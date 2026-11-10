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
use OCA\ByeByeMoneyList\Service\Receipt\ReceiptProductMatcher;

/**
 * Deterministic matching for an incoming purchase import: exact
 * (case-insensitive) store/category/product names, product barcodes and product
 * aliases first, then the same fuzzy matching used for receipt products.
 *
 * The matcher only fills suggestion fields; it never mutates catalog rows.
 *
 * @psalm-suppress UnusedClass
 */
class PurchaseImportMatcher {
	private const FUZZY_CONFIDENCE = 0.75;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private StoreMapper $storeMapper,
		private CategoryMapper $categoryMapper,
		private ProductMapper $productMapper,
		private ProductAliasMapper $productAliasMapper,
		private ReceiptProductMatcher $receiptMatcher,
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

		$storeByName = $this->indexByNormalizedName($stores);
		$storeByDisplayName = $this->indexByDisplayName($stores);
		$categoryByName = $this->indexByNormalizedName($ownCategories);
		$categoryByDisplayName = $this->indexByDisplayName($ownCategories);
		$productByName = $this->indexByNormalizedName($products);
		$productByDisplayName = $this->indexByDisplayName($products);
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

		$this->suggestStore($import, $storeByName, $storeByDisplayName);
		$this->suggestCategories($categories, $categoryByName, $categoryByDisplayName);

		foreach ($items as $item) {
			$this->suggestItem(
				$item,
				$productByName,
				$productByDisplayName,
				$productByAlias,
				$productByBarcode,
				$productById,
				$categoryByName,
				$categoryByDisplayName,
			);
		}
	}

	/**
	 * @template T of StoreEntity|CategoryEntity|ProductEntity
	 *
	 * @param T[] $entities
	 *
	 * @return array<string, T>
	 */
	private function indexByNormalizedName(array $entities): array {
		$map = [];
		foreach ($entities as $entity) {
			$key = $this->normalize((string)$entity->getName());
			if ($key !== '' && !isset($map[$key])) {
				$map[$key] = $entity;
			}
		}
		return $map;
	}

	/**
	 * @template T of StoreEntity|CategoryEntity|ProductEntity
	 *
	 * @param T[] $entities
	 *
	 * @return array<string, T>
	 */
	private function indexByDisplayName(array $entities): array {
		$map = [];
		foreach ($entities as $entity) {
			$name = (string)$entity->getName();
			if ($name !== '' && !isset($map[$name])) {
				$map[$name] = $entity;
			}
		}
		return $map;
	}

	/**
	 * @param array<string, StoreEntity> $storeByName
	 * @param array<string, StoreEntity> $storeByDisplayName
	 */
	private function suggestStore(PurchaseImportEntity $import, array $storeByName, array $storeByDisplayName): void {
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

		$matchedName = $this->receiptMatcher->findFuzzyName($rawName, array_keys($storeByDisplayName));
		if ($matchedName !== null) {
			$import->setSuggestedStoreId($storeByDisplayName[$matchedName]->getId());
			$import->setStoreMatchSource('fuzzy');
			$import->setStoreConfidence(self::FUZZY_CONFIDENCE);
			return;
		}

		$import->setSuggestedStoreName($rawName);
	}

	/**
	 * @param PurchaseImportCategoryEntity[] $categories
	 * @param array<string, CategoryEntity> $categoryByName
	 * @param array<string, CategoryEntity> $categoryByDisplayName
	 */
	private function suggestCategories(array $categories, array $categoryByName, array $categoryByDisplayName): void {
		foreach ($categories as $category) {
			$rawName = (string)$category->getRawName();
			$key = $this->normalize($rawName);
			$match = $key === '' ? null : ($categoryByName[$key] ?? null);
			if ($match !== null) {
				$category->setSuggestedCategoryId($match->getId());
				$category->setMatchSource('exact');
				$category->setConfidence(1.0);
				continue;
			}

			$matchedName = $this->receiptMatcher->findFuzzyName($rawName, array_keys($categoryByDisplayName));
			if ($matchedName !== null) {
				$category->setSuggestedCategoryId($categoryByDisplayName[$matchedName]->getId());
				$category->setMatchSource('fuzzy');
				$category->setConfidence(self::FUZZY_CONFIDENCE);
			}
		}
	}

	/**
	 * @param array<string, ProductEntity> $productByName
	 * @param array<string, ProductEntity> $productByDisplayName
	 * @param array<string, string> $productByAlias alias name => product id
	 * @param array<string, ProductEntity> $productByBarcode
	 * @param array<string, ProductEntity> $productById
	 * @param array<string, CategoryEntity> $categoryByName
	 * @param array<string, CategoryEntity> $categoryByDisplayName
	 */
	private function suggestItem(
		PurchaseImportItemEntity $item,
		array $productByName,
		array $productByDisplayName,
		array $productByAlias,
		array $productByBarcode,
		array $productById,
		array $categoryByName,
		array $categoryByDisplayName,
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

		if ($product === null) {
			$rawName = (string)$item->getRawName();
			$matchedName = $this->receiptMatcher->findFuzzyName($rawName, array_keys($productByDisplayName));
			if ($matchedName !== null) {
				$product = $productByDisplayName[$matchedName];
				$source = 'fuzzy';
			}
		}

		if ($product !== null) {
			$item->setSuggestedProductId($product->getId());
			$item->setMatchSource($source);
			$item->setConfidence($source === 'fuzzy' ? self::FUZZY_CONFIDENCE : 1.0);
		} else {
			$item->setSuggestedProductName(trim((string)$item->getRawName()));
		}

		$hint = trim((string)$item->getCategoryHint());
		$hintKey = $this->normalize($hint);
		$category = $hintKey === '' ? null : ($categoryByName[$hintKey] ?? null);
		if ($category === null && $hint !== '') {
			$matchedName = $this->receiptMatcher->findFuzzyName($hint, array_keys($categoryByDisplayName));
			if ($matchedName !== null) {
				$category = $categoryByDisplayName[$matchedName];
			}
		}
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
		return $this->receiptMatcher->normalize($raw);
	}
}
