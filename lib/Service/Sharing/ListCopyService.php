<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service\Sharing;

use DateTime;
use DateTimeZone;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Entity\ListItemEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\IDBConnection;

/**
 * Deep-copies a readable list (and its products/categories/stores) into the
 * current user's own catalog. Catalog rows are matched by name and reused when
 * they already exist, so repeated copies do not duplicate the user's catalog.
 */
class ListCopyService {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private ListMapper $listMapper,
		private ListItemMapper $itemMapper,
		private CategoryMapper $categoryMapper,
		private ProductMapper $productMapper,
		private StoreMapper $storeMapper,
		private ListAccessService $listAccess,
		private IDBConnection $db,
	) {
	}

	/**
	 * Copy a list the user can read into their own catalog. Returns null when the
	 * list is not readable. The new list is a fresh, unfinished list preserving
	 * items, quantities and prices; product/category/store rows are mapped by name.
	 */
	public function copy(string $listId, string $userId): ?ListEntity {
		$source = $this->listAccess->findReadable($listId, $userId);
		if ($source === null) {
			return null;
		}
		$sourceOwner = $source->getOwner() ?? '';
		$now = new DateTime('now', new DateTimeZone('UTC'));

		$started = false;
		try {
			$this->db->beginTransaction();
			$started = true;

			/** @var array<string, string> $categoryMap */
			$categoryMap = [];
			$sourceCategoryIds = $this->listMapper->findCategoryIdsByListIds([$listId])[$listId] ?? [];
			$this->copyCategories($sourceCategoryIds, $sourceOwner, $userId, $categoryMap);

			$mappedStoreId = $this->copyStore($source->getStoreId(), $sourceOwner, $userId, $categoryMap);

			$list = new ListEntity();
			$list->setId(Uuid::v4());
			$list->setOwner($userId);
			$list->setName($source->getName() ?? '');
			$list->setStoreId($mappedStoreId);
			$primaryCategoryId = $source->getCategoryId();
			$list->setCategoryId($primaryCategoryId !== null && isset($categoryMap[$primaryCategoryId]) ? $categoryMap[$primaryCategoryId] : null);
			$list->setStatus('new');
			$list->setFinalTotal(null);
			$list->setCreatedAt($now);
			$list->setUpdatedAt($now);
			$list->setPurchaseDate(null);
			$list->setIsFinished(false);
			$list->setPosition($source->getPosition() ?? 0);
			$list->setIsRecurring($source->getIsRecurring() ?? false);
			$list->setRecurringPeriod($source->getRecurringPeriod() ?? 'MONTH');
			$list->setIsForwardEmpty($source->getIsForwardEmpty() ?? true);
			$list->setIsSubscription($source->getIsSubscription() ?? false);
			$list->setIsIncome($source->getIsIncome() ?? false);
			$list->setReceiptPath(null);
			$created = $this->listMapper->insert($list);

			$mappedCategoryIds = [];
			foreach ($sourceCategoryIds as $sourceCategoryId) {
				if (isset($categoryMap[$sourceCategoryId])) {
					$mappedCategoryIds[] = $categoryMap[$sourceCategoryId];
				}
			}
			if ($mappedCategoryIds !== []) {
				$this->listMapper->replaceCategoriesByListId($created->getId(), $mappedCategoryIds);
			}

			/** @var array<string, string> $productMap */
			$productMap = [];
			foreach ($this->itemMapper->findByListId($listId) as $sourceItem) {
				$productId = $this->copyProduct(
					$sourceItem->getProductId(),
					$sourceOwner,
					$userId,
					$categoryMap,
					$productMap,
				);
				if ($productId === null) {
					continue;
				}

				$item = new ListItemEntity();
				$item->setId(Uuid::v4());
				$item->setOwner($userId);
				$item->setListId($created->getId());
				$item->setProductId($productId);
				$item->setPrice($sourceItem->getPrice());
				$item->setQuantity($sourceItem->getQuantity() ?? 1.0);
				$item->setDiscount($sourceItem->getDiscount());
				$item->setCustomName($sourceItem->getCustomName());
				$item->setPosition($sourceItem->getPosition() ?? 0);
				$item->setIsChecked(false);
				$item->setStatus('added');
				$item->setCreatedAt($now);
				$item->setUpdatedAt($now);
				$this->itemMapper->insert($item);
			}

			$this->db->commit();

			return $created;
		} catch (\Throwable $e) {
			if ($started) {
				$this->db->rollBack();
			}
			throw $e;
		}
	}

	/**
	 * @param list<string> $sourceCategoryIds
	 * @param array<string, string> $categoryMap
	 */
	private function copyCategories(array $sourceCategoryIds, string $sourceOwner, string $userId, array &$categoryMap): void {
		foreach ($sourceCategoryIds as $sourceCategoryId) {
			if (isset($categoryMap[$sourceCategoryId])) {
				continue;
			}
			$source = $this->categoryMapper->findByIdAndOwner($sourceCategoryId, $sourceOwner);
			if ($source === null) {
				continue;
			}

			$name = $source->getName() ?? '';
			$own = $this->categoryMapper->findByNameAndOwner($name, $userId);
			if ($own === null) {
				$own = new CategoryEntity();
				$own->setId(Uuid::v4());
				$own->setOwner($userId);
				$own->setName($name);
				$own->setColor($source->getColor());
				$own->setEmoji($source->getEmoji());
				$own->setIncome($source->getIncome() ?? false);
				$own->setStatus('confirmed');
				$parentId = $source->getParentId();
				$own->setParentId($parentId !== null && isset($categoryMap[$parentId]) ? $categoryMap[$parentId] : null);
				$own = $this->categoryMapper->insert($own);
			}

			$categoryMap[$sourceCategoryId] = $own->getId();
		}
	}

	/**
	 * @param array<string, string> $categoryMap
	 */
	private function copyStore(?string $sourceStoreId, string $sourceOwner, string $userId, array &$categoryMap): ?string {
		if ($sourceStoreId === null || $sourceStoreId === '') {
			return null;
		}
		$source = $this->storeMapper->findByIdAndOwner($sourceStoreId, $sourceOwner);
		if ($source === null) {
			return null;
		}

		$name = $source->getName() ?? '';
		$own = $this->storeMapper->findByNameAndOwner($name, $userId);
		if ($own !== null) {
			return $own->getId();
		}

		$store = new StoreEntity();
		$store->setId(Uuid::v4());
		$store->setOwner($userId);
		$store->setName($name);
		$store->setAddress($source->getAddress());
		$created = $this->storeMapper->insert($store);

		$sourceCategoryIds = $this->storeMapper->findCategoryIdsByStoreIds([$sourceStoreId])[$sourceStoreId] ?? [];
		$this->copyCategories($sourceCategoryIds, $sourceOwner, $userId, $categoryMap);
		$mappedCategoryIds = [];
		foreach ($sourceCategoryIds as $sourceCategoryId) {
			if (isset($categoryMap[$sourceCategoryId])) {
				$mappedCategoryIds[] = $categoryMap[$sourceCategoryId];
			}
		}
		if ($mappedCategoryIds !== []) {
			$this->storeMapper->replaceCategoriesByStoreId($created->getId(), $mappedCategoryIds);
		}

		return $created->getId();
	}

	/**
	 * @param array<string, string> $categoryMap
	 * @param array<string, string> $productMap
	 */
	private function copyProduct(?string $sourceProductId, string $sourceOwner, string $userId, array $categoryMap, array &$productMap): ?string {
		if ($sourceProductId === null || $sourceProductId === '') {
			return null;
		}
		if (isset($productMap[$sourceProductId])) {
			return $productMap[$sourceProductId];
		}

		$source = $this->productMapper->findByIdAndOwner($sourceProductId, $sourceOwner);
		if ($source === null) {
			return null;
		}

		$name = $source->getName() ?? '';
		$own = $this->productMapper->findByNameAndOwner($name, $userId);
		if ($own === null) {
			$own = new ProductEntity();
			$own->setId(Uuid::v4());
			$own->setOwner($userId);
			$own->setName($name);
			$own->setBarcode($source->getBarcode());
			$categoryId = $source->getCategoryId();
			$own->setCategoryId($categoryId !== null && isset($categoryMap[$categoryId]) ? $categoryMap[$categoryId] : null);
			$own->setStatus($source->getStatus() ?? 'reviewed');
			$own->setIsFavorite(false);
			$own->setIsSubscription($source->getIsSubscription() ?? false);
			$own->setIsIncome($source->getIsIncome() ?? false);
			$own = $this->productMapper->insert($own);
		}

		$productMap[$sourceProductId] = $own->getId();

		return $own->getId();
	}
}
