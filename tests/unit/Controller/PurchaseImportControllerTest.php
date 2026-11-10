<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\PurchaseImportController;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\PurchaseImportCategoryMapper;
use OCA\ByeByeMoneyList\Db\PurchaseImportItemMapper;
use OCA\ByeByeMoneyList\Db\PurchaseImportMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportCategoryEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportItemEntity;
use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCA\ByeByeMoneyList\Service\Import\PurchaseImportAiService;
use OCA\ByeByeMoneyList\Service\Import\PurchaseImportApproveService;
use OCA\ByeByeMoneyList\Service\Import\PurchaseImportMatcher;
use OCP\AppFramework\Http;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PurchaseImportControllerTest extends TestCase {
	private PurchaseImportController $controller;
	private PurchaseImportMapper $importMapper;
	private PurchaseImportItemMapper $itemMapper;
	private PurchaseImportCategoryMapper $categoryMapper;
	private PurchaseImportMatcher $matcher;
	private PurchaseImportAiService $aiService;
	private PurchaseImportApproveService $approveService;
	private StoreMapper $storeMapper;
	private CategoryMapper $catalogCategoryMapper;
	private ProductMapper $productMapper;
	private IUserSession $userSession;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->importMapper = $this->createMock(PurchaseImportMapper::class);
		$this->itemMapper = $this->createMock(PurchaseImportItemMapper::class);
		$this->categoryMapper = $this->createMock(PurchaseImportCategoryMapper::class);
		$this->matcher = $this->createMock(PurchaseImportMatcher::class);
		$this->aiService = $this->createMock(PurchaseImportAiService::class);
		$this->approveService = $this->createMock(PurchaseImportApproveService::class);
		$this->storeMapper = $this->createMock(StoreMapper::class);
		$this->catalogCategoryMapper = $this->createMock(CategoryMapper::class);
		$this->productMapper = $this->createMock(ProductMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$db = $this->createMock(IDBConnection::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new PurchaseImportController(
			$request,
			$this->importMapper,
			$this->itemMapper,
			$this->categoryMapper,
			$this->matcher,
			$this->aiService,
			$this->approveService,
			$this->storeMapper,
			$this->catalogCategoryMapper,
			$this->productMapper,
			$db,
			$this->userSession,
			$logger,
		);
	}

	private function mockUser(string $uid = 'alice'): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function payload(): array {
		return [
			'schema' => 'bbml.purchase-upload/1',
			'listId' => 'b1f0c7d2-3b1e-4a67-9c55-0e1d2f3a4b5c',
			'clientId' => 'client-1',
			'list' => [
				'name' => 'Purchase 09.11.2026',
				'createdAt' => '2026-11-09T18:30:00Z',
				'purchaseDate' => '2026-11-09T19:05:00Z',
				'currency' => '€',
				'finalTotal' => 42.37,
				'store' => ['name' => 'Lidl', 'address' => 'Hauptstr. 1', 'receiptName' => 'LIDL'],
				'categories' => [['name' => 'Groceries', 'isIncome' => false]],
			],
			'items' => [[
				'lineId' => '1',
				'name' => 'Milk 1l',
				'quantity' => 1.0,
				'unitPrice' => 1.29,
				'discount' => null,
				'isCoupon' => false,
				'barcode' => '4001234567890',
				'categoryHint' => 'Dairy',
			]],
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function callCreate(array $payload): \OCP\AppFramework\Http\DataResponse {
		return $this->controller->create(
			(string)$payload['schema'],
			(string)$payload['listId'],
			$payload['list'],
			$payload['clientId'] ?? null,
			$payload['items'],
		);
	}

	private function makeImport(string $status, string $id = 'import-1'): PurchaseImportEntity {
		$import = new PurchaseImportEntity();
		$import->setId($id);
		$import->setOwner('alice');
		$import->setClientListId('b1f0c7d2-3b1e-4a67-9c55-0e1d2f3a4b5c');
		$import->setName('Purchase');
		$import->setStatus($status);
		return $import;
	}

	public function testCreateRequiresLogin(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->callCreate($this->payload());

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testCreateRejectsUnsupportedSchema(): void {
		$this->mockUser();
		$payload = $this->payload();
		$payload['schema'] = 'other/1';

		$response = $this->callCreate($payload);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		$this->assertSame('Unsupported schema', $response->getData()['message']);
	}

	public function testCreateRejectsInvalidListId(): void {
		$this->mockUser();
		$payload = $this->payload();
		$payload['listId'] = 'not-a-uuid';

		$response = $this->callCreate($payload);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateRejectsEmptyName(): void {
		$this->mockUser();
		$payload = $this->payload();
		$payload['list']['name'] = '   ';

		$response = $this->callCreate($payload);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateStoresNewImport(): void {
		$this->mockUser();
		$this->importMapper->method('findByOwnerAndClientListId')->willReturn(null);
		$this->importMapper->expects($this->once())->method('insert')->willReturnArgument(0);
		$this->importMapper->expects($this->once())->method('update')->willReturnArgument(0);
		$this->itemMapper->expects($this->once())->method('insert')->willReturnArgument(0);
		$this->itemMapper->expects($this->once())->method('update')->willReturnArgument(0);
		$this->categoryMapper->expects($this->once())->method('insert')->willReturnArgument(0);
		$this->categoryMapper->expects($this->once())->method('update')->willReturnArgument(0);
		$this->matcher->expects($this->once())->method('suggestForImport');

		$response = $this->callCreate($this->payload());
		$data = $response->getData();

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertFalse($data['duplicate']);
		$this->assertSame('Purchase 09.11.2026', $data['import']['name']);
		$this->assertSame(PurchaseImportEntity::STATUS_PENDING, $data['import']['status']);
		$this->assertCount(1, $data['import']['items']);
		$this->assertSame('Milk 1l', $data['import']['items'][0]['rawName']);
	}

	public function testCreateReplacesPendingImport(): void {
		$this->mockUser();
		$this->importMapper->method('findByOwnerAndClientListId')
			->willReturn($this->makeImport(PurchaseImportEntity::STATUS_PENDING));
		$this->importMapper->expects($this->exactly(2))->method('update')->willReturnArgument(0);
		$this->importMapper->expects($this->never())->method('insert');
		$this->itemMapper->expects($this->once())->method('deleteByImportId');
		$this->categoryMapper->expects($this->once())->method('deleteByImportId');
		$this->itemMapper->expects($this->once())->method('insert')->willReturnArgument(0);
		$this->categoryMapper->expects($this->once())->method('insert')->willReturnArgument(0);
		$this->matcher->expects($this->once())->method('suggestForImport');

		$response = $this->callCreate($this->payload());

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertFalse($response->getData()['duplicate']);
	}

	public function testCreateReturnsDuplicateForApprovedImport(): void {
		$this->mockUser();
		$this->importMapper->method('findByOwnerAndClientListId')
			->willReturn($this->makeImport(PurchaseImportEntity::STATUS_APPROVED));
		$this->importMapper->expects($this->never())->method('insert');
		$this->importMapper->expects($this->never())->method('update');
		$this->itemMapper->expects($this->never())->method('insert');
		$this->itemMapper->method('findByImportId')->willReturn([]);
		$this->categoryMapper->method('findByImportId')->willReturn([]);
		$this->matcher->expects($this->never())->method('suggestForImport');

		$response = $this->callCreate($this->payload());

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertTrue($response->getData()['duplicate']);
	}

	public function testIndexRejectsInvalidStatus(): void {
		$this->mockUser();

		$response = $this->controller->index('bogus');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testIndexReturnsSummaries(): void {
		$this->mockUser();
		$this->importMapper->method('findAllByOwner')->willReturn([
			$this->makeImport(PurchaseImportEntity::STATUS_PENDING),
		]);
		$this->itemMapper->method('countByImportId')->willReturn(3);
		$this->categoryMapper->method('countByImportId')->willReturn(1);

		$response = $this->controller->index();
		$data = $response->getData();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertCount(1, $data['imports']);
		$this->assertSame(3, $data['imports'][0]['itemCount']);
		$this->assertSame(1, $data['imports'][0]['categoryCount']);
	}

	public function testShowReturnsNotFoundForForeignImport(): void {
		$this->mockUser();
		$this->importMapper->method('findByIdAndOwner')->willReturn(null);

		$response = $this->controller->show('missing');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testShowReturnsImport(): void {
		$this->mockUser();
		$this->importMapper->method('findByIdAndOwner')
			->willReturn($this->makeImport(PurchaseImportEntity::STATUS_PENDING));
		$this->itemMapper->method('findByImportId')->willReturn([]);
		$this->categoryMapper->method('findByImportId')->willReturn([]);

		$response = $this->controller->show('import-1');
		$data = $response->getData();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('import-1', $data['import']['id']);
	}

	private function makeItem(string $id = 'item-1', string $name = 'Milk'): PurchaseImportItemEntity {
		$item = new PurchaseImportItemEntity();
		$item->setId($id);
		$item->setImportId('import-1');
		$item->setOwner('alice');
		$item->setRawName($name);
		$item->setQuantity(1.0);
		$item->setAction(PurchaseImportEntity::ACTION_PENDING);
		return $item;
	}

	private function makeCategory(string $id = 'row-cat-1', string $name = 'Groceries'): PurchaseImportCategoryEntity {
		$category = new PurchaseImportCategoryEntity();
		$category->setId($id);
		$category->setImportId('import-1');
		$category->setOwner('alice');
		$category->setRawName($name);
		$category->setAction(PurchaseImportEntity::ACTION_PENDING);
		return $category;
	}

	public function testSuggestRejectsApprovedImport(): void {
		$this->mockUser();
		$this->importMapper->method('findByIdAndOwner')
			->willReturn($this->makeImport(PurchaseImportEntity::STATUS_APPROVED));
		$this->matcher->expects($this->never())->method('suggestForImport');

		$response = $this->controller->suggest('import-1');

		$this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
	}

	public function testSuggestRunsMatcherAndPersists(): void {
		$this->mockUser();
		$import = $this->makeImport(PurchaseImportEntity::STATUS_PENDING);
		$this->importMapper->method('findByIdAndOwner')->willReturn($import);
		$this->itemMapper->method('findByImportId')->willReturn([]);
		$this->categoryMapper->method('findByImportId')->willReturn([]);
		$this->matcher->expects($this->once())->method('suggestForImport');
		$this->importMapper->expects($this->once())->method('update')->willReturnArgument(0);

		$response = $this->controller->suggest('import-1');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(PurchaseImportEntity::STATUS_IN_REVIEW, $response->getData()['import']['status']);
	}

	public function testSaveMappingRejectsUnknownProduct(): void {
		$this->mockUser();
		$this->importMapper->method('findByIdAndOwner')
			->willReturn($this->makeImport(PurchaseImportEntity::STATUS_PENDING));
		$this->itemMapper->method('findByImportId')->willReturn([$this->makeItem()]);
		$this->categoryMapper->method('findByImportId')->willReturn([]);
		$this->productMapper->method('findByIdAndOwner')->willReturn(null);

		$response = $this->controller->saveMapping('import-1', [], [], [[
			'id' => 'item-1',
			'action' => PurchaseImportEntity::ACTION_MAP_EXISTING,
			'mappedProductId' => 'missing',
		]]);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testSaveMappingStoresActions(): void {
		$this->mockUser();
		$this->importMapper->method('findByIdAndOwner')
			->willReturn($this->makeImport(PurchaseImportEntity::STATUS_PENDING));
		$item = $this->makeItem();
		$this->itemMapper->method('findByImportId')->willReturn([$item]);
		$this->categoryMapper->method('findByImportId')->willReturn([]);
		$product = new ProductEntity();
		$product->setId('product-1');
		$product->setOwner('alice');
		$product->setName('Milk');
		$this->productMapper->method('findByIdAndOwner')->willReturn($product);
		$this->itemMapper->expects($this->once())->method('update')->willReturnArgument(0);
		$this->importMapper->expects($this->once())->method('update')->willReturnArgument(0);

		$response = $this->controller->saveMapping('import-1', [], [], [[
			'id' => 'item-1',
			'action' => PurchaseImportEntity::ACTION_MAP_EXISTING,
			'mappedProductId' => 'product-1',
		]]);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(
			PurchaseImportEntity::ACTION_MAP_EXISTING,
			$response->getData()['import']['items'][0]['action'],
		);
		$this->assertSame('product-1', $response->getData()['import']['items'][0]['mappedProductId']);
	}

	public function testSaveMappingStoresStoreAndCategoryChoices(): void {
		$this->mockUser();
		$import = $this->makeImport(PurchaseImportEntity::STATUS_PENDING);
		$this->importMapper->method('findByIdAndOwner')->willReturn($import);
		$row = $this->makeCategory();
		$this->itemMapper->method('findByImportId')->willReturn([]);
		$this->categoryMapper->method('findByImportId')->willReturn([$row]);
		$store = new StoreEntity();
		$store->setId('store-1');
		$store->setOwner('alice');
		$store->setName('Lidl');
		$this->storeMapper->method('findByIdAndOwner')->willReturn($store);
		$category = new CategoryEntity();
		$category->setId('cat-1');
		$category->setOwner('alice');
		$category->setName('Groceries');
		$this->catalogCategoryMapper->method('findByIdAndOwner')->willReturn($category);

		$response = $this->controller->saveMapping(
			'import-1',
			['action' => PurchaseImportEntity::ACTION_MAP_EXISTING, 'mappedStoreId' => 'store-1'],
			[['id' => 'row-cat-1', 'action' => PurchaseImportEntity::ACTION_MAP_EXISTING, 'mappedCategoryId' => 'cat-1']],
			[],
		);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('store-1', $response->getData()['import']['storeMapping']['mappedStoreId']);
		$this->assertSame('cat-1', $response->getData()['import']['categories'][0]['mappedCategoryId']);
	}

	public function testApproveReturnsDuplicateForApprovedImport(): void {
		$this->mockUser();
		$this->importMapper->method('findByIdAndOwner')
			->willReturn($this->makeImport(PurchaseImportEntity::STATUS_APPROVED));
		$this->itemMapper->method('findByImportId')->willReturn([]);
		$this->categoryMapper->method('findByImportId')->willReturn([]);
		$this->approveService->expects($this->never())->method('approve');

		$response = $this->controller->approve('import-1');

		$this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
		$this->assertTrue($response->getData()['duplicate']);
	}

	public function testApproveCreatesList(): void {
		$this->mockUser();
		$this->importMapper->method('findByIdAndOwner')
			->willReturn($this->makeImport(PurchaseImportEntity::STATUS_IN_REVIEW));
		$this->itemMapper->method('findByImportId')->willReturn([]);
		$this->categoryMapper->method('findByImportId')->willReturn([]);
		$list = new ListEntity();
		$list->setId('b1f0c7d2-3b1e-4a67-9c55-0e1d2f3a4b5c');
		$list->setName('Purchase');
		$list->setStatus('finished');
		$list->setIsFinished(true);
		$this->approveService->method('approve')->willReturn($list);

		$response = $this->controller->approve('import-1');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('b1f0c7d2-3b1e-4a67-9c55-0e1d2f3a4b5c', $response->getData()['list']['id']);
	}

	public function testDiscardMarksImportDiscarded(): void {
		$this->mockUser();
		$import = $this->makeImport(PurchaseImportEntity::STATUS_PENDING);
		$this->importMapper->method('findByIdAndOwner')->willReturn($import);
		$this->itemMapper->method('findByImportId')->willReturn([]);
		$this->categoryMapper->method('findByImportId')->willReturn([]);
		$this->importMapper->expects($this->once())->method('update')->willReturnArgument(0);

		$response = $this->controller->discard('import-1');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(PurchaseImportEntity::STATUS_DISCARDED, $response->getData()['import']['status']);
	}
}
