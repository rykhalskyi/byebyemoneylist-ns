<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\CategoryController;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CategoryControllerTest extends TestCase {
	private CategoryController $controller;
	private CategoryMapper $mapper;
	private IUserSession $userSession;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->mapper = $this->createMock(CategoryMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new CategoryController($request, $this->mapper, $this->userSession, $logger);
	}

	private function mockUser(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
		return $user;
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
}
