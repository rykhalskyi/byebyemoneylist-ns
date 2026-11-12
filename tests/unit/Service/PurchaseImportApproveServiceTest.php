<?php

declare(strict_types=1);

namespace Service;

use DateTime;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\PurchaseImportMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportItemEntity;
use OCA\ByeByeMoneyList\Service\Import\PurchaseImportApproveService;
use OCA\ByeByeMoneyList\Service\Import\PurchaseImportDuplicateException;
use OCA\ByeByeMoneyList\Service\ToBuy\ToBuyAutoMatchService;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PurchaseImportApproveServiceTest extends TestCase {
	private PurchaseImportApproveService $service;
	private ListMapper $listMapper;
	private ListItemMapper $listItemMapper;
	private ProductMapper $productMapper;
	private PurchaseImportMapper $importMapper;

	protected function setUp(): void {
		$db = $this->createMock(IDBConnection::class);
		$this->listMapper = $this->createMock(ListMapper::class);
		$this->listItemMapper = $this->createMock(ListItemMapper::class);
		$storeMapper = $this->createMock(StoreMapper::class);
		$this->productMapper = $this->createMock(ProductMapper::class);
		$aliasMapper = $this->createMock(ProductAliasMapper::class);
		$categoryMapper = $this->createMock(CategoryMapper::class);
		$this->importMapper = $this->createMock(PurchaseImportMapper::class);
		$logger = $this->createMock(LoggerInterface::class);
		$toBuyAutoMatch = $this->createMock(ToBuyAutoMatchService::class);

		$this->service = new PurchaseImportApproveService(
			$db,
			$this->listMapper,
			$this->listItemMapper,
			$storeMapper,
			$this->productMapper,
			$aliasMapper,
			$categoryMapper,
			$this->importMapper,
			$logger,
			$toBuyAutoMatch,
		);
	}

	private function import(): PurchaseImportEntity {
		$import = new PurchaseImportEntity();
		$import->setId('import-1');
		$import->setOwner('alice');
		$import->setClientListId('b1f0c7d2-3b1e-4a67-9c55-0e1d2f3a4b5c');
		$import->setName('Purchase');
		$import->setStatus(PurchaseImportEntity::STATUS_IN_REVIEW);
		$import->setStoreAction(PurchaseImportEntity::ACTION_SKIP);
		$import->setFinalTotal(10.0);
		$import->setListCreatedAt(new DateTime('2026-11-09T18:30:00Z'));
		$import->setPurchaseDate(new DateTime('2026-11-09T19:05:00Z'));
		return $import;
	}

	private function item(string $id, bool $coupon = false, string $action = PurchaseImportEntity::ACTION_MAP_EXISTING): PurchaseImportItemEntity {
		$item = new PurchaseImportItemEntity();
		$item->setId($id);
		$item->setOwner('alice');
		$item->setRawName('Milk');
		$item->setQuantity(2.0);
		$item->setUnitPrice(1.5);
		$item->setIsCoupon($coupon);
		$item->setAction($action);
		$item->setMappedProductId($coupon ? null : 'product-1');
		$item->setPosition(0);
		return $item;
	}

	public function testApproveCreatesListWithClientIdAndSkipsCoupons(): void {
		$import = $this->import();
		$this->listMapper->method('findById')->willReturn(null);
		$this->listMapper->method('getMaxPosition')->willReturn(5);
		$this->listMapper->expects($this->once())->method('insert')
			->willReturnCallback(static fn (ListEntity $list): ListEntity => $list);

		$product = new ProductEntity();
		$product->setId('product-1');
		$product->setOwner('alice');
		$product->setName('Milk');
		$this->productMapper->method('findByIdAndOwner')->willReturn($product);
		$this->listItemMapper->expects($this->once())->method('insert')->willReturnArgument(0);
		$this->importMapper->expects($this->once())->method('update')->willReturnArgument(0);

		$list = $this->service->approve($import, [
			$this->item('item-1'),
			$this->item('item-2', true),
		], []);

		$this->assertSame('b1f0c7d2-3b1e-4a67-9c55-0e1d2f3a4b5c', $list->getId());
		$this->assertSame(6, $list->getPosition());
		$this->assertSame(10.0, $list->getFinalTotal());
		$this->assertTrue($list->getIsFinished());
		$this->assertSame(PurchaseImportEntity::STATUS_APPROVED, $import->getStatus());
		$this->assertSame($list->getId(), $import->getApprovedListId());
	}

	public function testApproveRejectsExistingListId(): void {
		$existing = new ListEntity();
		$existing->setId('b1f0c7d2-3b1e-4a67-9c55-0e1d2f3a4b5c');
		$this->listMapper->method('findById')->willReturn($existing);

		$this->expectException(PurchaseImportDuplicateException::class);

		$this->service->approve($this->import(), [], []);
	}
}
