<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\ProductController;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Entity\ProductAliasEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCP\AppFramework\Http;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProductControllerTest extends TestCase {
	private ProductController $controller;
	private ProductMapper $mapper;
	private ProductAliasMapper $aliasMapper;
	private CategoryMapper $categoryMapper;
	private ListItemMapper $itemMapper;
	private IUserSession $userSession;
	private IDBConnection $db;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->mapper = $this->createMock(ProductMapper::class);
		$this->aliasMapper = $this->createMock(ProductAliasMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->itemMapper = $this->createMock(ListItemMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ProductController(
			$request,
			$this->mapper,
			$this->aliasMapper,
			$this->categoryMapper,
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

	private function mockQueryBuilder(): void {
		$qb = $this->createMock(IQueryBuilder::class);
		$qb->method('delete')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('andWhere')->willReturnSelf();
		$qb->method('createNamedParameter')->willReturn('param');
		$qb->method('executeStatement')->willReturn(1);
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturn('1=1');
		$qb->method('expr')->willReturn($expr);
		$this->db->method('getQueryBuilder')->willReturn($qb);
	}

	private function product(string $id, string $name, ?string $categoryId = null, bool $isSubscription = false, bool $isIncome = false): ProductEntity {
		$product = new ProductEntity();
		$product->setId($id);
		$product->setOwner('alice');
		$product->setName($name);
		$product->setStatus('reviewed');
		$product->setIsFavorite(false);
		$product->setIsSubscription($isSubscription);
		$product->setIsIncome($isIncome);
		if ($categoryId !== null) {
			$product->setCategoryId($categoryId);
		}
		return $product;
	}

	public function testIndexReturnsOnlyCurrentUsersNormalProductsWithAliases(): void {
		$this->mockUser('alice');

		$milk = $this->product('11111111-2222-4333-8444-555555555555', 'Milk');

		$this->mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([$milk]);

		$alias = new ProductAliasEntity();
		$alias->setOwner('alice');
		$alias->setProductId('11111111-2222-4333-8444-555555555555');
		$alias->setAliasName('M');

		$this->aliasMapper->expects($this->once())
			->method('findByProductIds')
			->with(['11111111-2222-4333-8444-555555555555'], 'alice')
			->willReturn([$alias]);

		$response = $this->controller->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$products = $response->getData()['products'];
		$this->assertCount(1, $products);
		$this->assertSame('Milk', $products[0]['name']);
		$this->assertSame(['M'], $products[0]['aliases']);
		$this->assertFalse($products[0]['isFavorite']);
		$this->assertFalse($products[0]['isSubscription']);
		$this->assertFalse($products[0]['isIncome']);
	}

	public function testIndexReturnsSubscriptionsWhenTypeIsSubscriptions(): void {
		$this->mockUser('alice');

		$subscription = $this->product('11111111-2222-4333-8444-555555555555', 'Netflix', null, true);

		$this->mapper->expects($this->once())
			->method('findSubscriptionsByOwner')
			->with('alice')
			->willReturn([$subscription]);

		$this->aliasMapper->expects($this->once())
			->method('findByProductIds')
			->with(['11111111-2222-4333-8444-555555555555'], 'alice')
			->willReturn([]);

		$response = $this->controller->index('subscriptions');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$products = $response->getData()['products'];
		$this->assertCount(1, $products);
		$this->assertSame('Netflix', $products[0]['name']);
		$this->assertTrue($products[0]['isSubscription']);
		$this->assertFalse($products[0]['isIncome']);
	}

	public function testIndexReturnsIncomeWhenTypeIsIncome(): void {
		$this->mockUser('alice');

		$income = $this->product('11111111-2222-4333-8444-555555555555', 'Salary', null, false, true);

		$this->mapper->expects($this->once())
			->method('findIncomeByOwner')
			->with('alice')
			->willReturn([$income]);

		$this->aliasMapper->expects($this->once())
			->method('findByProductIds')
			->with(['11111111-2222-4333-8444-555555555555'], 'alice')
			->willReturn([]);

		$response = $this->controller->index('income');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$products = $response->getData()['products'];
		$this->assertCount(1, $products);
		$this->assertSame('Salary', $products[0]['name']);
		$this->assertFalse($products[0]['isSubscription']);
		$this->assertTrue($products[0]['isIncome']);
	}

	public function testIndexReturnsAllWhenTypeIsAll(): void {
		$this->mockUser('alice');

		$normal = $this->product('11111111-2222-4333-8444-555555555551', 'Milk');
		$subscription = $this->product('11111111-2222-4333-8444-555555555552', 'Netflix', null, true);
		$income = $this->product('11111111-2222-4333-8444-555555555553', 'Salary', null, false, true);

		$this->mapper->expects($this->once())
			->method('findAllIncludingSpecialByOwner')
			->with('alice')
			->willReturn([$normal, $subscription, $income]);

		$this->aliasMapper->expects($this->once())
			->method('findByProductIds')
			->with([
				'11111111-2222-4333-8444-555555555551',
				'11111111-2222-4333-8444-555555555552',
				'11111111-2222-4333-8444-555555555553',
			], 'alice')
			->willReturn([]);

		$response = $this->controller->index('all');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertCount(3, $response->getData()['products']);
	}

	public function testIndexReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->index();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testCreateReturnsCreatedProduct(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ProductEntity $product): ProductEntity {
				$product->setId('11111111-2222-4333-8444-555555555555');
				return $product;
			});

		$this->aliasMapper->expects($this->once())
			->method('insert');

		$response = $this->controller->create('Milk', null, null, ['M', 'M'], true);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$product = $response->getData()['product'];
		$this->assertSame('Milk', $product['name']);
		$this->assertSame('11111111-2222-4333-8444-555555555555', $product['id']);
		$this->assertSame(['M'], $product['aliases']);
		$this->assertTrue($product['isFavorite']);
		$this->assertSame('reviewed', $product['status']);
		$this->assertFalse($product['isSubscription']);
		$this->assertFalse($product['isIncome']);
	}

	public function testCreateReturnsSubscriptionAndIncomeProduct(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ProductEntity $product): ProductEntity {
				$product->setId('11111111-2222-4333-8444-555555555555');
				return $product;
			});

		$this->aliasMapper->expects($this->once())
			->method('insert');

		$response = $this->controller->create('Netflix', null, null, [], false, true, false);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$product = $response->getData()['product'];
		$this->assertTrue($product['isSubscription']);
		$this->assertFalse($product['isIncome']);
	}

	public function testCreateSetsCategoryWhenValid(): void {
		$this->mockUser('alice');

		$category = new CategoryEntity();
		$category->setId('22222222-3333-4444-8555-666666666666');
		$category->setOwner('alice');
		$category->setName('Dairy');
		$category->setIncome(false);

		$this->categoryMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('22222222-3333-4444-8555-666666666666', 'alice')
			->willReturn($category);

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ProductEntity $product): ProductEntity {
				$product->setId('11111111-2222-4333-8444-555555555555');
				return $product;
			});

		$response = $this->controller->create('Cheese', '22222222-3333-4444-8555-666666666666');

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame('22222222-3333-4444-8555-666666666666', $response->getData()['product']['categoryId']);
	}

	public function testCreateReturnsUnprocessableWhenNameEmpty(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->never())->method('insert');

		$response = $this->controller->create('   ');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateReturnsUnprocessableWhenCategoryNotFound(): void {
		$this->mockUser('alice');

		$this->categoryMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('99999999-0000-4444-8555-777777777777', 'alice')
			->willReturn(null);

		$this->mapper->expects($this->never())->method('insert');

		$response = $this->controller->create('Cheese', '99999999-0000-4444-8555-777777777777');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateReturnsUnprocessableWhenCategoryIsIncome(): void {
		$this->mockUser('alice');

		$category = new CategoryEntity();
		$category->setId('22222222-3333-4444-8555-666666666666');
		$category->setOwner('alice');
		$category->setName('Salary');
		$category->setIncome(true);

		$this->categoryMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('22222222-3333-4444-8555-666666666666', 'alice')
			->willReturn($category);

		$this->mapper->expects($this->never())->method('insert');

		$response = $this->controller->create('Cheese', '22222222-3333-4444-8555-666666666666');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateAllowsIncomeCategoryForIncomeProduct(): void {
		$this->mockUser('alice');

		$category = new CategoryEntity();
		$category->setId('22222222-3333-4444-8555-666666666666');
		$category->setOwner('alice');
		$category->setName('Salary');
		$category->setIncome(true);

		$this->categoryMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('22222222-3333-4444-8555-666666666666', 'alice')
			->willReturn($category);

		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ProductEntity $product): ProductEntity {
				$product->setId('11111111-2222-4333-8444-555555555555');
				return $product;
			});

		$this->aliasMapper->expects($this->once())
			->method('insert');

		$response = $this->controller->create('Salary', '22222222-3333-4444-8555-666666666666', null, [], false, false, true);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame('22222222-3333-4444-8555-666666666666', $response->getData()['product']['categoryId']);
		$this->assertTrue($response->getData()['product']['isIncome']);
	}

	public function testCreateReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->create('Milk');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testCreateRollsBackAndReturnsServerErrorWhenInsertFails(): void {
		$this->mockUser('alice');

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('rollBack');
		$this->db->expects($this->never())->method('commit');

		$this->mapper->expects($this->once())
			->method('insert')
			->willThrowException(new \RuntimeException('boom'));

		$this->aliasMapper->expects($this->never())->method('insert');

		$response = $this->controller->create('Milk');

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
	}

	public function testUpdateReturnsUpdatedProduct(): void {
		$this->mockUser('alice');

		$product = $this->product('11111111-2222-4333-8444-555555555555', 'Milk');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($product);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$this->mapper->expects($this->once())->method('update')->willReturnArgument(0);
		$this->aliasMapper->expects($this->once())->method('deleteByProductId');
		$this->aliasMapper->expects($this->once())->method('insert');

		$response = $this->controller->update('11111111-2222-4333-8444-555555555555', 'Milk', null, '1234', ['M'], true);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData()['product'];
		$this->assertSame('1234', $data['barcode']);
		$this->assertSame(['M'], $data['aliases']);
		$this->assertTrue($data['isFavorite']);
		$this->assertFalse($data['isSubscription']);
		$this->assertFalse($data['isIncome']);
	}

	public function testUpdateAllowsIncomeCategoryForIncomeProduct(): void {
		$this->mockUser('alice');

		$product = $this->product('11111111-2222-4333-8444-555555555555', 'Salary', null, false, true);

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($product);

		$category = new CategoryEntity();
		$category->setId('22222222-3333-4444-8555-666666666666');
		$category->setOwner('alice');
		$category->setName('Salary');
		$category->setIncome(true);

		$this->categoryMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('22222222-3333-4444-8555-666666666666', 'alice')
			->willReturn($category);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$this->mapper->expects($this->once())->method('update')->willReturnArgument(0);
		$this->aliasMapper->expects($this->once())->method('deleteByProductId');
		$this->aliasMapper->expects($this->once())->method('insert');

		$response = $this->controller->update('11111111-2222-4333-8444-555555555555', 'Salary', '22222222-3333-4444-8555-666666666666', null, [], false, false, true);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('22222222-3333-4444-8555-666666666666', $response->getData()['product']['categoryId']);
		$this->assertTrue($response->getData()['product']['isIncome']);
	}

	public function testUpdateReturnsNotFoundWhenNotOwned(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn(null);

		$response = $this->controller->update('99999999-0000-4444-8555-777777777777', 'Milk');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUpdateReturnsUnprocessableWhenCategoryIsIncome(): void {
		$this->mockUser('alice');

		$product = $this->product('11111111-2222-4333-8444-555555555555', 'Milk');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($product);

		$category = new CategoryEntity();
		$category->setId('22222222-3333-4444-8555-666666666666');
		$category->setOwner('alice');
		$category->setName('Salary');
		$category->setIncome(true);

		$this->categoryMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($category);

		$this->mapper->expects($this->never())->method('update');

		$response = $this->controller->update('11111111-2222-4333-8444-555555555555', 'Milk', '22222222-3333-4444-8555-666666666666');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testDestroyDeletesProduct(): void {
		$this->mockUser('alice');

		$product = $this->product('11111111-2222-4333-8444-555555555555', 'Milk');

		$this->mapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($product);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$this->mockQueryBuilder();

		$this->aliasMapper->expects($this->once())->method('deleteByProductId');
		$this->mapper->expects($this->once())->method('delete')->with($product);

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
