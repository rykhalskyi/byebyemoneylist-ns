<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\CategoryController;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCP\AppFramework\Http;
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

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$this->mockQueryBuilder();

		$this->mapper->expects($this->once())->method('delete')->with($category);

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


