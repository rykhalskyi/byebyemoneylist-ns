<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\ListItemController;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Entity\ListItemEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ListItemControllerTest extends TestCase {
	private ListItemController $controller;
	private ListItemMapper $itemMapper;
	private ListMapper $listMapper;
	private ProductMapper $productMapper;
	private IUserSession $userSession;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->itemMapper = $this->createMock(ListItemMapper::class);
		$this->listMapper = $this->createMock(ListMapper::class);
		$this->productMapper = $this->createMock(ProductMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ListItemController(
			$request,
			$this->itemMapper,
			$this->listMapper,
			$this->productMapper,
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

	private function list(string $id): ListEntity {
		$list = new ListEntity();
		$list->setId($id);
		$list->setOwner('alice');
		$list->setName('Groceries');
		return $list;
	}

	private function product(string $id, string $name): ProductEntity {
		$product = new ProductEntity();
		$product->setId($id);
		$product->setOwner('alice');
		$product->setName($name);
		return $product;
	}

	private function item(string $id, string $listId, string $productId): ListItemEntity {
		$item = new ListItemEntity();
		$item->setId($id);
		$item->setOwner('alice');
		$item->setListId($listId);
		$item->setProductId($productId);
		$item->setQuantity(1.0);
		$item->setStatus('added');
		$item->setCreatedAt(new \DateTime('2026-08-28T10:00:00+00:00'));
		return $item;
	}

	public function testIndexReturnsItemsForOwnedList(): void {
		$this->mockUser('alice');

		$listId = '11111111-2222-4333-8444-555555555555';
		$productId = '22222222-3333-4444-8555-666666666666';

		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with($listId, 'alice')
			->willReturn($this->list($listId));

		$item = $this->item('33333333-4444-4555-8666-777777777777', $listId, $productId);
		$item->setPrice(1.99);
		$item->setQuantity(1.5);

		$this->itemMapper->expects($this->once())
			->method('findByListId')
			->with($listId)
			->willReturn([$item]);

		$this->productMapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([$this->product($productId, 'Milk')]);

		$response = $this->controller->index($listId);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$items = $response->getData()['items'];
		$this->assertCount(1, $items);
		$this->assertSame('Milk', $items[0]['productName']);
		$this->assertSame(1.99, $items[0]['price']);
		$this->assertSame(1.5, $items[0]['quantity']);
		$this->assertSame('2026-08-28T10:00:00+00:00', $items[0]['createdAt']);
	}

	public function testIndexReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->index('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testIndexReturnsNotFoundWhenListNotOwned(): void {
		$this->mockUser('alice');

		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn(null);

		$this->itemMapper->expects($this->never())->method('findByListId');

		$response = $this->controller->index('99999999-0000-4444-8555-777777777777');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testCreateReturnsCreatedItem(): void {
		$this->mockUser('alice');

		$listId = '11111111-2222-4333-8444-555555555555';
		$productId = '22222222-3333-4444-8555-666666666666';

		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with($listId, 'alice')
			->willReturn($this->list($listId));

		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with($productId, 'alice')
			->willReturn($this->product($productId, 'Milk'));

		$this->itemMapper->expects($this->once())
			->method('insert')
			->willReturnArgument(0);

		$response = $this->controller->create($listId, $productId, 1.99, 1.5);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$item = $response->getData()['item'];
		$this->assertSame($listId, $item['listId']);
		$this->assertSame($productId, $item['productId']);
		$this->assertSame('Milk', $item['productName']);
		$this->assertSame(1.99, $item['price']);
		$this->assertSame(1.5, $item['quantity']);
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			$item['id'],
		);
	}

	public function testCreateDefaultsQuantityToOne(): void {
		$this->mockUser('alice');

		$listId = '11111111-2222-4333-8444-555555555555';
		$productId = '22222222-3333-4444-8555-666666666666';

		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with($listId, 'alice')
			->willReturn($this->list($listId));

		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with($productId, 'alice')
			->willReturn($this->product($productId, 'Milk'));

		$this->itemMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ListItemEntity $item): ListItemEntity {
				$item->setId('33333333-4444-4555-8666-777777777777');
				return $item;
			});

		$response = $this->controller->create($listId, $productId);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame(1.0, $response->getData()['item']['quantity']);
	}

	public function testCreateReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->create('11111111-2222-4333-8444-555555555555', '22222222-3333-4444-8555-666666666666');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testCreateReturnsNotFoundWhenListNotOwned(): void {
		$this->mockUser('alice');

		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn(null);

		$this->productMapper->expects($this->never())->method('findByIdAndOwner');
		$this->itemMapper->expects($this->never())->method('insert');

		$response = $this->controller->create('99999999-0000-4444-8555-777777777777', '22222222-3333-4444-8555-666666666666');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testCreateReturnsUnprocessableWhenProductNotFound(): void {
		$this->mockUser('alice');

		$listId = '11111111-2222-4333-8444-555555555555';

		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with($listId, 'alice')
			->willReturn($this->list($listId));

		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn(null);

		$this->itemMapper->expects($this->never())->method('insert');

		$response = $this->controller->create($listId, '99999999-0000-4444-8555-777777777777');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateReturnsUnprocessableWhenQuantityNotPositive(): void {
		$this->mockUser('alice');

		$listId = '11111111-2222-4333-8444-555555555555';
		$productId = '22222222-3333-4444-8555-666666666666';

		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with($listId, 'alice')
			->willReturn($this->list($listId));

		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with($productId, 'alice')
			->willReturn($this->product($productId, 'Milk'));

		$this->itemMapper->expects($this->never())->method('insert');

		$response = $this->controller->create($listId, $productId, null, 0);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateReturnsUnprocessableWhenPriceNegative(): void {
		$this->mockUser('alice');

		$listId = '11111111-2222-4333-8444-555555555555';
		$productId = '22222222-3333-4444-8555-666666666666';

		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with($listId, 'alice')
			->willReturn($this->list($listId));

		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with($productId, 'alice')
			->willReturn($this->product($productId, 'Milk'));

		$this->itemMapper->expects($this->never())->method('insert');

		$response = $this->controller->create($listId, $productId, -1.0);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}
}
