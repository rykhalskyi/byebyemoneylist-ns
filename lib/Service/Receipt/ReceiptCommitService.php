<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service\Receipt;

use DateTime;
use DateTimeZone;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\ProductPriceMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Entity\ListItemEntity;
use OCA\ByeByeMoneyList\Entity\ProductAliasEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Entity\ProductPriceEntity;
use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCA\ByeByeMoneyList\Service\ReceiptPictureService;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Transactionally saves a reviewed receipt: resolves/creates the store, creates a
 * finished list, matches or creates products (and aliases), inserts list items,
 * records prices, and optionally stores the receipt image.
 *
 * @psalm-suppress UnusedClass
 */
class ReceiptCommitService {
	private IDBConnection $db;
	private ListMapper $listMapper;
	private ListItemMapper $listItemMapper;
	private StoreMapper $storeMapper;
	private ProductMapper $productMapper;
	private ProductAliasMapper $productAliasMapper;
	private ProductPriceMapper $productPriceMapper;
	private CategoryMapper $categoryMapper;
	private ReceiptPictureService $receiptPictureService;
	private ReceiptProductMatcher $matcher;
	private LoggerInterface $logger;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		IDBConnection $db,
		ListMapper $listMapper,
		ListItemMapper $listItemMapper,
		StoreMapper $storeMapper,
		ProductMapper $productMapper,
		ProductAliasMapper $productAliasMapper,
		ProductPriceMapper $productPriceMapper,
		CategoryMapper $categoryMapper,
		ReceiptPictureService $receiptPictureService,
		ReceiptProductMatcher $matcher,
		LoggerInterface $logger,
	) {
		$this->db = $db;
		$this->listMapper = $listMapper;
		$this->listItemMapper = $listItemMapper;
		$this->storeMapper = $storeMapper;
		$this->productMapper = $productMapper;
		$this->productAliasMapper = $productAliasMapper;
		$this->productPriceMapper = $productPriceMapper;
		$this->categoryMapper = $categoryMapper;
		$this->receiptPictureService = $receiptPictureService;
		$this->matcher = $matcher;
		$this->logger = $logger;
	}

	/**
	 * @param list<array{productId?: ?string, name: string, quantity: float, price: float, discount?: ?float, isCoupon?: bool, categoryId?: ?string, categoryName?: ?string}> $items
	 */
	public function commit(
		string $userId,
		string $name,
		?string $storeName,
		?string $storeAddress,
		?float $finalTotal,
		DateTime $purchaseDate,
		array $items,
		bool $saveReceipt,
		?string $receiptTmpPath,
		?string $receiptExtension,
	): ListEntity {
		$stores = array_values($this->storeMapper->findAllByOwner($userId));
		$products = array_values($this->productMapper->findAllIncludingSpecialByOwner($userId));
		$aliasMap = $this->buildAliasMap($products, $userId);

		$this->db->beginTransaction();
		try {
			$storeId = $this->resolveStore($userId, $storeName, $storeAddress, $stores);

			$list = new ListEntity();
			$list->setId(Uuid::v4());
			$list->setOwner($userId);
			$list->setName($name);
			$list->setStatus('finished');
			$list->setIsFinished(true);
			$list->setFinalTotal($finalTotal !== null ? round($finalTotal, 2) : null);
			$list->setPurchaseDate($purchaseDate);
			$list->setPosition(0);
			$list->setIsRecurring(false);
			$list->setRecurringPeriod('MONTH');
			$list->setIsForwardEmpty(true);
			$list->setIsSubscription(false);
			$list->setIsIncome(false);
			if ($storeId !== null) {
				$list->setStoreId($storeId);
			}
			$now = new DateTime('now', new DateTimeZone('UTC'));
			$list->setCreatedAt($now);
			$list->setUpdatedAt($now);

			$created = $this->listMapper->insert($list);
			$listId = $created->getId();

			$couponProductId = null;
			$categoryIds = [];
			$index = 0;
			foreach ($items as $item) {
				$itemName = trim($item['name']);
				if ($itemName === '') {
					continue;
				}
				$isCoupon = $item['isCoupon'] ?? false;
				$quantity = max(0.0, $item['quantity']);
				$price = $item['price'];
				$discount = $item['discount'] ?? null;

				$categoryId = $this->resolveCategory($userId, $item['categoryId'] ?? null, $item['categoryName'] ?? null);
				if ($categoryId !== null && !in_array($categoryId, $categoryIds, true)) {
					$categoryIds[] = $categoryId;
				}

				$customName = null;
				if ($isCoupon) {
					if ($couponProductId === null) {
						$couponProductId = $this->getOrCreateCouponProductId($userId, $products);
					}
					$productId = $couponProductId;
					$customName = $itemName;
				} else {
					$productId = $this->resolveProduct($userId, $item['productId'] ?? null, $itemName, $storeId, $categoryId, $products, $aliasMap);
				}

				$this->insertListItem($userId, $listId, $productId, $price, $quantity, $discount, $customName, $index);
				if (!$isCoupon && $price > 0) {
					$this->upsertPrice($userId, $productId, $storeId, $price, $purchaseDate);
				}
				$index++;
			}

			if ($categoryIds !== []) {
				$created->setCategoryId($categoryIds[0]);
				$this->listMapper->replaceCategoriesByListId($listId, $categoryIds);
			}

			if ($saveReceipt && $receiptTmpPath !== null && $receiptExtension !== null) {
				$path = $this->receiptPictureService->store($userId, $listId, $receiptTmpPath, $receiptExtension);
				$created->setReceiptPath($path);
			}

			$created->setUpdatedAt(new DateTime('now', new DateTimeZone('UTC')));
			$updated = $this->listMapper->update($created);

			$this->db->commit();

			return $updated;
		} catch (\Exception $e) {
			$this->db->rollBack();
			$this->logger->error('Failed to commit scanned receipt', ['exception' => $e]);
			throw new RuntimeException('Failed to save the scanned receipt', 0, $e);
		}
	}

	/**
	 * @param list<StoreEntity> $stores
	 */
	private function resolveStore(string $userId, ?string $storeName, ?string $storeAddress, array $stores): ?string {
		$trimmed = trim((string)$storeName);
		if ($trimmed === '') {
			return null;
		}

		foreach ($stores as $store) {
			if (mb_strtolower((string)$store->getName()) === mb_strtolower($trimmed)) {
				if ($store->getAddress() === null && $storeAddress !== null && trim($storeAddress) !== '') {
					$store->setAddress(trim($storeAddress));
					$this->storeMapper->update($store);
				}
				return $store->getId();
			}
		}

		$store = new StoreEntity();
		$store->setId(Uuid::v4());
		$store->setOwner($userId);
		$store->setName($trimmed);
		if ($storeAddress !== null && trim($storeAddress) !== '') {
			$store->setAddress(trim($storeAddress));
		}
		$this->storeMapper->insert($store);

		return $store->getId();
	}

	private function resolveCategory(string $userId, ?string $categoryId, ?string $categoryName): ?string {
		if ($categoryId !== null && $categoryId !== '') {
			$category = $this->categoryMapper->findByIdAndOwner($categoryId, $userId);
			if ($category !== null) {
				return $category->getId();
			}
		}
		$trimmed = trim((string)$categoryName);
		if ($trimmed === '') {
			return null;
		}
		return $this->categoryMapper->getOrCreate($trimmed, $userId)->getId();
	}

	/**
	 * @param list<ProductEntity> $products
	 * @param array<string, ProductEntity> $aliasMap
	 */
	private function resolveProduct(
		string $userId,
		?string $productId,
		string $name,
		?string $storeId,
		?string $categoryId,
		array $products,
		array $aliasMap,
	): string {
		if ($productId !== null && $productId !== '') {
			$existing = $this->productMapper->findByIdAndOwner($productId, $userId);
			if ($existing !== null) {
				return $existing->getId();
			}
		}

		$matched = $this->matcher->match($name, $products, $aliasMap);
		if ($matched !== null) {
			$lower = mb_strtolower(trim($name));
			$nameDiffers = mb_strtolower((string)$matched->getName()) !== $lower;
			if ($nameDiffers && !isset($aliasMap[$lower])) {
				$this->insertAlias($userId, $matched->getId(), $name, $storeId);
			}
			return $matched->getId();
		}

		$product = new ProductEntity();
		$product->setId(Uuid::v4());
		$product->setOwner($userId);
		$product->setName($name);
		$product->setStatus('added');
		$product->setIsFavorite(false);
		$product->setIsSubscription(false);
		$product->setIsIncome(false);
		if ($categoryId !== null) {
			$product->setCategoryId($categoryId);
		}
		$created = $this->productMapper->insert($product);

		$this->insertAlias($userId, $created->getId(), $name, $storeId);

		return $created->getId();
	}

	/**
	 * @param list<ProductEntity> $products
	 */
	private function getOrCreateCouponProductId(string $userId, array $products): string {
		foreach ($products as $product) {
			if (mb_strtolower((string)$product->getName()) === 'coupon') {
				return $product->getId();
			}
		}

		$product = new ProductEntity();
		$product->setId(Uuid::v4());
		$product->setOwner($userId);
		$product->setName('Coupon');
		$product->setStatus('reviewed');
		$product->setIsFavorite(false);
		$product->setIsSubscription(false);
		$product->setIsIncome(false);
		$this->productMapper->insert($product);

		return $product->getId();
	}

	private function insertListItem(string $userId, string $listId, ?string $productId, float $price, float $quantity, ?float $discount, ?string $customName, int $position): void {
		if ($productId === null) {
			return;
		}
		$item = new ListItemEntity();
		$item->setId(Uuid::v4());
		$item->setOwner($userId);
		$item->setListId($listId);
		$item->setProductId($productId);
		$item->setQuantity(round($quantity, 2));
		$item->setPrice(round($price, 2));
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

	private function upsertPrice(string $userId, ?string $productId, ?string $storeId, float $price, DateTime $purchaseDate): void {
		if ($productId === null) {
			return;
		}
		$existing = $this->productPriceMapper->findByProductAndStore($productId, $storeId, $userId);
		$now = new DateTime('now', new DateTimeZone('UTC'));
		if ($existing !== null) {
			$existing->setValue(round($price, 2));
			$existing->setPriceDate($purchaseDate);
			$this->productPriceMapper->update($existing);
			return;
		}

		$priceEntity = new ProductPriceEntity();
		$priceEntity->setId(Uuid::v4());
		$priceEntity->setOwner($userId);
		$priceEntity->setProductId($productId);
		$priceEntity->setStoreId($storeId);
		$priceEntity->setValue(round($price, 2));
		$priceEntity->setPriceDate($purchaseDate);
		$priceEntity->setCreatedAt($now);
		$this->productPriceMapper->insert($priceEntity);
	}

	private function insertAlias(string $userId, string $productId, string $name, ?string $storeId): void {
		$alias = new ProductAliasEntity();
		$alias->setId(Uuid::v4());
		$alias->setOwner($userId);
		$alias->setProductId($productId);
		$alias->setAliasName($name);
		if ($storeId !== null) {
			$alias->setStoreId($storeId);
		}
		$this->productAliasMapper->insert($alias);
	}

	/**
	 * @param list<ProductEntity> $products
	 *
	 * @return array<string, ProductEntity>
	 */
	private function buildAliasMap(array $products, string $userId): array {
		$byId = [];
		foreach ($products as $product) {
			$byId[$product->getId()] = $product;
		}
		$ids = array_keys($byId);

		$map = [];
		foreach ($this->productAliasMapper->findByProductIds($ids, $userId) as $alias) {
			$productId = $alias->getProductId();
			$aliasName = $alias->getAliasName();
			if ($productId === null || $aliasName === null) {
				continue;
			}
			$product = $byId[$productId] ?? null;
			if ($product !== null) {
				$map[mb_strtolower($aliasName)] = $product;
			}
		}
		return $map;
	}
}
