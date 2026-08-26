<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\ListController;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ListControllerTest extends TestCase {
	private ListController $controller;
	private ListMapper $mapper;
	private IUserSession $userSession;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->mapper = $this->createMock(ListMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ListController($request, $this->mapper, $this->userSession, $logger);
	}

	private function mockUser(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
		return $user;
	}

	public function testIndexReturnsOnlyCurrentUsersLists(): void {
		$this->mockUser('alice');

		$list = new ListEntity();
		$list->setId('11111111-2222-4333-8444-555555555555');
		$list->setOwner('alice');
		$list->setName('Groceries');
		$list->setStatus('new');
		$list->setCreatedAt(new \DateTime('2026-08-26T10:00:00+00:00'));

		$this->mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([$list]);

		$response = $this->controller->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$lists = $response->getData()['lists'];
		$this->assertCount(1, $lists);
		$this->assertSame('Groceries', $lists[0]['name']);
		$this->assertNull($lists[0]['storeId']);
		$this->assertNull($lists[0]['categoryId']);
		$this->assertSame('2026-08-26T10:00:00+00:00', $lists[0]['createdAt']);
	}

	public function testIndexReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->index();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testCreateReturnsCreatedList(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnArgument(0);

		$response = $this->controller->create('  Weekly groceries  ');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$list = $response->getData()['list'];
		$this->assertSame('Weekly groceries', $list['name']);
		$this->assertNull($list['storeId']);
		$this->assertNull($list['categoryId']);
		$this->assertSame('new', $list['status']);
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			$list['id'],
		);
	}

	public function testCreateWithStoreAndCategory(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnArgument(0);

		$response = $this->controller->create('Groceries', 'store-1', 'cat-1');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$list = $response->getData()['list'];
		$this->assertSame('store-1', $list['storeId']);
		$this->assertSame('cat-1', $list['categoryId']);
	}

	public function testCreateRejectsEmptyName(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->never())->method('insert');

		$response = $this->controller->create('   ');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$this->mapper->expects($this->never())->method('insert');

		$response = $this->controller->create('Groceries');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}
}
