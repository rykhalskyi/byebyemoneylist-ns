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

final class CategoryControllerTest extends TestCase {
	private CategoryController $controller;
	private CategoryMapper $mapper;
	private IUserSession $userSession;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->mapper = $this->createMock(CategoryMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);

		$this->controller = new CategoryController($request, $this->mapper, $this->userSession);
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
}
