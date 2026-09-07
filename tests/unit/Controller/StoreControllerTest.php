<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\StoreController;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCP\AppFramework\Http;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class StoreControllerTest extends TestCase {
	private StoreController $controller;
	private StoreMapper $mapper;
	private CategoryMapper $categoryMapper;
	private IUserSession $userSession;
	private IDBConnection $db;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->mapper = $this->createMock(StoreMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new StoreController($request, $this->mapper, $this->categoryMapper, $this->db, $this->userSession, $logger);
	}

	private function mockUser(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
		return $user;
	}

	private function mockQueryBuilder(): void {
		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('update')->willReturnSelf();
		$qb->method('set')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn('param');
		$qb->method('executeStatement')->willReturn(1);
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$qb->method('expr')->willReturn($expr);
		$this->db->method('getQueryBuilder')->willReturn($qb);
	}

	private function mockTransaction(): void {
		$this->db->method('beginTransaction');
		$this->db->method('commit');
	}

	/** @param string ...$ids */
	private function mockOwnedCategories(string ...$ids): void {
		$category = $this->createMock(CategoryEntity::class);
		$this->categoryMapper->method('findByIdAndOwner')->willReturnCallback(
			static function (string $categoryId, string $userId) use ($ids, $category): ?CategoryEntity {
				return in_array($categoryId, $ids, true) && $userId === 'alice' ? $category : null;
			}
		);
	}

	public function testIndexReturnsOnlyCurrentUsersStores(): void {
		$this->mockUser('alice');

		$store = new StoreEntity();
		$store->setId('11111111-2222-4333-8444-555555555555');
		$store->setOwner('alice');
		$store->setName('Market');

		$this->mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([$store]);

		$this->mapper->expects($this->once())
			->method('findCategoryIdsByStoreIds')
			->with(['11111111-2222-4333-8444-555555555555'])
			->willReturn([]);

		$response = $this->controller->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$stores = $response->getData()['stores'];
		$this->assertCount(1, $stores);
		$this->assertSame('Market', $stores[0]['name']);
		$this->assertNull($stores[0]['address']);
		$this->assertSame([], $stores[0]['categoryIds']);
	}

	public function testIndexReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->index();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testCreateReturnsCreatedStore(): void {
		$this->mockUser('alice');
		$this->mockTransaction();

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (StoreEntity $store): StoreEntity {
				$store->setId('11111111-2222-4333-8444-555555555555');
				return $store;
			});

		$response = $this->controller->create('Market');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$store = $response->getData()['store'];
		$this->assertSame('Market', $store['name']);
		$this->assertSame('11111111-2222-4333-8444-555555555555', $store['id']);
	}

	public function testCreateStoresAddressAndCategories(): void {
		$this->mockUser('alice');
		$this->mockTransaction();
		$this->mockOwnedCategories('c-1', 'c-2');

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (StoreEntity $store): StoreEntity {
				$store->setId('11111111-2222-4333-8444-555555555555');
				return $store;
			});

		$this->mapper->expects($this->once())
			->method('replaceCategoriesByStoreId')
			->with('11111111-2222-4333-8444-555555555555', ['c-1', 'c-2']);

		$response = $this->controller->create('Market', '  Hauptstrasse 1 ', ['c-1', 'c-2']);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$store = $response->getData()['store'];
		$this->assertSame('Hauptstrasse 1', $store['address']);
		$this->assertSame(['c-1', 'c-2'], $store['categoryIds']);
	}

	public function testCreateRejectsCategoryNotOwnedByUser(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->never())->method('insert');

		$response = $this->controller->create('Market', null, ['11111111-0000-4444-8555-999999999999']);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateReturnsUnprocessableWhenNameEmpty(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->never())->method('insert');

		$response = $this->controller->create('   ');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->create('Market');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testUpdateReturnsUpdatedStore(): void {
		$this->mockUser('alice');
		$this->mockTransaction();

		$store = new StoreEntity();
		$store->setId('11111111-2222-4333-8444-555555555555');
		$store->setOwner('alice');
		$store->setName('Old name');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($store);

		$this->mapper->expects($this->once())
			->method('update')
			->willReturnArgument(0);

		$this->mapper->expects($this->once())
			->method('replaceCategoriesByStoreId')
			->with('11111111-2222-4333-8444-555555555555', []);

		$response = $this->controller->update('11111111-2222-4333-8444-555555555555', '  New name  ');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('New name', $response->getData()['store']['name']);
		$this->assertNull($response->getData()['store']['address']);
	}

	public function testUpdateStoresAddressAndReplacesCategories(): void {
		$this->mockUser('alice');
		$this->mockTransaction();
		$this->mockOwnedCategories('c-9');

		$store = new StoreEntity();
		$store->setId('11111111-2222-4333-8444-555555555555');
		$store->setOwner('alice');
		$store->setName('Old name');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($store);

		$this->mapper->expects($this->once())
			->method('update')
			->willReturnArgument(0);

		$this->mapper->expects($this->once())
			->method('replaceCategoriesByStoreId')
			->with('11111111-2222-4333-8444-555555555555', ['c-9']);

		$response = $this->controller->update('11111111-2222-4333-8444-555555555555', 'New name', 'Zeil 10', ['c-9']);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('Zeil 10', $response->getData()['store']['address']);
		$this->assertSame(['c-9'], $response->getData()['store']['categoryIds']);
	}

	public function testUpdateRejectsCategoryNotOwnedByUser(): void {
		$this->mockUser('alice');

		$store = new StoreEntity();
		$store->setId('11111111-2222-4333-8444-555555555555');
		$store->setOwner('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($store);

		$this->mapper->expects($this->never())->method('update');

		$response = $this->controller->update('11111111-2222-4333-8444-555555555555', 'Name', null, ['99999999-0000-4444-8555-777777777777']);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testUpdateReturnsNotFoundWhenNotOwned(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn(null);

		$response = $this->controller->update('99999999-0000-4444-8555-777777777777', 'Name');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUpdateReturnsUnprocessableWhenNameEmpty(): void {
		$this->mockUser('alice');

		$store = new StoreEntity();
		$store->setId('11111111-2222-4333-8444-555555555555');
		$store->setOwner('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($store);

		$this->mapper->expects($this->never())->method('update');

		$response = $this->controller->update('11111111-2222-4333-8444-555555555555', '   ');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testDestroyDeletesStore(): void {
		$this->mockUser('alice');

		$store = new StoreEntity();
		$store->setId('11111111-2222-4333-8444-555555555555');
		$store->setOwner('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($store);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$this->mockQueryBuilder();

		$this->mapper->expects($this->once())
			->method('deleteCategoriesByStoreId')
			->with('11111111-2222-4333-8444-555555555555');

		$this->mapper->expects($this->once())->method('delete')->with($store);

		$response = $this->controller->destroy('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testDestroyReturnsNotFoundWhenNotOwned(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn(null);

		$this->mapper->expects($this->never())->method('delete');

		$response = $this->controller->destroy('99999999-0000-4444-8555-777777777777');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}
}
