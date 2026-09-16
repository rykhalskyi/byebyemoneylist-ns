<?php

declare(strict_types=1);

namespace Service;

use DateTime;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\ProductPriceMapper;
use OCA\ByeByeMoneyList\Entity\ProductAliasEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Entity\ProductPriceEntity;
use OCA\ByeByeMoneyList\Service\ProductMergeService;
use OCA\ByeByeMoneyList\Service\ProductPictureService;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class ProductMergeServiceTest extends TestCase {
	private IDBConnection $db;
	private ProductMapper $productMapper;
	private ProductAliasMapper $productAliasMapper;
	private ProductPriceMapper $productPriceMapper;
	private ListItemMapper $listItemMapper;
	private ProductPictureService $pictureService;
	private ProductMergeService $service;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
		$this->productMapper = $this->createMock(ProductMapper::class);
		$this->productAliasMapper = $this->createMock(ProductAliasMapper::class);
		$this->productPriceMapper = $this->createMock(ProductPriceMapper::class);
		$this->listItemMapper = $this->createMock(ListItemMapper::class);
		$this->pictureService = $this->createMock(ProductPictureService::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->service = new ProductMergeService(
			$this->db,
			$this->productMapper,
			$this->productAliasMapper,
			$this->productPriceMapper,
			$this->listItemMapper,
			$this->pictureService,
			$logger,
		);
	}

	private function product(string $id, string $name, ?string $picturePath = null): ProductEntity {
		$product = new ProductEntity();
		$product->setId($id);
		$product->setOwner('alice');
		$product->setName($name);
		$product->setStatus('reviewed');
		$product->setIsFavorite(false);
		$product->setIsSubscription(false);
		$product->setIsIncome(false);
		if ($picturePath !== null) {
			$product->setPicturePath($picturePath);
		}
		return $product;
	}

	private function alias(string $productId, string $name, ?string $storeId = null): ProductAliasEntity {
		$alias = new ProductAliasEntity();
		$alias->setId('alias-' . $name);
		$alias->setOwner('alice');
		$alias->setProductId($productId);
		$alias->setAliasName($name);
		if ($storeId !== null) {
			$alias->setStoreId($storeId);
		}
		return $alias;
	}

	public function testMergeUpdatesPrimaryConcatenatesAliasesAndDeletesSecondary(): void {
		$primary = $this->product('A', 'Milk');
		$secondary = $this->product('B', 'Milch');

		$this->productAliasMapper->expects($this->once())
			->method('findByProductIds')
			->with(['A', 'B'], 'alice')
			->willReturn([
				$this->alias('A', 'M', 'store-1'),
				$this->alias('B', 'Milch'),
				$this->alias('B', 'M'),
			]);

		$this->productMapper->expects($this->once())->method('update')->willReturnArgument(0);
		$this->productAliasMapper->expects($this->exactly(2))->method('deleteByProductId');
		$this->listItemMapper->expects($this->once())
			->method('reassignProduct')
			->with('B', 'A', 'alice');
		$this->productPriceMapper->expects($this->once())
			->method('findByProductIdAndOwner')
			->with('B', 'alice')
			->willReturn([]);
		$this->productMapper->expects($this->once())->method('delete')->with($secondary);

		$inserted = [];
		$insertedStores = [];
		$this->productAliasMapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function (ProductAliasEntity $alias) use (&$inserted, &$insertedStores): ProductAliasEntity {
				$inserted[] = $alias->getAliasName();
				$insertedStores[] = $alias->getStoreId();
				return $alias;
			});

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$result = $this->service->merge('alice', $primary, $secondary, 'Milk', 'cat-1', '1234', true, false, false, 'primary');

		$this->assertSame($primary, $result);
		$this->assertSame('Milk', $result->getName());
		$this->assertSame('cat-1', $result->getCategoryId());
		$this->assertSame('1234', $result->getBarcode());
		$this->assertTrue($result->getIsFavorite());
		$this->assertSame('reviewed', $result->getStatus());
		$this->assertSame(['Milch', 'M'], $inserted);
		$this->assertSame([null, 'store-1'], $insertedStores);
	}

	public function testMergeRepointsPricesAndKeepsTheLatestOnCollision(): void {
		$primary = $this->product('A', 'Milk');
		$secondary = $this->product('B', 'Milch');

		$this->productAliasMapper->method('findByProductIds')->willReturn([]);
		$this->productMapper->method('update')->willReturnArgument(0);
		$this->productAliasMapper->method('deleteByProductId');

		$existing = new ProductPriceEntity();
		$existing->setId('price-primary');
		$existing->setOwner('alice');
		$existing->setProductId('A');
		$existing->setStoreId('s1');
		$existing->setValue(1.0);
		$existing->setPriceDate(new DateTime('2026-01-01T00:00:00Z'));

		$newer = new ProductPriceEntity();
		$newer->setId('price-secondary-1');
		$newer->setOwner('alice');
		$newer->setProductId('B');
		$newer->setStoreId('s1');
		$newer->setValue(2.0);
		$newer->setPriceDate(new DateTime('2026-02-01T00:00:00Z'));

		$moved = new ProductPriceEntity();
		$moved->setId('price-secondary-2');
		$moved->setOwner('alice');
		$moved->setProductId('B');
		$moved->setStoreId('s2');
		$moved->setValue(3.0);
		$moved->setPriceDate(new DateTime('2026-01-15T00:00:00Z'));

		$this->productPriceMapper->expects($this->once())
			->method('findByProductIdAndOwner')
			->with('B', 'alice')
			->willReturn([$newer, $moved]);

		$this->productPriceMapper->expects($this->exactly(2))
			->method('findByProductAndStore')
			->willReturnMap([
				['A', 's1', 'alice', $existing],
				['A', 's2', 'alice', null],
			]);

		$this->productPriceMapper->expects($this->exactly(2))->method('update');
		$this->productPriceMapper->expects($this->once())->method('delete')->with($newer);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');

		$this->service->merge('alice', $primary, $secondary, 'Milk', null, null, false, false, false, 'primary');

		$this->assertSame(2.0, $existing->getValue());
		$this->assertSame('2026-02-01', $existing->getPriceDate()?->format('Y-m-d'));
		$this->assertSame('A', $moved->getProductId());
	}

	public function testMergeCopiesChosenPictureAndRemovesTheOthers(): void {
		$primary = $this->product('A', 'Milk', 'product-pictures/alice/A.jpg');
		$secondary = $this->product('B', 'Milch', 'product-pictures/alice/B.png');

		$this->productAliasMapper->method('findByProductIds')->willReturn([]);
		$this->productMapper->expects($this->once())->method('update')->willReturnArgument(0);
		$this->productAliasMapper->method('deleteByProductId');
		$this->productPriceMapper->method('findByProductIdAndOwner')->willReturn([]);

		$this->pictureService->expects($this->once())
			->method('copy')
			->with('alice', 'product-pictures/alice/B.png', 'A')
			->willReturn('product-pictures/alice/A.png');

		$this->pictureService->expects($this->exactly(2))
			->method('delete')
			->with('alice', $this->logicalOr(
				'product-pictures/alice/A.jpg',
				'product-pictures/alice/B.png',
			));

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');

		$result = $this->service->merge('alice', $primary, $secondary, 'Milk', null, null, false, false, false, 'secondary');

		$this->assertSame('product-pictures/alice/A.png', $result->getPicturePath());
	}

	public function testMergeRollsBackWhenUpdateFails(): void {
		$primary = $this->product('A', 'Milk');
		$secondary = $this->product('B', 'Milch');

		$this->productAliasMapper->method('findByProductIds')->willReturn([]);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');

		$this->productMapper->expects($this->once())
			->method('update')
			->willThrowException(new RuntimeException('boom'));

		$this->productMapper->expects($this->never())->method('delete');

		$this->expectException(RuntimeException::class);

		$this->service->merge('alice', $primary, $secondary, 'Milk', null, null, false, false, false, 'primary');
	}
}
