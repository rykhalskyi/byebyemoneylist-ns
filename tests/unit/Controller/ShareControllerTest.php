<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\ShareController;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\ListShareMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Entity\ListShareEntity;
use OCA\ByeByeMoneyList\Service\Sharing\ListAccessService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ShareControllerTest extends TestCase {
	private ShareController $controller;
	private ListMapper $listMapper;
	private ListShareMapper $shareMapper;
	private ListAccessService $listAccess;
	private IUserSession $userSession;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->listMapper = $this->createMock(ListMapper::class);
		$this->shareMapper = $this->createMock(ListShareMapper::class);
		$this->listAccess = $this->createMock(ListAccessService::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ShareController(
			$request,
			$this->listMapper,
			$this->shareMapper,
			$this->listAccess,
			$this->userSession,
			$logger,
		);
	}

	private function mockUser(string $uid): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
	}

	private function makeList(string $id = 'list-1', string $owner = 'alice'): ListEntity {
		$list = new ListEntity();
		$list->setId($id);
		$list->setOwner($owner);
		$list->setName('Groceries');
		$list->setStatus('new');
		return $list;
	}

	private function makeShare(
		string $id = 'share-1',
		string $listId = 'list-1',
		string $owner = 'alice',
		string $sharedWith = 'bob',
		string $mode = ListShareEntity::MODE_READONLY,
		string $status = ListShareEntity::STATUS_ACTIVE,
	): ListShareEntity {
		$share = new ListShareEntity();
		$share->setId($id);
		$share->setListId($listId);
		$share->setOwner($owner);
		$share->setSharedWith($sharedWith);
		$share->setMode($mode);
		$share->setStatus($status);
		$share->setCreatedAt(new \DateTime('2026-10-02T10:00:00+00:00'));
		$share->setUpdatedAt(new \DateTime('2026-10-02T10:00:00+00:00'));
		return $share;
	}

	public function testIncomingRequiresLogin(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->incoming();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testIncomingReturnsSharesWithListNames(): void {
		$this->mockUser('bob');
		$this->shareMapper->expects($this->once())
			->method('findByRecipient')
			->with('bob')
			->willReturn([$this->makeShare()]);
		$this->listMapper->expects($this->once())
			->method('findById')
			->with('list-1')
			->willReturn($this->makeList());

		$response = $this->controller->incoming();
		$data = $response->getData();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('Groceries', $data['shares'][0]['listName']);
		$this->assertFalse($data['shares'][0]['revoked']);
	}

	public function testCreateRejectsSelfShare(): void {
		$this->mockUser('alice');
		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('list-1', 'alice')
			->willReturn($this->makeList());
		$this->shareMapper->expects($this->never())->method('insert');

		$response = $this->controller->create('list-1', 'alice');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateRejectsInvalidMode(): void {
		$this->mockUser('alice');
		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('list-1', 'alice')
			->willReturn($this->makeList());
		$this->shareMapper->expects($this->never())->method('insert');

		$response = $this->controller->create('list-1', 'bob', 'writeonly');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateReturnsNotFoundForForeignList(): void {
		$this->mockUser('bob');
		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('list-1', 'bob')
			->willReturn(null);

		$response = $this->controller->create('list-1', 'carol');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testCreateInsertsNewShare(): void {
		$this->mockUser('alice');
		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('list-1', 'alice')
			->willReturn($this->makeList());
		$this->shareMapper->expects($this->once())
			->method('findByListAndUser')
			->with('list-1', 'bob')
			->willReturn(null);
		$this->shareMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function (ListShareEntity $share): bool {
				return $share->getListId() === 'list-1'
					&& $share->getOwner() === 'alice'
					&& $share->getSharedWith() === 'bob'
					&& $share->getMode() === ListShareEntity::MODE_READWRITE
					&& $share->getStatus() === ListShareEntity::STATUS_ACTIVE;
			}))
			->willReturnArgument(0);

		$response = $this->controller->create('list-1', 'bob', ListShareEntity::MODE_READWRITE);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$data = $response->getData();
		$this->assertSame('bob', $data['share']['sharedWith']);
		$this->assertSame(ListShareEntity::MODE_READWRITE, $data['share']['mode']);
	}

	public function testCreateUpdatesExistingRevokedShare(): void {
		$this->mockUser('alice');
		$this->listMapper->method('findByIdAndOwner')->willReturn($this->makeList());
		$existing = $this->makeShare(status: ListShareEntity::STATUS_REVOKED);
		$this->shareMapper->expects($this->once())
			->method('findByListAndUser')
			->with('list-1', 'bob')
			->willReturn($existing);
		$this->shareMapper->expects($this->once())
			->method('update')
			->willReturnArgument(0);

		$response = $this->controller->create('list-1', 'bob', ListShareEntity::MODE_READWRITE);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(ListShareEntity::STATUS_ACTIVE, $existing->getStatus());
		$this->assertSame(ListShareEntity::MODE_READWRITE, $existing->getMode());
	}

	public function testDestroyRevokesShare(): void {
		$this->mockUser('alice');
		$this->listMapper->method('findByIdAndOwner')->willReturn($this->makeList());
		$share = $this->makeShare();
		$this->shareMapper->expects($this->once())
			->method('findById')
			->with('share-1')
			->willReturn($share);
		$this->shareMapper->expects($this->once())
			->method('update')
			->willReturnArgument(0);

		$response = $this->controller->destroy('list-1', 'share-1');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(ListShareEntity::STATUS_REVOKED, $share->getStatus());
		$this->assertTrue($response->getData()['share']['revoked']);
	}

	public function testDestroyRejectsShareFromAnotherOwner(): void {
		$this->mockUser('alice');
		$this->listMapper->method('findByIdAndOwner')->willReturn($this->makeList());
		$this->shareMapper->expects($this->once())
			->method('findById')
			->willReturn($this->makeShare(owner: 'carol'));
		$this->shareMapper->expects($this->never())->method('update');

		$response = $this->controller->destroy('list-1', 'share-1');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}
}
