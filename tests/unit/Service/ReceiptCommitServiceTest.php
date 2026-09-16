<?php

declare(strict_types=1);

namespace Service;

use DateTime;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\ProductPriceMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Entity\ListItemEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Service\Receipt\ReceiptCommitService;
use OCA\ByeByeMoneyList\Service\Receipt\ReceiptProductMatcher;
use OCA\ByeByeMoneyList\Service\ReceiptPictureService;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ReceiptCommitServiceTest extends TestCase {
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
	private ReceiptCommitService $service;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
		$this->listMapper = $this->createMock(ListMapper::class);
		$this->listItemMapper = $this->createMock(ListItemMapper::class);
		$this->storeMapper = $this->createMock(StoreMapper::class);
		$this->productMapper = $this->createMock(ProductMapper::class);
		$this->productAliasMapper = $this->createMock(ProductAliasMapper::class);
		$this->productPriceMapper = $this->createMock(ProductPriceMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->receiptPictureService = $this->createMock(ReceiptPictureService::class);
		$this->matcher = $this->createMock(ReceiptProductMatcher::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->service = new ReceiptCommitService(
			$this->db,
			$this->listMapper,
			$this->listItemMapper,
			$this->storeMapper,
			$this->productMapper,
			$this->productAliasMapper,
			$this->productPriceMapper,
			$this->categoryMapper,
			$this->receiptPictureService,
			$this->matcher,
			$logger,
		);
	}

	private function product(string $id, string $name): ProductEntity {
		$product = new ProductEntity();
		$product->setId($id);
		$product->setOwner('alice');
		$product->setName($name);
		$product->setStatus('reviewed');
		return $product;
	}

	/**
	 * @param list<array{productId?: ?string, name: string, quantity: float, price: float, discount?: ?float, isCoupon?: bool, categoryId?: ?string, categoryName?: ?string}> $items
	 * @param list<string> $categoryIds
	 */
	private function commit(array $items, array $categoryIds = []): ListEntity {
		return $this->service->commit(
			'alice',
			'Aldi 14.09.2026',
			'Aldi',
			null,
			3.0,
			new DateTime('2026-09-14T10:00:00+00:00'),
			$items,
			false,
			null,
			null,
			$categoryIds,
		);
	}

	public function testCommitCreatesFinishedListWithMatchedProductAndPrice(): void {
		$milk = $this->product('33333333-4444-4555-8666-777777777777', 'Milk');

		$this->storeMapper->expects($this->once())->method('findAllByOwner')->willReturn([]);
		$this->productMapper->expects($this->once())->method('findAllIncludingSpecialByOwner')->willReturn([$milk]);
		$this->productAliasMapper->method('findByProductIds')->willReturn([]);

		$this->matcher->expects($this->once())
			->method('match')
			->with('Milk', $this->isType('array'), $this->isType('array'))
			->willReturn($milk);

		$list = new ListEntity();
		$this->listMapper->expects($this->once())
			->method('insert')
			->with($this->isInstanceOf(ListEntity::class))
			->willReturnCallback(function (ListEntity $entity): ListEntity {
				$entity->setId('99999999-aaaa-4bbb-8ccc-dddddddddddd');
				return $entity;
			});

		$this->listItemMapper->expects($this->once())
			->method('insert')
			->with($this->isInstanceOf(ListItemEntity::class));

		$this->productPriceMapper->expects($this->once())
			->method('findByProductAndStore')
			->with('33333333-4444-4555-8666-777777777777', $this->isType('string'), 'alice')
			->willReturn(null);
		$this->productPriceMapper->expects($this->once())
			->method('insert')
			->with($this->isInstanceOf(\OCA\ByeByeMoneyList\Entity\ProductPriceEntity::class));

		$this->listMapper->expects($this->once())
			->method('update')
			->with($this->isInstanceOf(ListEntity::class))
			->willReturnArgument(0);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');

		$result = $this->commit([
			['name' => 'Milk', 'quantity' => 2.0, 'price' => 1.5],
		]);

		$this->assertSame('finished', $result->getStatus());
		$this->assertTrue($result->getIsFinished());
		$this->assertSame(3.0, $result->getFinalTotal());
	}

	public function testCommitReusesProductCreatedEarlierInTheSameCommit(): void {
		$this->storeMapper->method('findAllByOwner')->willReturn([]);
		$this->productMapper->method('findAllIncludingSpecialByOwner')->willReturn([]);
		$this->productAliasMapper->method('findByProductIds')->willReturn([]);

		// Simulate the real matcher's exact-name lookup against the growing list.
		$this->matcher->method('match')->willReturnCallback(
			static function (string $name, array $products, array $aliasMap): ?ProductEntity {
				foreach ($products as $product) {
					if (mb_strtolower((string)$product->getName()) === mb_strtolower($name)) {
						return $product;
					}
				}
				return null;
			}
		);

		$this->productMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ProductEntity $entity): ProductEntity {
				$entity->setId('33333333-4444-4555-8666-777777777777');
				return $entity;
			});
		$this->productAliasMapper->method('insert')->willReturnArgument(0);

		$this->listMapper->method('insert')
			->willReturnCallback(function (ListEntity $entity): ListEntity {
				$entity->setId('99999999-aaaa-4bbb-8ccc-dddddddddddd');
				return $entity;
			});
		$this->listMapper->method('update')->willReturnArgument(0);
		$this->listItemMapper->expects($this->exactly(2))->method('insert');
		$this->productPriceMapper->method('findByProductAndStore')->willReturn(null);
		$this->productPriceMapper->method('insert');

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');

		$this->commit([
			['name' => 'Milk', 'quantity' => 1.0, 'price' => 1.0],
			['name' => 'milk', 'quantity' => 1.0, 'price' => 2.0],
		]);
	}

	public function testCommitAppliesProvidedCategoryIdsToTheList(): void {
		$this->storeMapper->method('findAllByOwner')->willReturn([]);
		$this->productMapper->method('findAllIncludingSpecialByOwner')->willReturn([]);
		$this->productAliasMapper->method('findByProductIds')->willReturn([]);
		$this->matcher->method('match')->willReturn(null);

		$this->productMapper->method('insert')
			->willReturnCallback(function (ProductEntity $entity): ProductEntity {
				$entity->setId('33333333-4444-4555-8666-777777777777');
				return $entity;
			});
		$this->productAliasMapper->method('insert')->willReturnArgument(0);

		$category = new CategoryEntity();
		$category->setId('cat-1');
		$category->setOwner('alice');
		$category->setName('Groceries');
		$this->categoryMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('cat-1', 'alice')
			->willReturn($category);

		$this->listMapper->method('insert')
			->willReturnCallback(function (ListEntity $entity): ListEntity {
				$entity->setId('99999999-aaaa-4bbb-8ccc-dddddddddddd');
				return $entity;
			});
		$this->listMapper->expects($this->once())
			->method('replaceCategoriesByListId')
			->with('99999999-aaaa-4bbb-8ccc-dddddddddddd', ['cat-1']);
		$this->listMapper->method('update')->willReturnArgument(0);
		$this->listItemMapper->method('insert');
		$this->productPriceMapper->method('findByProductAndStore')->willReturn(null);
		$this->productPriceMapper->method('insert');

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');

		$result = $this->commit([
			['name' => 'Milk', 'quantity' => 1.0, 'price' => 1.0],
		], ['cat-1']);

		$this->assertSame('cat-1', $result->getCategoryId());
	}

	public function testCommitRollsBackWhenInsertingAnItemFails(): void {
		$this->storeMapper->method('findAllByOwner')->willReturn([]);
		$this->productMapper->method('findAllIncludingSpecialByOwner')->willReturn([]);
		$this->productAliasMapper->method('findByProductIds')->willReturn([]);

		// No matching product -> a new product is created
		$this->matcher->method('match')->willReturn(null);
		$this->productMapper->expects($this->once())
			->method('insert')
			->with($this->isInstanceOf(ProductEntity::class))
			->willReturnCallback(function (ProductEntity $entity): ProductEntity {
				$entity->setId('33333333-4444-4555-8666-777777777777');
				return $entity;
			});
		$this->productAliasMapper->method('insert')->willReturnArgument(0);

		$this->listMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ListEntity $entity): ListEntity {
				$entity->setId('99999999-aaaa-4bbb-8ccc-dddddddddddd');
				return $entity;
			});

		$this->listItemMapper->expects($this->once())
			->method('insert')
			->willThrowException(new \RuntimeException('db down'));

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');

		$this->expectException(\RuntimeException::class);

		$this->commit([
			['name' => 'Bread', 'quantity' => 1.0, 'price' => 2.0],
		]);
	}
}
