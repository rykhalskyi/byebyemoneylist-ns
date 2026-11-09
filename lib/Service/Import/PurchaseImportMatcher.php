<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service\Import;

use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportCategoryEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportItemEntity;
use OCA\ByeByeMoneyList\Entity\StoreEntity;

/**
 * Deterministic matching for an incoming purchase import: exact
 * (case-insensitive) store/category/product names, product barcodes and product
 * aliases. Fuzzy matching and AI suggestions are a later slice.
 *
 * The matcher only fills suggestion fields; it never mutates catalog rows.
 *
 * @psalm-suppress UnusedClass
 */
class PurchaseImportMatcher {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private StoreMapper $storeMapper,
		private CategoryMapper $categoryMapper,
		private ProductMapper $productMapper,
		private ProductAliasMapper $productAliasMapper,
	) {
	}

	/**
	 * @param PurchaseImportItemEntity[] $items
	 * @param PurchaseImportCategoryEntity[] $categories
	 */
	public function suggestForImport(
		PurchaseImportEntity $import,
		array $items,
		array $categories,
		string $owner,
	): void {
		$stores = $this->storeMapper->findAllByOwner($owner);
		$ownCategories = $this->categoryMapper->findAllByOwner($owner);
		$products = $this->productMapper->findAllIncludingSpecialByOwner($owner);
		$aliases = $this->productAliasMapper->findByProductIds(
			array_values(array_map(static fn (ProductEntity $product): string => $product->getId(), $products)),
			$owner,
		);

		$storeByName = $this->indexStores($stores);
		$categoryByName = $this->indexCategories($ownCategories);
		$productByName = $this->indexProducts($products);
		$productByBarcode = [];
		foreach ($products as $product) {
			$barcode = trim((string)$product->getBarcode());
			if ($barcode !== '') {
				$productByBarcode[$barcode] = $product;
			}
		}
		$productByAlias = [];
		foreach ($aliases as $alias) {
			$key = $this->normalize((string)$alias->getAliasName());
			$aliasProductId = $alias->getProductId();
			if ($key !== '' && $aliasProductId !== null && $aliasProductId !== '') {
				$productByAlias[$key] = $aliasProductId;
			}
		}
		$productById = [];
		foreach ($products as $product) {
			$productById[$product->getId()] = $product;
		}

		$this->suggestStore($import, $storeByName);
		$this->suggestCategories($categories, $categoryByName);

		foreach ($items as $item) {
			$this->suggestItem(
				$item,
				$productByName,
				$productByAlias,
				$productByBarcode,
				$productById,
				$categoryByName,
			);
		}
	}

	/**
	 * @param StoreEntity[] $stores
	 *
	 * @return array<string, StoreEntity>
	 */
	private function indexStores(array $stores): array {
		$map = [];
		foreach ($stores as $store) {
			$key = $this->normalize((string)$store->getName());
			if ($key !== '' && !isset($map[$key])) {
				$map[$key] = $store;
			}
		}
		return $map;
	}

	/**
	 * @param CategoryEntity[] $categories
	 *
	 * @return array<string, CategoryEntity>
	 */
	private function indexCategories(array $categories): array {
		$map = [];
		foreach ($categories as $category) {
			$key = $this->normalize((string)$category->getName());
			if ($key !== '' && !isset($map[$key])) {
				$map[$key] = $category;
			}
		}
		return $map;
	}

	/**
	 * @param ProductEntity[] $products
	 *
	 * @return array<string, ProductEntity>
	 */
	private function indexProducts(array $products): array {
		$map = [];
		foreach ($products as $product) {
			$key = $this->normalize((string)$product->getName());
			if ($key !== '' && !isset($map[$key])) {
				$map[$key] = $product;
			}
		}
		return $map;
	}

	/**
	 * @param array<string, StoreEntity> $storeByName
	 */
	private function suggestStore(PurchaseImportEntity $import, array $storeByName): void {
		$rawName = trim((string)$import->getStoreName());
		if ($rawName === '') {
			return;
		}

		$store = $storeByName[$this->normalize($rawName)] ?? null;
		if ($store === null) {
			$receiptName = trim((string)$import->getStoreReceiptName());
			if ($receiptName !== '') {
				$store = $storeByName[$this->normalize($receiptName)] ?? null;
			}
		}

		if ($store !== null) {
			$import->setSuggestedStoreId($store->getId());
			$import->setStoreMatchSource('exact');
			$import->setStoreConfidence(1.0);
			return;
		}

		$import->setSuggestedStoreName($rawName);
	}

	/**
	 * @param PurchaseImportCategoryEntity[] $categories
	 * @param array<string, CategoryEntity> $categoryByName
	 */
	private function suggestCategories(array $categories, array $categoryByName): void {
		foreach ($categories as $category) {
			$key = $this->normalize((string)$category->getRawName());
			$match = $key === '' ? null : ($categoryByName[$key] ?? null);
			if ($match !== null) {
				$category->setSuggestedCategoryId($match->getId());
				$category->setMatchSource('exact');
				$category->setConfidence(1.0);
			}
		}
	}

	/**
	 * @param array<string, ProductEntity> $productByName
	 * @param array<string, string> $productByAlias alias name => product id
	 * @param array<string, ProductEntity> $productByBarcode
	 * @param array<string, ProductEntity> $productById
	 * @param array<string, CategoryEntity> $categoryByName
	 */
	private function suggestItem(
		PurchaseImportItemEntity $item,
		array $productByName,
		array $productByAlias,
		array $productByBarcode,
		array $productById,
		array $categoryByName,
	): void {
		$product = null;
		$source = null;

		$barcode = trim((string)$item->getBarcode());
		if ($barcode !== '' && isset($productByBarcode[$barcode])) {
			$product = $productByBarcode[$barcode];
			$source = 'barcode';
		}

		if ($product === null) {
			$key = $this->normalize((string)$item->getRawName());
			if ($key !== '' && isset($productByName[$key])) {
				$product = $productByName[$key];
				$source = 'exact';
			}
		}

		if ($product === null) {
			$key = $this->normalize((string)$item->getRawName());
			$aliasProductId = $key === '' ? null : ($productByAlias[$key] ?? null);
			if ($aliasProductId !== null && isset($productById[$aliasProductId])) {
				$product = $productById[$aliasProductId];
				$source = 'alias';
			}
		}

		if ($product !== null) {
			$item->setSuggestedProductId($product->getId());
			$item->setMatchSource($source);
			$item->setConfidence(1.0);
		} else {
			$item->setSuggestedProductName(trim((string)$item->getRawName()));
		}

		$hint = trim((string)$item->getCategoryHint());
		$hintKey = $this->normalize($hint);
		$category = $hintKey === '' ? null : ($categoryByName[$hintKey] ?? null);
		if ($category === null && $product !== null) {
			$categoryId = (string)$product->getCategoryId();
			$category = $categoryId === '' ? null : $this->findCategory($categoryId, $categoryByName);
		}

		if ($category !== null) {
			$item->setSuggestedCategoryId($category->getId());
		}
	}

	/**
	 * @param array<string, CategoryEntity> $categoryByName
	 */
	private function findCategory(string $categoryId, array $categoryByName): ?CategoryEntity {
		foreach ($categoryByName as $category) {
			if ($category->getId() === $categoryId) {
				return $category;
			}
		}
		return null;
	}

	private function normalize(string $raw): string {
		$value = mb_strtolower(trim($raw));
		$ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		if ($ascii !== false) {
			$value = $ascii;
		}
		$value = (string)preg_replace('/[^a-z0-9]+/', ' ', $value);
		return trim((string)preg_replace('/\s+/', ' ', $value));
	}
}
