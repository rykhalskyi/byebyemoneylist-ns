<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\StoreController;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class StoreControllerTest extends TestCase {
	private StoreController $controller;
	private StoreMapper $mapper;
	private IUserSession $userSession;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->mapper = $this->createMock(StoreMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new StoreController($request, $this->mapper, $this->userSession, $logger);
	}

	private function mockUser(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
		return $user;
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

		$response = $this->controller->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$stores = $response->getData()['stores'];
		$this->assertCount(1, $stores);
		$this->assertSame('Market', $stores[0]['name']);
	}

	public function testIndexReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->index();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testCreateReturnsCreatedStore(): void {
		$this->mockUser('alice');

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
}
