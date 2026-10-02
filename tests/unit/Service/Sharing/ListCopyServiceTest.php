<?php

declare(strict_types=1);

namespace Service;

use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Entity\ListItemEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Service\Sharing\ListAccessService;
use OCA\ByeByeMoneyList\Service\Sharing\ListCopyService;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

final class ListCopyServiceTest extends TestCase {
	private ListMapper $listMapper;
	private ListItemMapper $itemMapper;
	private CategoryMapper $categoryMapper;
	private ProductMapper $productMapper;
	private StoreMapper $storeMapper;
	private ListAccessService $listAccess;
	private IDBConnection $db;
	private ListCopyService $service;

	protected function setUp(): void {
		$this->listMapper = $this->createMock(ListMapper::class);
		$this->itemMapper = $this->createMock(ListItemMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->productMapper = $this->createMock(ProductMapper::class);
		$this->storeMapper = $this->createMock(StoreMapper::class);
		$this->listAccess = $this->createMock(ListAccessService::class);
		$this->db = $this->createMock(IDBConnection::class);

		$this->service = new ListCopyService(
			$this->listMapper,
			$this->itemMapper,
			$this->categoryMapper,
			$this->productMapper,
			$this->storeMapper,
			$this->listAccess,
			$this->db,
		);
	}

	private function sourceList(): ListEntity {
		$list = new ListEntity();
		$list->setId('source-list');
		$list->setOwner('alice');
		$list->setName('Groceries');
		$list->setStatus('finished');
		$list->setIsFinished(true);
		$list->setCategoryId('cat-a');
		return $list;
	}

	public function testCopyReturnsNullForUnreadableList(): void {
		$this->listAccess->expects($this->once())->method('findReadable')->willReturn(null);
		$this->db->expects($this->never())->method('beginTransaction');

		$this->assertNull($this->service->copy('source-list', 'bob'));
	}

	public function testCopyCreatesListItemsAndMapsCatalogByName(): void {
		$this->listAccess->method('findReadable')->willReturn($this->sourceList());
		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$this->listMapper->method('findCategoryIdsByListIds')->willReturn(['source-list' => ['cat-a']]);

		$sourceCategory = new CategoryEntity();
		$sourceCategory->setId('cat-a');
		$sourceCategory->setOwner('alice');
		$sourceCategory->setName('Dairy');
		$sourceCategory->setColor('#ffffff');
		$this->categoryMapper->method('findByIdAndOwner')->willReturn($sourceCategory);
		$this->categoryMapper->method('findByNameAndOwner')->willReturn(null);
		$this->categoryMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (CategoryEntity $category): CategoryEntity {
				$category->setId('own-cat');
				return $category;
			});

		$this->listMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ListEntity $list): ListEntity {
				$list->setId('new-list');
				return $list;
			});
		$this->listMapper->expects($this->once())
			->method('replaceCategoriesByListId')
			->with('new-list', ['own-cat']);

		$sourceItem = new ListItemEntity();
		$sourceItem->setId('item-1');
		$sourceItem->setOwner('alice');
		$sourceItem->setListId('source-list');
		$sourceItem->setProductId('p1');
		$sourceItem->setPrice(2.5);
		$sourceItem->setQuantity(2.0);
		$sourceItem->setIsChecked(true);
		$this->itemMapper->method('findByListId')->willReturn([$sourceItem]);

		$sourceProduct = new ProductEntity();
		$sourceProduct->setId('p1');
		$sourceProduct->setOwner('alice');
		$sourceProduct->setName('Milk');
		$sourceProduct->setCategoryId('cat-a');
		$this->productMapper->method('findByIdAndOwner')->willReturn($sourceProduct);
		$this->productMapper->method('findByNameAndOwner')->willReturn(null);
		$this->productMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ProductEntity $product): ProductEntity {
				$product->setId('own-p1');
				return $product;
			});

		$insertedItem = null;
		$this->itemMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ListItemEntity $item) use (&$insertedItem): ListItemEntity {
				$insertedItem = $item;
				return $item;
			});

		$result = $this->service->copy('source-list', 'bob');

		$this->assertNotNull($result);
		$this->assertSame('bob', $result->getOwner());
		$this->assertSame('Groceries', $result->getName());
		$this->assertSame('own-cat', $result->getCategoryId());
		$this->assertFalse($result->getIsFinished());
		$this->assertSame('new', $result->getStatus());

		$this->assertInstanceOf(ListItemEntity::class, $insertedItem);
		$this->assertSame('bob', $insertedItem->getOwner());
		$this->assertSame('new-list', $insertedItem->getListId());
		$this->assertSame('own-p1', $insertedItem->getProductId());
		$this->assertSame(2.5, $insertedItem->getPrice());
		$this->assertFalse($insertedItem->getIsChecked());
	}

	public function testCopyReusesExistingCatalogRowsByName(): void {
		$this->listAccess->method('findReadable')->willReturn($this->sourceList());
		$this->db->method('beginTransaction');
		$this->db->method('commit');

		$this->listMapper->method('findCategoryIdsByListIds')->willReturn(['source-list' => ['cat-a']]);

		$sourceCategory = new CategoryEntity();
		$sourceCategory->setId('cat-a');
		$sourceCategory->setOwner('alice');
		$sourceCategory->setName('Dairy');
		$this->categoryMapper->method('findByIdAndOwner')->willReturn($sourceCategory);

		$ownCategory = new CategoryEntity();
		$ownCategory->setId('existing-cat');
		$ownCategory->setOwner('bob');
		$ownCategory->setName('Dairy');
		$this->categoryMapper->method('findByNameAndOwner')->willReturn($ownCategory);
		$this->categoryMapper->expects($this->never())->method('insert');

		$this->listMapper->method('insert')->willReturnCallback(function (ListEntity $list): ListEntity {
			$list->setId('new-list');
			return $list;
		});
		$this->listMapper->expects($this->once())
			->method('replaceCategoriesByListId')
			->with('new-list', ['existing-cat']);

		$this->itemMapper->method('findByListId')->willReturn([]);

		$result = $this->service->copy('source-list', 'bob');

		$this->assertNotNull($result);
		$this->assertSame('existing-cat', $result->getCategoryId());
	}
}
