<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Entity\ListItemEntity;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class ListItemController extends OCSController {
	private ListItemMapper $itemMapper;
	private ListMapper $listMapper;
	private ProductMapper $productMapper;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		ListItemMapper $itemMapper,
		ListMapper $listMapper,
		ProductMapper $productMapper,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->itemMapper = $itemMapper;
		$this->listMapper = $listMapper;
		$this->productMapper = $productMapper;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Get all items for the current user's shopping list
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id List id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, array{items: list<array{id: string, listId: string, productId: string, productName: string, price: ?float, quantity: float, createdAt: ?string}>}|array{message: string}, array{}>
	 *
	 * 200: Items returned
	 * 401: Current user is not logged in
	 * 404: List not found or not owned by the current user
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/lists/{id}/items')]
	public function index(string $id): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$list = $this->listMapper->findByIdAndOwner($id, $userId);
		if ($list === null) {
			return new DataResponse(['message' => 'List not found'], Http::STATUS_NOT_FOUND);
		}

		$items = $this->itemMapper->findByListId($id);
		$productNames = $this->productNamesByProductId($userId);

		$serialized = array_map(
			fn (ListItemEntity $item): array => $this->serializeItem($item, $productNames[$item->getProductId() ?? ''] ?? ''),
			$items,
		);

		return new DataResponse(['items' => array_values($serialized)], Http::STATUS_OK);
	}

	/**
	 * Add a product to the current user's shopping list
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id List id
	 * @param string $productId Product id (required, must belong to the current user)
	 * @param ?float $price Optional product price (must not be negative)
	 * @param ?float $quantity Quantity as a float (must be greater than zero)
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{item: array{id: string, listId: string, productId: string, productName: string, price: ?float, quantity: float, createdAt: ?string}}|array{message: string}, array{}>
	 *
	 * 201: Item added
	 * 401: Current user is not logged in
	 * 404: List not found or not owned by the current user
	 * 422: Product not found, price is negative, or quantity is not greater than zero
	 * 500: Failed to add the item
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/lists/{id}/items')]
	public function create(string $id, string $productId, ?float $price = null, ?float $quantity = 1.0): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$list = $this->listMapper->findByIdAndOwner($id, $userId);
		if ($list === null) {
			return new DataResponse(['message' => 'List not found'], Http::STATUS_NOT_FOUND);
		}

		$product = $this->productMapper->findByIdAndOwner($productId, $userId);
		if ($product === null) {
			return new DataResponse(['message' => 'Product not found'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$quantity = $quantity ?? 1.0;
		if ($quantity <= 0) {
			return new DataResponse(['message' => 'Quantity must be greater than zero'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if ($price !== null && $price < 0) {
			return new DataResponse(['message' => 'Price must not be negative'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$item = new ListItemEntity();
		$item->setId(Uuid::v4());
		$item->setOwner($userId);
		$item->setListId($id);
		$item->setProductId($productId);
		$item->setQuantity($quantity);
		$item->setStatus('added');
		if ($price !== null) {
			$item->setPrice($price);
		}
		$item->setCreatedAt(new DateTime('now', new DateTimeZone('UTC')));

		try {
			$created = $this->itemMapper->insert($item);
		} catch (\Exception $e) {
			$this->logger->error('Failed to add list item', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to add item'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['item' => $this->serializeItem($created, $product->getName() ?? '')], Http::STATUS_CREATED);
	}

	/**
	 * @return array<string, string>
	 */
	private function productNamesByProductId(string $userId): array {
		$names = [];
		foreach ($this->productMapper->findAllByOwner($userId) as $product) {
			$names[$product->getId()] = $product->getName() ?? '';
		}
		return $names;
	}

	private function getCurrentUserId(): ?string {
		return $this->userSession->getUser()?->getUID();
	}

	/**
	 * @return array{id: string, listId: string, productId: string, productName: string, price: ?float, quantity: float, createdAt: ?string}
	 */
	private function serializeItem(ListItemEntity $item, string $productName): array {
		$createdAt = $item->getCreatedAt();
		return [
			'id' => $item->getId(),
			'listId' => $item->getListId() ?? '',
			'productId' => $item->getProductId() ?? '',
			'productName' => $productName,
			'price' => $item->getPrice(),
			'quantity' => $item->getQuantity() ?? 1.0,
			'createdAt' => $createdAt?->format(DateTimeInterface::ATOM),
		];
	}
}
