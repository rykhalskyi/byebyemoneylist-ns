<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\ProductPriceController;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\ProductPriceMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Entity\ProductPriceEntity;
use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCP\AppFramework\Http;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProductPriceControllerTest extends TestCase {
	private ProductPriceController $controller;
	private ProductPriceMapper $priceMapper;
	private ProductMapper $productMapper;
	private StoreMapper $storeMapper;
	private IUserSession $userSession;
	private IDBConnection $db;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->priceMapper = $this->createMock(ProductPriceMapper::class);
		$this->productMapper = $this->createMock(ProductMapper::class);
		$this->storeMapper = $this->createMock(StoreMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ProductPriceController(
			$request,
			$this->priceMapper,
			$this->productMapper,
			$this->storeMapper,
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

	private function product(string $id, string $name): ProductEntity {
		$product = new ProductEntity();
		$product->setId($id);
		$product->setOwner('alice');
		$product->setName($name);
		$product->setStatus('reviewed');
		$product->setIsFavorite(false);
		$product->setIsSubscription(false);
		$product->setIsIncome(false);
		return $product;
	}

	private function store(string $id, string $name): StoreEntity {
		$store = new StoreEntity();
		$store->setId($id);
		$store->setOwner('alice');
		$store->setName($name);
		return $store;
	}

	public function testBatchUpsertUnauthenticated(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$response = $this->controller->batchUpsert([
			['productId' => 'p1', 'value' => 1.5, 'date' => '2026-09-01T10:00:00+00:00'],
		]);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testBatchUpsertRejectsEmptyArray(): void {
		$this->mockUser('alice');
		$response = $this->controller->batchUpsert([]);
		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testBatchUpsertRejectsUnknownProduct(): void {
		$this->mockUser('alice');
		$this->productMapper->method('findByIdAndOwner')->willReturn(null);
		$response = $this->controller->batchUpsert([
			['productId' => 'unknown', 'value' => 1.5, 'date' => '2026-09-01T10:00:00+00:00'],
		]);
		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testBatchUpsertRejectsNegativeValue(): void {
		$this->mockUser('alice');
		$this->productMapper->method('findByIdAndOwner')->willReturn($this->product('p1', 'Milk'));
		$response = $this->controller->batchUpsert([
			['productId' => 'p1', 'value' => -1, 'date' => '2026-09-01T10:00:00+00:00'],
		]);
		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testBatchUpsertRejectsInvalidDate(): void {
		$this->mockUser('alice');
		$this->productMapper->method('findByIdAndOwner')->willReturn($this->product('p1', 'Milk'));
		$response = $this->controller->batchUpsert([
			['productId' => 'p1', 'value' => 1.5, 'date' => 'not-a-date'],
		]);
		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testBatchUpsertInsertsWhenNoExistingPrice(): void {
		$this->mockUser('alice');
		$this->productMapper->method('findByIdAndOwner')->willReturn($this->product('p1', 'Milk'));
		$this->storeMapper->method('findByIdAndOwner')->willReturn($this->store('s1', 'Store'));
		$this->priceMapper->method('findByProductAndStore')->willReturn(null);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');

		$inserted = [];
		$this->priceMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (ProductPriceEntity $price) use (&$inserted): ProductPriceEntity {
				$price->setId('price-1');
				$inserted[] = $price;
				return $price;
			});

		$response = $this->controller->batchUpsert([
			['productId' => 'p1', 'storeId' => 's1', 'value' => 1.99, 'date' => '2026-09-01T10:00:00+00:00'],
		]);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertCount(1, $response->getData()['prices']);
		$this->assertCount(1, $inserted);
		$this->assertSame('p1', $inserted[0]->getProductId());
		$this->assertSame('s1', $inserted[0]->getStoreId());
		$this->assertSame(1.99, $inserted[0]->getValue());
	}
}
