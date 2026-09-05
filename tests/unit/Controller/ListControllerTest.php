<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\ListController;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCP\AppFramework\Http;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ListControllerTest extends TestCase {
	private ListController $controller;
	private ListMapper $mapper;
	private ListItemMapper $itemMapper;
	private IDBConnection $db;
	private IUserSession $userSession;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->mapper = $this->createMock(ListMapper::class);
		$this->itemMapper = $this->createMock(ListItemMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ListController(
			$request,
			$this->mapper,
			$this->itemMapper,
			$this->db,
			$this->userSession,
			$logger,
		);
	}

	private function mockUser(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
		return $user;
	}

	private function makeList(string $id, string $name): ListEntity {
		$list = new ListEntity();
		$list->setId($id);
		$list->setOwner('alice');
		$list->setName($name);
		$list->setStatus('new');
		return $list;
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

		$this->itemMapper->expects($this->once())
			->method('sumCheckedByListIds')
			->with(['11111111-2222-4333-8444-555555555555'])
			->willReturn(['11111111-2222-4333-8444-555555555555' => 12.5]);

		$this->mapper->expects($this->once())
			->method('findCategoryIdsByListIds')
			->with(['11111111-2222-4333-8444-555555555555'])
			->willReturn(['11111111-2222-4333-8444-555555555555' => ['cat-1', 'cat-2']]);

		$response = $this->controller->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$lists = $response->getData()['lists'];
		$this->assertCount(1, $lists);
		$this->assertSame('Groceries', $lists[0]['name']);
		$this->assertNull($lists[0]['storeId']);
		$this->assertSame('cat-1', $lists[0]['categoryId']);
		$this->assertSame(['cat-1', 'cat-2'], $lists[0]['categoryIds']);
		$this->assertSame(12.5, $lists[0]['totalPrice']);
		$this->assertSame('2026-08-26T10:00:00+00:00', $lists[0]['createdAt']);
		$this->assertSame('2026-08-26T10:00:00+00:00', $lists[0]['createDate']);
		$this->assertNull($lists[0]['updatedAt']);
		$this->assertNull($lists[0]['purchaseDate']);
		$this->assertSame(0, $lists[0]['position']);
		$this->assertFalse($lists[0]['isFinished']);
		$this->assertFalse($lists[0]['isSubscription']);
		$this->assertFalse($lists[0]['isIncome']);
		$this->assertFalse($lists[0]['isRecurring']);
		$this->assertSame('MONTH', $lists[0]['recurringPeriod']);
		$this->assertTrue($lists[0]['isForwardEmpty']);
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
		$this->assertSame([], $list['categoryIds']);
		$this->assertSame('new', $list['status']);
		$this->assertFalse($list['isFinished']);
		$this->assertSame(0, $list['position']);
		$this->assertFalse($list['isSubscription']);
		$this->assertFalse($list['isIncome']);
		$this->assertFalse($list['isRecurring']);
		$this->assertSame('MONTH', $list['recurringPeriod']);
		$this->assertTrue($list['isForwardEmpty']);
		$this->assertNotNull($list['createdAt']);
		$this->assertNotNull($list['updatedAt']);
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			$list['id'],
		);
	}

	public function testCreateWithFlags(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnArgument(0);

		$response = $this->controller->create(
			'Rent',
			isRecurring: true,
			recurringPeriod: 'MONTH',
			isForwardEmpty: false,
			isSubscription: true,
			isIncome: false,
			isFinished: true,
		);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$list = $response->getData()['list'];
		$this->assertTrue($list['isFinished']);
		$this->assertSame('finished', $list['status']);
		$this->assertTrue($list['isRecurring']);
		$this->assertSame('MONTH', $list['recurringPeriod']);
		$this->assertFalse($list['isForwardEmpty']);
		$this->assertTrue($list['isSubscription']);
		$this->assertFalse($list['isIncome']);
	}

	public function testCreateWithStoreAndCategory(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnArgument(0);

		$this->mapper->expects($this->once())
			->method('replaceCategoriesByListId')
			->with($this->isType('string'), ['cat-1']);

		$response = $this->controller->create('Groceries', 'store-1', 'cat-1');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$list = $response->getData()['list'];
		$this->assertSame('store-1', $list['storeId']);
		$this->assertSame('cat-1', $list['categoryId']);
		$this->assertSame(['cat-1'], $list['categoryIds']);
	}

	public function testCreateWithMultipleCategoriesAndDates(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnArgument(0);

		$this->mapper->expects($this->once())
			->method('replaceCategoriesByListId')
			->with($this->isType('string'), ['cat-1', 'cat-2']);

		$response = $this->controller->create(
			'Groceries',
			categoryIds: ['cat-1', 'cat-2', 'cat-1'],
			position: 3,
			purchaseDate: '2026-09-01T12:00:00+00:00',
			finalTotal: 12.34,
			createDate: '2026-08-20T10:00:00+00:00',
		);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$list = $response->getData()['list'];
		$this->assertSame('cat-1', $list['categoryId']);
		$this->assertSame(['cat-1', 'cat-2'], $list['categoryIds']);
		$this->assertSame(3, $list['position']);
		$this->assertSame('2026-09-01T12:00:00+00:00', $list['purchaseDate']);
		$this->assertSame(12.34, $list['finalTotal']);
		$this->assertSame('2026-08-20T10:00:00+00:00', $list['createDate']);
	}

	public function testCreateRejectsInvalidPurchaseDate(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->never())->method('insert');

		$response = $this->controller->create('Groceries', purchaseDate: 'not-a-date');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateRejectsNegativeFinalTotal(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->never())->method('insert');

		$response = $this->controller->create('Groceries', finalTotal: -5.0);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
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

	public function testUpdateAppliesFullState(): void {
		$this->mockUser('alice');

		$list = $this->makeList('11111111-2222-4333-8444-555555555555', 'Old name');
		$list->setStatus('new');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($list);

		$this->mapper->expects($this->once())
			->method('update')
			->willReturnArgument(0);

		$this->mapper->expects($this->once())
			->method('replaceCategoriesByListId')
			->with('11111111-2222-4333-8444-555555555555', ['cat-9']);

		$response = $this->controller->update(
			'11111111-2222-4333-8444-555555555555',
			'Weekly groceries',
			storeId: null,
			categoryIds: ['cat-9'],
			position: 4,
			purchaseDate: '2026-09-02T10:00:00+00:00',
			finalTotal: 55.0,
			isFinished: true,
			isRecurring: false,
		);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$serialized = $response->getData()['list'];
		$this->assertSame('Weekly groceries', $serialized['name']);
		$this->assertNull($serialized['storeId']);
		$this->assertSame('cat-9', $serialized['categoryId']);
		$this->assertSame(['cat-9'], $serialized['categoryIds']);
		$this->assertSame(4, $serialized['position']);
		$this->assertSame('2026-09-02T10:00:00+00:00', $serialized['purchaseDate']);
		$this->assertSame(55.0, $serialized['finalTotal']);
		$this->assertTrue($serialized['isFinished']);
		$this->assertSame('finished', $serialized['status']);
		$this->assertFalse($serialized['isRecurring']);
		$this->assertNotNull($serialized['updatedAt']);
	}

	public function testUpdateReturnsNotFoundForOtherUsersList(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('missing', 'alice')
			->willReturn(null);

		$this->mapper->expects($this->never())->method('update');

		$response = $this->controller->update('missing', 'Groceries');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUpdateRejectsEmptyName(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($this->makeList('11111111-2222-4333-8444-555555555555', 'Groceries'));

		$this->mapper->expects($this->never())->method('update');

		$response = $this->controller->update('11111111-2222-4333-8444-555555555555', '   ');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testUpdateRejectsInvalidPurchaseDate(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($this->makeList('11111111-2222-4333-8444-555555555555', 'Groceries'));

		$this->mapper->expects($this->never())->method('update');

		$response = $this->controller->update('11111111-2222-4333-8444-555555555555', 'Groceries', purchaseDate: 'nope');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testDestroyDeletesListWithItemsAndCategories(): void {
		$this->mockUser('alice');

		$list = $this->makeList('11111111-2222-4333-8444-555555555555', 'Groceries');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($list);

		$this->itemMapper->expects($this->once())
			->method('deleteByListId')
			->with('11111111-2222-4333-8444-555555555555');

		$this->mapper->expects($this->once())
			->method('deleteCategoriesByListId')
			->with('11111111-2222-4333-8444-555555555555');

		$this->mapper->expects($this->once())
			->method('delete')
			->with($list);

		$response = $this->controller->destroy('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testDestroyReturnsNotFoundForMissingList(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('missing', 'alice')
			->willReturn(null);

		$response = $this->controller->destroy('missing');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}
}
