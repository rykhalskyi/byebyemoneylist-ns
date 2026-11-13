<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service\Import;

use DateTime;
use DateTimeZone;
use OCA\ByeByeMoneyList\BackgroundJob\ToBuyAutoMatchJob;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\PurchaseImportMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Entity\ListItemEntity;
use OCA\ByeByeMoneyList\Entity\ProductAliasEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportCategoryEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportItemEntity;
use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Approves a reviewed purchase import into a real finished list in one
 * transaction, preserving the client list UUID.
 *
 * @psalm-suppress UnusedClass
 */
class PurchaseImportApproveService {
	private IDBConnection $db;
	private ListMapper $listMapper;
	private ListItemMapper $listItemMapper;
	private StoreMapper $storeMapper;
	private ProductMapper $productMapper;
	private ProductAliasMapper $productAliasMapper;
	private CategoryMapper $categoryMapper;
	private PurchaseImportMapper $importMapper;
	private LoggerInterface $logger;
	private IJobList $jobList;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		IDBConnection $db,
		ListMapper $listMapper,
		ListItemMapper $listItemMapper,
		StoreMapper $storeMapper,
		ProductMapper $productMapper,
		ProductAliasMapper $productAliasMapper,
		CategoryMapper $categoryMapper,
		PurchaseImportMapper $importMapper,
		LoggerInterface $logger,
		IJobList $jobList,
	) {
		$this->db = $db;
		$this->listMapper = $listMapper;
		$this->listItemMapper = $listItemMapper;
		$this->storeMapper = $storeMapper;
		$this->productMapper = $productMapper;
		$this->productAliasMapper = $productAliasMapper;
		$this->categoryMapper = $categoryMapper;
		$this->importMapper = $importMapper;
		$this->logger = $logger;
		$this->jobList = $jobList;
	}

	/**
	 * @param PurchaseImportItemEntity[] $items
	 * @param PurchaseImportCategoryEntity[] $categories
	 *
	 * @throws PurchaseImportDuplicateException
	 */
	public function approve(PurchaseImportEntity $import, array $items, array $categories): ListEntity {
		$owner = (string)$import->getOwner();
		$clientListId = (string)$import->getClientListId();

		if ($clientListId === '') {
			throw new RuntimeException('Import has no client list id');
		}
		if ($this->listMapper->findById($clientListId) !== null) {
			throw new PurchaseImportDuplicateException('List already exists');
		}

		$this->db->beginTransaction();
		try {
			$now = new DateTime('now', new DateTimeZone('UTC'));
			$storeId = $this->resolveStore($import, $owner);

			$listCategoryIds = [];
			foreach ($categories as $categoryRow) {
				$categoryId = $this->resolveCategoryRow($categoryRow, $owner);
				if ($categoryId === null) {
					continue;
				}
				if (!in_array($categoryId, $listCategoryIds, true)) {
					$listCategoryIds[] = $categoryId;
				}
			}

			$list = new ListEntity();
			$list->setId($clientListId);
			$list->setOwner($owner);
			$list->setName($this->listName($import));
			$list->setStatus('finished');
			$list->setIsFinished(true);
			$list->setKind(ListEntity::KIND_PURCHASE);
			$list->setIsSubscription(false);
			$list->setIsIncome(false);
			$list->setIsRecurring(false);
			$list->setRecurringPeriod('MONTH');
			$list->setIsForwardEmpty(true);
			$list->setPurchaseDate($import->getPurchaseDate() ?? $now);
			$list->setCreatedAt($import->getListCreatedAt() ?? $now);
			$list->setUpdatedAt($now);
			$list->setPosition($this->listMapper->getMaxPosition($owner) + 1);
			if ($storeId !== null) {
				$list->setStoreId($storeId);
			}

			$lineTotal = 0.0;
			$resolvedItems = [];
			foreach ($items as $itemRow) {
				if ((bool)$itemRow->getIsCoupon()) {
					continue;
				}
				$action = $itemRow->getAction() ?? PurchaseImportEntity::ACTION_PENDING;
				if ($action === PurchaseImportEntity::ACTION_SKIP) {
					continue;
				}

				$categoryId = $this->resolveItemCategory($itemRow, $owner);
				if ($categoryId !== null && !in_array($categoryId, $listCategoryIds, true)) {
					$listCategoryIds[] = $categoryId;
				}

				$productId = $this->resolveItemProduct($itemRow, $owner, $storeId, $categoryId);
				if ($productId === null) {
					continue;
				}

				$price = $itemRow->getUnitPrice() ?? 0.0;
				$quantity = $itemRow->getQuantity() ?? 1.0;
				$discount = $itemRow->getDiscount();
				$lineTotal += max(0.0, $price * $quantity - ($discount ?? 0.0));

				$resolvedItems[] = [
					'productId' => $productId,
					'rawName' => (string)$itemRow->getRawName(),
					'price' => $price,
					'quantity' => $quantity,
					'discount' => $discount,
					'position' => (int)$itemRow->getPosition(),
				];
			}

			$finalTotal = $import->getFinalTotal();
			if ($finalTotal === null) {
				$finalTotal = round($lineTotal, 2);
			}
			$list->setFinalTotal($finalTotal);
			$list->setCategoryId($listCategoryIds[0] ?? null);

			$created = $this->listMapper->insert($list);
			$listId = $created->getId();

			$position = 0;
			foreach ($resolvedItems as $resolved) {
				$product = $this->productMapper->findByIdAndOwner($resolved['productId'], $owner);
				$customName = null;
				if ($product !== null && mb_strtolower((string)$product->getName()) !== mb_strtolower($resolved['rawName'])) {
					$customName = $resolved['rawName'];
				}
				$this->insertListItem(
					$owner,
					$listId,
					$resolved['productId'],
					$resolved['price'],
					$resolved['quantity'],
					$resolved['discount'],
					$customName,
					$position++,
				);
			}

			if ($listCategoryIds !== []) {
				$this->listMapper->replaceCategoriesByListId($listId, $listCategoryIds);
			}

			$import->setStatus(PurchaseImportEntity::STATUS_APPROVED);
			$import->setApprovedListId($listId);
			$import->setApprovedAt($now);
			$import->setUpdatedAt($now);
			$this->importMapper->update($import);

			$this->db->commit();

			$purchasedNames = array_map(
				static fn (array $resolved): string => $resolved['rawName'],
				$resolvedItems,
			);
			$this->queueToBuyAutoMatch($owner, $purchasedNames);

			return $created;
		} catch (PurchaseImportDuplicateException $e) {
			$this->db->rollBack();
			throw $e;
		} catch (\Exception $e) {
			$this->db->rollBack();
			$this->logger->error('Failed to approve purchase import', ['exception' => $e]);
			throw new RuntimeException('Failed to approve purchase import', 0, $e);
		}
	}

	/**
	 * @param list<string> $purchasedNames
	 */
	private function queueToBuyAutoMatch(string $owner, array $purchasedNames): void {
		if ($purchasedNames === []) {
			return;
		}
		try {
			$this->jobList->add(ToBuyAutoMatchJob::class, [
				'userId' => $owner,
				'names' => $purchasedNames,
			]);
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to queue To Buy auto-check', ['exception' => $e]);
		}
	}

	private function resolveStore(PurchaseImportEntity $import, string $owner): ?string {
		$action = $import->getStoreAction() ?? PurchaseImportEntity::ACTION_PENDING;
		if ($action === PurchaseImportEntity::ACTION_SKIP) {
			return null;
		}

		$mappedId = $import->getMappedStoreId();
		if ($mappedId === null || $mappedId === '') {
			$mappedId = $action === PurchaseImportEntity::ACTION_PENDING ? $import->getSuggestedStoreId() : null;
		}
		if ($mappedId !== null && $mappedId !== '') {
			$store = $this->storeMapper->findByIdAndOwner($mappedId, $owner);
			if ($store !== null) {
				return $store->getId();
			}
		}

		$name = trim((string)($import->getSuggestedStoreName() ?? $import->getStoreName()));
		if ($name === '') {
			return null;
		}

		$existing = $this->storeMapper->findByNameAndOwner($name, $owner);
		if ($existing !== null) {
			return $existing->getId();
		}

		$store = new StoreEntity();
		$store->setId(Uuid::v4());
		$store->setOwner($owner);
		$store->setName($name);
		$address = trim((string)$import->getStoreAddress());
		if ($address !== '') {
			$store->setAddress($address);
		}
		$this->storeMapper->insert($store);

		return $store->getId();
	}

	private function resolveCategoryRow(PurchaseImportCategoryEntity $row, string $owner): ?string {
		$action = $row->getAction() ?? PurchaseImportEntity::ACTION_PENDING;
		if ($action === PurchaseImportEntity::ACTION_SKIP) {
			return null;
		}

		$mappedId = $row->getMappedCategoryId();
		if ($mappedId === null || $mappedId === '') {
			$mappedId = $action === PurchaseImportEntity::ACTION_PENDING ? $row->getSuggestedCategoryId() : null;
		}
		if ($mappedId !== null && $mappedId !== '') {
			$category = $this->categoryMapper->findByIdAndOwner($mappedId, $owner);
			if ($category !== null) {
				return $category->getId();
			}
		}

		$name = trim((string)$row->getRawName());
		if ($name === '') {
			return null;
		}

		$existing = $this->categoryMapper->findByNameAndOwner($name, $owner);
		if ($existing !== null) {
			return $existing->getId();
		}

		$category = new CategoryEntity();
		$category->setId(Uuid::v4());
		$category->setOwner($owner);
		$category->setName($name);
		$category->setIncome((bool)$row->getIsIncome());
		$category->setStatus('confirmed');
		$this->categoryMapper->insert($category);

		return $category->getId();
	}

	private function resolveItemCategory(PurchaseImportItemEntity $row, string $owner): ?string {
		foreach ([$row->getMappedCategoryId(), $row->getSuggestedCategoryId()] as $candidateId) {
			if ($candidateId === null || $candidateId === '') {
				continue;
			}
			$category = $this->categoryMapper->findByIdAndOwner($candidateId, $owner);
			if ($category !== null) {
				return $category->getId();
			}
		}

		return null;
	}

	private function resolveItemProduct(
		PurchaseImportItemEntity $row,
		string $owner,
		?string $storeId,
		?string $categoryId,
	): ?string {
		$action = $row->getAction() ?? PurchaseImportEntity::ACTION_PENDING;

		$candidateIds = [$row->getMappedProductId()];
		if ($action === PurchaseImportEntity::ACTION_PENDING) {
			$candidateIds[] = $row->getSuggestedProductId();
		}
		foreach ($candidateIds as $candidateId) {
			if ($candidateId === null || $candidateId === '') {
				continue;
			}
			$product = $this->productMapper->findByIdAndOwner($candidateId, $owner);
			if ($product !== null) {
				return $product->getId();
			}
		}

		$rawName = trim((string)$row->getRawName());
		$newName = trim((string)$row->getNewProductName());
		if ($newName === '') {
			$newName = trim((string)$row->getSuggestedProductName());
		}
		$productName = $newName !== '' ? $newName : $rawName;
		if ($productName === '') {
			return null;
		}

		return $this->createOrReuseProduct($owner, $productName, $rawName, $categoryId, $storeId);
	}

	private function createOrReuseProduct(
		string $owner,
		string $productName,
		string $aliasName,
		?string $categoryId,
		?string $storeId,
	): string {
		$existing = $this->productMapper->findByNameAndOwner($productName, $owner);
		if ($existing !== null) {
			return $existing->getId();
		}

		$product = new ProductEntity();
		$product->setId(Uuid::v4());
		$product->setOwner($owner);
		$product->setName($productName);
		$product->setStatus('added');
		$product->setIsFavorite(false);
		$product->setIsSubscription(false);
		$product->setIsIncome(false);
		if ($categoryId !== null) {
			$product->setCategoryId($categoryId);
		}
		$created = $this->productMapper->insert($product);

		$trimmedAlias = trim($aliasName);
		if ($trimmedAlias !== '' && mb_strtolower($trimmedAlias) !== mb_strtolower($productName)) {
			$alias = new ProductAliasEntity();
			$alias->setId(Uuid::v4());
			$alias->setOwner($owner);
			$alias->setProductId($created->getId());
			$alias->setAliasName($trimmedAlias);
			if ($storeId !== null) {
				$alias->setStoreId($storeId);
			}
			$this->productAliasMapper->insert($alias);
		}

		return $created->getId();
	}

	private function insertListItem(
		string $owner,
		string $listId,
		string $productId,
		float $price,
		float $quantity,
		?float $discount,
		?string $customName,
		int $position,
	): void {
		$item = new ListItemEntity();
		$item->setId(Uuid::v4());
		$item->setOwner($owner);
		$item->setListId($listId);
		$item->setProductId($productId);
		$item->setPrice(round($price, 2));
		$item->setQuantity(round($quantity, 3));
		$item->setDiscount($discount !== null ? round($discount, 2) : null);
		$item->setIsChecked(true);
		$item->setStatus('added');
		$item->setPosition(max(0, $position));
		if ($customName !== null && $customName !== '') {
			$item->setCustomName($customName);
		}
		$now = new DateTime('now', new DateTimeZone('UTC'));
		$item->setCreatedAt($now);
		$item->setUpdatedAt($now);
		$this->listItemMapper->insert($item);
	}

	private function listName(PurchaseImportEntity $import): string {
		$name = trim((string)$import->getName());

		return $name !== '' ? $name : 'Imported purchase';
	}
}
