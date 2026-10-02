<?php

declare(strict_types=1);

namespace Service;

use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\ListShareMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Entity\ListShareEntity;
use OCA\ByeByeMoneyList\Service\Sharing\ListAccessService;
use PHPUnit\Framework\TestCase;

final class ListAccessServiceTest extends TestCase {
	private ListMapper $listMapper;
	private ListShareMapper $shareMapper;
	private ListAccessService $service;

	protected function setUp(): void {
		$this->listMapper = $this->createMock(ListMapper::class);
		$this->shareMapper = $this->createMock(ListShareMapper::class);
		$this->service = new ListAccessService($this->listMapper, $this->shareMapper);
	}

	private function makeList(string $id, string $owner): ListEntity {
		$list = new ListEntity();
		$list->setId($id);
		$list->setOwner($owner);
		$list->setName('Groceries');
		$list->setStatus('new');
		return $list;
	}

	private function makeShare(string $mode, string $status = ListShareEntity::STATUS_ACTIVE, string $owner = 'alice'): ListShareEntity {
		$share = new ListShareEntity();
		$share->setId('share-1');
		$share->setListId('list-1');
		$share->setOwner($owner);
		$share->setSharedWith('bob');
		$share->setMode($mode);
		$share->setStatus($status);
		return $share;
	}

	public function testIsOwnerTrueForOwnedList(): void {
		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('list-1', 'alice')
			->willReturn($this->makeList('list-1', 'alice'));

		$this->assertTrue($this->service->isOwner('list-1', 'alice'));
	}

	public function testIsOwnerFalseWhenNotOwned(): void {
		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('list-1', 'bob')
			->willReturn(null);

		$this->assertFalse($this->service->isOwner('list-1', 'bob'));
	}

	public function testFindReadableReturnsOwnedListWithoutShareLookup(): void {
		$list = $this->makeList('list-1', 'alice');
		$this->listMapper->expects($this->once())->method('findById')->with('list-1')->willReturn($list);
		$this->shareMapper->expects($this->never())->method('findActiveByListAndUser');

		$this->assertSame($list, $this->service->findReadable('list-1', 'alice'));
	}

	public function testFindReadableReturnsSharedList(): void {
		$this->listMapper->expects($this->once())->method('findById')->with('list-1')->willReturn($this->makeList('list-1', 'alice'));
		$this->shareMapper->expects($this->once())
			->method('findActiveByListAndUser')
			->with('list-1', 'bob')
			->willReturn($this->makeShare(ListShareEntity::MODE_READONLY));

		$this->assertNotNull($this->service->findReadable('list-1', 'bob'));
	}

	public function testFindReadableReturnsNullWhenNotShared(): void {
		$this->listMapper->method('findById')->willReturn($this->makeList('list-1', 'alice'));
		$this->shareMapper->method('findActiveByListAndUser')->willReturn(null);

		$this->assertNull($this->service->findReadable('list-1', 'bob'));
	}

	public function testFindReadableReturnsNullForMissingList(): void {
		$this->listMapper->expects($this->once())->method('findById')->with('missing')->willReturn(null);

		$this->assertNull($this->service->findReadable('missing', 'alice'));
	}

	public function testFindWritableReturnsReadWriteSharedList(): void {
		$this->listMapper->method('findById')->willReturn($this->makeList('list-1', 'alice'));
		$this->shareMapper->method('findActiveByListAndUser')->willReturn($this->makeShare(ListShareEntity::MODE_READWRITE));

		$this->assertNotNull($this->service->findWritable('list-1', 'bob'));
	}

	public function testFindWritableRejectsReadOnlySharedList(): void {
		$this->listMapper->method('findById')->willReturn($this->makeList('list-1', 'alice'));
		$this->shareMapper->method('findActiveByListAndUser')->willReturn($this->makeShare(ListShareEntity::MODE_READONLY));

		$this->assertNull($this->service->findWritable('list-1', 'bob'));
	}

	public function testListIdsForMergesOwnedAndShared(): void {
		$this->listMapper->expects($this->once())
			->method('findAllByOwner')
			->with('bob')
			->willReturn([$this->makeList('own-1', 'bob')]);
		$this->shareMapper->expects($this->once())
			->method('findActiveListIdsByRecipient')
			->with('bob')
			->willReturn(['shared-1']);

		$this->assertSame(['own-1', 'shared-1'], $this->service->listIdsFor('bob'));
	}

	public function testVisibleCatalogOwnersIncludesSelfAndActiveOwners(): void {
		$active = $this->makeShare(ListShareEntity::MODE_READONLY, ListShareEntity::STATUS_ACTIVE, 'alice');
		$revoked = $this->makeShare(ListShareEntity::MODE_READONLY, ListShareEntity::STATUS_REVOKED, 'carol');
		$this->shareMapper->expects($this->once())
			->method('findByRecipient')
			->with('bob')
			->willReturn([$active, $revoked]);

		$this->assertSame(['bob', 'alice'], $this->service->visibleCatalogOwners('bob'));
	}
}
