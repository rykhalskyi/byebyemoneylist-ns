<?php

declare(strict_types=1);

namespace Service;

use DateTime;
use OCA\ByeByeMoneyList\Db\CatalogShareMapper;
use OCA\ByeByeMoneyList\Entity\CatalogShareEntity;
use OCA\ByeByeMoneyList\Service\Sharing\CatalogSharingService;
use PHPUnit\Framework\TestCase;

final class CatalogSharingServiceTest extends TestCase {
	private CatalogShareMapper $mapper;
	private CatalogSharingService $service;

	protected function setUp(): void {
		$this->mapper = $this->createMock(CatalogShareMapper::class);
		$this->service = new CatalogSharingService($this->mapper);
	}

	private function makeShare(string $status): CatalogShareEntity {
		$share = new CatalogShareEntity();
		$share->setId('share-1');
		$share->setItemType(CatalogShareEntity::TYPE_PRODUCT);
		$share->setItemId('product-1');
		$share->setOwner('bob');
		$share->setSharedWith('alice');
		$share->setStatus($status);
		return $share;
	}

	public function testPublishInsertsNewGrant(): void {
		$this->mapper->expects($this->once())
			->method('findByItemAndUser')
			->with(CatalogShareEntity::TYPE_PRODUCT, 'product-1', 'alice')
			->willReturn(null);
		$this->mapper->expects($this->once())
			->method('insert')
			->with($this->callback(function (CatalogShareEntity $share): bool {
				return $share->getItemType() === CatalogShareEntity::TYPE_PRODUCT
					&& $share->getItemId() === 'product-1'
					&& $share->getOwner() === 'bob'
					&& $share->getSharedWith() === 'alice'
					&& $share->getStatus() === CatalogShareEntity::STATUS_ACTIVE
					&& $share->getCreatedAt() instanceof DateTime;
			}))
			->willReturnArgument(0);

		$this->service->publish(CatalogShareEntity::TYPE_PRODUCT, 'product-1', 'bob', 'alice');
	}

	public function testPublishReactivatesRevokedGrant(): void {
		$revoked = $this->makeShare(CatalogShareEntity::STATUS_REVOKED);
		$this->mapper->expects($this->once())->method('findByItemAndUser')->willReturn($revoked);
		$this->mapper->expects($this->once())
			->method('update')
			->with($this->callback(
				fn (CatalogShareEntity $share): bool => $share->getStatus() === CatalogShareEntity::STATUS_ACTIVE,
			))
			->willReturnArgument(0);

		$this->service->publish(CatalogShareEntity::TYPE_PRODUCT, 'product-1', 'bob', 'alice');

		$this->assertSame(CatalogShareEntity::STATUS_ACTIVE, $revoked->getStatus());
	}

	public function testPublishKeepsActiveGrantUntouched(): void {
		$active = $this->makeShare(CatalogShareEntity::STATUS_ACTIVE);
		$this->mapper->expects($this->once())->method('findByItemAndUser')->willReturn($active);
		$this->mapper->expects($this->never())->method('update');
		$this->mapper->expects($this->never())->method('insert');

		$this->service->publish(CatalogShareEntity::TYPE_PRODUCT, 'product-1', 'bob', 'alice');
	}

	public function testRevokeItemDelegates(): void {
		$this->mapper->expects($this->once())
			->method('revokeByItem')
			->with(CatalogShareEntity::TYPE_STORE, 'store-1');

		$this->service->revokeItem(CatalogShareEntity::TYPE_STORE, 'store-1');
	}
}
