<?php

declare(strict_types=1);

namespace Service;

use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Entity\ProductAliasEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportCategoryEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportItemEntity;
use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCA\ByeByeMoneyList\Service\Import\PurchaseImportMatcher;
use PHPUnit\Framework\TestCase;

final class PurchaseImportMatcherTest extends TestCase {
	private PurchaseImportMatcher $matcher;

	protected function setUp(): void {
		$storeMapper = $this->createMock(StoreMapper::class);
		$categoryMapper = $this->createMock(CategoryMapper::class);
		$productMapper = $this->createMock(ProductMapper::class);
		$aliasMapper = $this->createMock(ProductAliasMapper::class);

		$store = new StoreEntity();
		$store->setId('store-1');
		$store->setOwner('alice');
		$store->setName('Lidl');
		$storeMapper->method('findAllByOwner')->willReturn([$store]);

		$groceries = new CategoryEntity();
		$groceries->setId('cat-groceries');
		$groceries->setOwner('alice');
		$groceries->setName('Groceries');

		$dairy = new CategoryEntity();
		$dairy->setId('cat-dairy');
		$dairy->setOwner('alice');
		$dairy->setName('Dairy');
		$categoryMapper->method('findAllByOwner')->willReturn([$groceries, $dairy]);

		$milk = new ProductEntity();
		$milk->setId('product-milk');
		$milk->setOwner('alice');
		$milk->setName('Milk');
		$milk->setBarcode('4001234567890');
		$milk->setCategoryId('cat-dairy');
		$milk->setStatus('reviewed');
		$productMapper->method('findAllIncludingSpecialByOwner')->willReturn([$milk]);

		$alias = new ProductAliasEntity();
		$alias->setId('alias-1');
		$alias->setOwner('alice');
		$alias->setProductId('product-milk');
		$alias->setAliasName('Milch');
		$aliasMapper->method('findByProductIds')->willReturn([$alias]);

		$this->matcher = new PurchaseImportMatcher($storeMapper, $categoryMapper, $productMapper, $aliasMapper);
	}

	private function import(): PurchaseImportEntity {
		$import = new PurchaseImportEntity();
		$import->setOwner('alice');
		$import->setStoreName('Lidl');
		return $import;
	}

	private function item(string $name, string $barcode = '', string $hint = ''): PurchaseImportItemEntity {
		$item = new PurchaseImportItemEntity();
		$item->setRawName($name);
		$item->setBarcode($barcode === '' ? null : $barcode);
		$item->setCategoryHint($hint === '' ? null : $hint);
		return $item;
	}

	private function category(string $name): PurchaseImportCategoryEntity {
		$category = new PurchaseImportCategoryEntity();
		$category->setRawName($name);
		return $category;
	}

	public function testSuggestsExactStoreCategoryProductAliasAndBarcode(): void {
		$import = $this->import();
		$category = $this->category('Groceries');
		$exact = $this->item('Milk', '', 'Dairy');
		$alias = $this->item('Milch');
		$barcode = $this->item('Unknown label', '4001234567890');
		$unmatched = $this->item('Bread');

		$this->matcher->suggestForImport($import, [$exact, $alias, $barcode, $unmatched], [$category], 'alice');

		$this->assertSame('store-1', $import->getSuggestedStoreId());
		$this->assertSame('exact', $import->getStoreMatchSource());
		$this->assertSame(1.0, $import->getStoreConfidence());

		$this->assertSame('cat-groceries', $category->getSuggestedCategoryId());
		$this->assertSame('exact', $category->getMatchSource());

		$this->assertSame('product-milk', $exact->getSuggestedProductId());
		$this->assertSame('exact', $exact->getMatchSource());
		$this->assertSame('cat-dairy', $exact->getSuggestedCategoryId());

		$this->assertSame('product-milk', $alias->getSuggestedProductId());
		$this->assertSame('alias', $alias->getMatchSource());

		$this->assertSame('product-milk', $barcode->getSuggestedProductId());
		$this->assertSame('barcode', $barcode->getMatchSource());

		$this->assertNull($unmatched->getSuggestedProductId());
		$this->assertSame('Bread', $unmatched->getSuggestedProductName());
	}

	public function testSuggestsNewStoreNameWhenNoMatch(): void {
		$import = $this->import();
		$import->setStoreName('Unknown market');

		$this->matcher->suggestForImport($import, [], [], 'alice');

		$this->assertNull($import->getSuggestedStoreId());
		$this->assertSame('Unknown market', $import->getSuggestedStoreName());
	}
}
