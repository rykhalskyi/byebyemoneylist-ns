<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\CategoryController;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\AppFramework\Http;
use OCP\DB\Exception as DbException;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CategoryControllerTest extends TestCase {
	private CategoryController $controller;
	private CategoryMapper $mapper;
	private IDBConnection $db;
	private IUserSession $userSession;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->mapper = $this->createMock(CategoryMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new CategoryController($request, $this->mapper, $this->db, $this->userSession, $logger);
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
		$qb->method('delete')->willReturnSelf();
		$qb->method('set')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn('param');
		$qb->method('executeStatement')->willReturn(1);
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$expr->method('in')->willReturn('1=1');
		$qb->method('expr')->willReturn($expr);
		$this->db->method('getQueryBuilder')->willReturn($qb);
	}

	public function testIndexReturnsOnlyCurrentUsersCategories(): void {
		$this->mockUser('alice');

		$category = new CategoryEntity();
		$category->setId('11111111-2222-4333-8444-555555555555');
		$category->setOwner('alice');
		$category->setName('Food');
		$category->setColor('#ff0000');
		$category->setEmoji('🍎');
		$category->setIncome(false);

		$this->mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([$category]);

		$response = $this->controller->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$categories = $response->getData()['categories'];
		$this->assertCount(1, $categories);
		$this->assertSame('Food', $categories[0]['name']);
		$this->assertSame('#ff0000', $categories[0]['color']);
		$this->assertSame('🍎', $categories[0]['emoji']);
		$this->assertFalse($categories[0]['income']);
	}

	public function testIndexReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->index();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testCreateReturnsCreatedCategory(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (CategoryEntity $category): CategoryEntity {
				$category->setId('11111111-2222-4333-8444-555555555555');
				return $category;
			});

		$response = $this->controller->create('Food', '#ff0000', '🍎', null, true);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$category = $response->getData()['category'];
		$this->assertSame('Food', $category['name']);
		$this->assertSame('#ff0000', $category['color']);
		$this->assertSame('🍎', $category['emoji']);
		$this->assertNull($category['parentId']);
		$this->assertTrue($category['income']);
	}

	public function testCreateSetsParentWhenValid(): void {
		$this->mockUser('alice');

		$parent = new CategoryEntity();
		$parent->setId('22222222-3333-4444-8555-666666666666');
		$parent->setOwner('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('22222222-3333-4444-8555-666666666666', 'alice')
			->willReturn($parent);

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (CategoryEntity $category): CategoryEntity {
				$category->setId('11111111-2222-4333-8444-555555555555');
				return $category;
			});

		$response = $this->controller->create('Dairy', null, null, '22222222-3333-4444-8555-666666666666', false);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame('22222222-3333-4444-8555-666666666666', $response->getData()['category']['parentId']);
	}

	public function testCreateReturnsUnprocessableWhenNameEmpty(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->never())->method('insert');

		$response = $this->controller->create('   ');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateReturnsUnprocessableWhenColorInvalid(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->never())->method('insert');

		$response = $this->controller->create('Food', 'red');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateAcceptsArgbColorAndNormalizesToRgb(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (CategoryEntity $category): CategoryEntity {
				$this->assertSame('#6b6b6b', $category->getColor());
				$category->setId('11111111-2222-4333-8444-555555555555');
				return $category;
			});

		$response = $this->controller->create('Food', '#ff6b6b6b');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame('#6b6b6b', $response->getData()['category']['color']);
	}

	public function testCreateReturnsUnprocessableWhenParentNotOwned(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('99999999-0000-4444-8555-777777777777', 'alice')
			->willReturn(null);

		$this->mapper->expects($this->never())->method('insert');

		$response = $this->controller->create('Food', null, null, '99999999-0000-4444-8555-777777777777', false);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->create('Food');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testUpdateReturnsUpdatedCategory(): void {
		$this->mockUser('alice');

		$category = new CategoryEntity();
		$category->setId('11111111-2222-4333-8444-555555555555');
		$category->setOwner('alice');
		$category->setName('Old');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($category);

		$this->mapper->expects($this->once())
			->method('update')
			->willReturnArgument(0);

		$response = $this->controller->update('11111111-2222-4333-8444-555555555555', 'Food', '#ff0000', '🍎', null, true);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData()['category'];
		$this->assertSame('Food', $data['name']);
		$this->assertSame('#ff0000', $data['color']);
		$this->assertSame('🍎', $data['emoji']);
		$this->assertTrue($data['income']);
	}

	public function testUpdateReturnsNotFoundWhenNotOwned(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn(null);

		$response = $this->controller->update('99999999-0000-4444-8555-777777777777', 'Food');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUpdateReturnsUnprocessableWhenSelfParent(): void {
		$this->mockUser('alice');

		$category = new CategoryEntity();
		$category->setId('11111111-2222-4333-8444-555555555555');
		$category->setOwner('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($category);

		$this->mapper->expects($this->never())->method('update');

		$response = $this->controller->update('11111111-2222-4333-8444-555555555555', 'Food', null, null, '11111111-2222-4333-8444-555555555555', false);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testDestroyDeletesCategory(): void {
		$this->mockUser('alice');

		$category = new CategoryEntity();
		$category->setId('11111111-2222-4333-8444-555555555555');
		$category->setOwner('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($category);

		$this->mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([$category]);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$this->mockQueryBuilder();

		$response = $this->controller->destroy('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testDestroyDeletesCategoryWithDescendants(): void {
		$this->mockUser('alice');

		$root = new CategoryEntity();
		$root->setId('11111111-1111-4111-8111-111111111111');
		$root->setOwner('alice');

		$child = new CategoryEntity();
		$child->setId('22222222-2222-4222-8222-222222222222');
		$child->setOwner('alice');
		$child->setParentId($root->getId());

		$grandchild = new CategoryEntity();
		$grandchild->setId('33333333-3333-4333-8333-333333333333');
		$grandchild->setOwner('alice');
		$grandchild->setParentId($child->getId());

		$unrelated = new CategoryEntity();
		$unrelated->setId('44444444-4444-4444-8444-444444444444');
		$unrelated->setOwner('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($root);
		$this->mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([$root, $child, $grandchild, $unrelated]);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$deletedIds = [];
		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('update')->willReturnSelf();
		$qb->method('delete')->willReturnSelf();
		$qb->method('set')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('executeStatement')->willReturn(1);
		$qb->method('createNamedParameter')->willReturnCallback(function (mixed $value) use (&$deletedIds): string {
			if (is_array($value)) {
				$deletedIds = $value;
			}
			return 'param';
		});
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$expr->method('in')->willReturn('1=1');
		$qb->method('expr')->willReturn($expr);
		$this->db->method('getQueryBuilder')->willReturn($qb);

		$response = $this->controller->destroy($root->getId());

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(
			[$root->getId(), $child->getId(), $grandchild->getId()],
			$deletedIds,
		);
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

	public function testBatchCreateReturnsCreatedCategories(): void {
		$this->mockUser('alice');

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$insertedCategories = [];
		$this->mapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function (CategoryEntity $category) use (&$insertedCategories): CategoryEntity {
				$insertedCategories[] = $category;
				return $category;
			});

		$categoriesInput = [
			['name' => 'Food', 'color' => '#ff0000', 'emoji' => '🍎', 'tempId' => 'temp-1'],
			['name' => 'Transport', 'color' => '#ff00ff00', 'income' => false, 'tempId' => 'temp-2']
		];

		$response = $this->controller->batchCreate($categoriesInput);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$result = $response->getData()['categories'];
		$this->assertCount(2, $result);
		$this->assertSame('Food', $result[0]['name']);
		$this->assertSame('temp-1', $result[0]['tempId']);
		$this->assertSame('Transport', $result[1]['name']);
		$this->assertSame('temp-2', $result[1]['tempId']);
		$this->assertSame('#ff0000', $insertedCategories[0]->getColor());
		$this->assertSame('#00ff00', $insertedCategories[1]->getColor());
	}

	public function testBatchCreateHandlesTempIdParentIdMapping(): void {
		$this->mockUser('alice');

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');

		$insertedCategories = [];
		$this->mapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function (CategoryEntity $category) use (&$insertedCategories): CategoryEntity {
				$insertedCategories[] = $category;
				return $category;
			});

		$categoriesInput = [
			['name' => 'Food', 'tempId' => 'temp-parent'],
			['name' => 'Bakery', 'parentId' => 'temp-parent', 'tempId' => 'temp-child']
		];

		$response = $this->controller->batchCreate($categoriesInput);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertCount(2, $insertedCategories);
		$parentId = $insertedCategories[0]->getId();
		$this->assertSame($parentId, $insertedCategories[1]->getParentId());
	}

	public function testBatchCreateResolvesTempParentRegardlessOfOrder(): void {
		$this->mockUser('alice');

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$insertedCategories = [];
		$this->mapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function (CategoryEntity $category) use (&$insertedCategories): CategoryEntity {
				$insertedCategories[] = $category;
				return $category;
			});

		$categoriesInput = [
			['name' => 'Bakery', 'parentId' => 'temp-parent', 'tempId' => 'temp-child'],
			['name' => 'Food', 'tempId' => 'temp-parent']
		];

		$response = $this->controller->batchCreate($categoriesInput);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertCount(2, $insertedCategories);
		$this->assertSame('Food', $insertedCategories[0]->getName());
		$this->assertNull($insertedCategories[0]->getParentId());
		$this->assertSame($insertedCategories[0]->getId(), $insertedCategories[1]->getParentId());
	}

	public function testBatchCreateOrdersRootsBeforeChildrenBeforeGrandchildren(): void {
		$this->mockUser('alice');

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$insertedCategories = [];
		$this->mapper->expects($this->exactly(3))
			->method('insert')
			->willReturnCallback(function (CategoryEntity $category) use (&$insertedCategories): CategoryEntity {
				$insertedCategories[] = $category;
				return $category;
			});

		$categoriesInput = [
			['name' => 'Croissant', 'parentId' => 'temp-child', 'tempId' => 'temp-grandchild'],
			['name' => 'Bakery', 'parentId' => 'temp-root', 'tempId' => 'temp-child'],
			['name' => 'Food', 'tempId' => 'temp-root']
		];

		$response = $this->controller->batchCreate($categoriesInput);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertCount(3, $insertedCategories);
		$this->assertSame('Food', $insertedCategories[0]->getName());
		$this->assertSame('Bakery', $insertedCategories[1]->getName());
		$this->assertSame('Croissant', $insertedCategories[2]->getName());
		$this->assertNull($insertedCategories[0]->getParentId());
		$this->assertSame($insertedCategories[0]->getId(), $insertedCategories[1]->getParentId());
		$this->assertSame($insertedCategories[1]->getId(), $insertedCategories[2]->getParentId());
	}

	public function testBatchCreateCreatesOrphanedCategoryAsRootWhenParentNotFound(): void {
		$this->mockUser('alice');

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$insertedCategories = [];
		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (CategoryEntity $category) use (&$insertedCategories): CategoryEntity {
				$insertedCategories[] = $category;
				return $category;
			});

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('missing-parent-id', 'alice')
			->willReturn(null);

		$response = $this->controller->batchCreate([
			['name' => 'Bakery', 'parentId' => 'missing-parent-id', 'tempId' => 'temp-bakery']
		]);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertCount(1, $insertedCategories);
		$this->assertNull($insertedCategories[0]->getParentId());
		$this->assertNull($response->getData()['categories'][0]['parentId']);
	}

	public function testBatchCreateResolvesExistingServerParent(): void {
		$this->mockUser('alice');

		$parent = new CategoryEntity();
		$parent->setId('22222222-3333-4444-8555-666666666666');
		$parent->setOwner('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('22222222-3333-4444-8555-666666666666', 'alice')
			->willReturn($parent);

		$insertedCategories = [];
		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (CategoryEntity $category) use (&$insertedCategories): CategoryEntity {
				$insertedCategories[] = $category;
				return $category;
			});

		$response = $this->controller->batchCreate([
			['name' => 'Dairy', 'parentId' => '22222222-3333-4444-8555-666666666666', 'tempId' => 'temp-dairy']
		]);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertCount(1, $insertedCategories);
		$this->assertSame('22222222-3333-4444-8555-666666666666', $insertedCategories[0]->getParentId());
	}

	public function testBatchCreateReturnsUnprocessableWhenEmpty(): void {
		$this->mockUser('alice');

		$response = $this->controller->batchCreate([]);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testBatchCreateOnlyIfEmptyReturnsExistingWithoutCreating(): void {
		$this->mockUser('alice');

		$existing = new CategoryEntity();
		$existing->setId('11111111-2222-4333-8444-555555555555');
		$existing->setOwner('alice');
		$existing->setName('Food');

		$this->mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([$existing]);
		$this->db->expects($this->never())->method('beginTransaction');
		$this->mapper->expects($this->never())->method('insert');

		$response = $this->controller->batchCreate([
			['name' => 'Food', 'tempId' => 'supermarket']
		], true);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertCount(1, $response->getData()['categories']);
		$this->assertSame('Food', $response->getData()['categories'][0]['name']);
	}

	public function testBatchCreateOnlyIfEmptyCreatesDeterministicSetWhenEmpty(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([]);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$inserted = [];
		$this->mapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function (CategoryEntity $category) use (&$inserted): CategoryEntity {
				$inserted[] = $category;
				return $category;
			});

		$response = $this->controller->batchCreate([
			['name' => 'Food', 'tempId' => 'supermarket'],
			['name' => 'Bakery', 'parentId' => 'supermarket', 'tempId' => 'supermarket-bakery'],
		], true);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertCount(2, $inserted);
		$this->assertSame(Uuid::v5('alice:supermarket'), $inserted[0]->getId());
		$this->assertSame(Uuid::v5('alice:supermarket-bakery'), $inserted[1]->getId());
		$this->assertSame($inserted[0]->getId(), $inserted[1]->getParentId());
	}

	public function testBatchCreateOnlyIfEmptyRecoversWhenConcurrentInsertWins(): void {
		$this->mockUser('alice');

		$existing = new CategoryEntity();
		$existing->setId(Uuid::v5('alice:supermarket'));
		$existing->setOwner('alice');
		$existing->setName('Food');

		// The pre-check sees an empty account, the recovery read returns the winner's rows.
		$this->mapper->expects($this->exactly(2))
			->method('findAllByOwner')
			->with('alice')
			->willReturnOnConsecutiveCalls([], [$existing]);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->never())->method('commit');
		$this->db->expects($this->once())->method('rollBack');

		$exception = $this->createMock(DbException::class);
		$exception->method('getReason')->willReturn(DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION);
		$this->mapper->expects($this->once())
			->method('insert')
			->willThrowException($exception);

		$response = $this->controller->batchCreate([
			['name' => 'Food', 'tempId' => 'supermarket']
		], true);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('Food', $response->getData()['categories'][0]['name']);
	}

	public function testConfirmUpdatesCategoryStatusToConfirmed(): void {
		$this->mockUser('alice');

		$category = new CategoryEntity();
		$category->setId('11111111-2222-4333-8444-555555555555');
		$category->setOwner('alice');
		$category->setStatus('pending_review');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($category);

		$this->mapper->expects($this->once())
			->method('update')
			->willReturnArgument(0);

		$response = $this->controller->confirm('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('confirmed', $response->getData()['category']['status']);
	}

	public function testConfirmAllUpdatesPendingCategories(): void {
		$this->mockUser('alice');

		$this->mockQueryBuilder();

		$response = $this->controller->confirmAll();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}
}
