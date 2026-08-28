<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\ProductController;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Entity\ProductAliasEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCP\AppFramework\Http;
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
	private IUserSession $userSession;
	private IDBConnection $db;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->mapper = $this->createMock(ProductMapper::class);
		$this->aliasMapper = $this->createMock(ProductAliasMapper::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ProductController(
			$request,
			$this->mapper,
			$this->aliasMapper,
			$this->categoryMapper,
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

	private function product(string $id, string $name, ?string $categoryId = null): ProductEntity {
		$product = new ProductEntity();
		$product->setId($id);
		$product->setOwner('alice');
		$product->setName($name);
		$product->setStatus('reviewed');
		$product->setIsFavorite(false);
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
}
