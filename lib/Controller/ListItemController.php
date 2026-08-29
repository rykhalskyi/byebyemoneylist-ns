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
use OCA\ByeByeMoneyList\Entity\ProductEntity;
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
	private const MAX_DECIMAL = 9999999999.99;

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
		$productNames = $this->productNamesByProductId(
			$this->productMapper->findByIds(
				array_values(array_map(fn (ListItemEntity $item): string => $item->getProductId() ?? '', $items)),
				$userId,
			),
		);

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
	 * 422: Product not found, price is negative or out of range, or quantity is not positive or out of range
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
		if (!is_finite($quantity) || $quantity <= 0 || $quantity > self::MAX_DECIMAL) {
			return new DataResponse(['message' => 'Quantity must be greater than zero'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		$quantity = round($quantity, 2);

		if ($price !== null && (!is_finite($price) || $price < 0 || $price > self::MAX_DECIMAL)) {
			return new DataResponse(['message' => 'Price must not be negative'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		if ($price !== null) {
			$price = round($price, 2);
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
	 * Update a list item (check state, price, quantity)
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id List id
	 * @param string $itemId Item id
	 * @param ?bool $isChecked Whether the item is checked
	 * @param ?float $price Optional product price (must not be negative)
	 * @param ?float $quantity Quantity as a float (must be greater than zero)
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{item: array{id: string, listId: string, productId: string, productName: string, price: ?float, quantity: float, isChecked: bool, createdAt: ?string}}|array{message: string}, array{}>
	 *
	 * 200: Item updated
	 * 401: Current user is not logged in
	 * 404: List or item not found or not owned by the current user
	 * 422: Price is negative or out of range, or quantity is not positive or out of range
	 * 500: Failed to update the item
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/lists/{id}/items/{itemId}')]
	public function update(string $id, string $itemId, ?bool $isChecked = null, ?float $price = null, ?float $quantity = null): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$list = $this->listMapper->findByIdAndOwner($id, $userId);
		if ($list === null) {
			return new DataResponse(['message' => 'List not found'], Http::STATUS_NOT_FOUND);
		}

		$item = $this->itemMapper->findByIdAndListId($itemId, $id);
		if ($item === null) {
			return new DataResponse(['message' => 'Item not found'], Http::STATUS_NOT_FOUND);
		}

		if ($quantity !== null && (!is_finite($quantity) || $quantity <= 0 || $quantity > self::MAX_DECIMAL)) {
			return new DataResponse(['message' => 'Quantity must be greater than zero'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		if ($quantity !== null) {
			$item->setQuantity(round($quantity, 2));
		}

		if ($price !== null && (!is_finite($price) || $price < 0 || $price > self::MAX_DECIMAL)) {
			return new DataResponse(['message' => 'Price must not be negative'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		if ($price !== null) {
			$item->setPrice(round($price, 2));
		}

		if ($isChecked !== null) {
			$item->setIsChecked($isChecked);
		}

		try {
			$updated = $this->itemMapper->update($item);
		} catch (\Exception $e) {
			$this->logger->error('Failed to update list item', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to update item'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['item' => $this->serializeItem($updated, $this->productName($updated->getProductId() ?? '', $userId))], Http::STATUS_OK);
	}

	/**
	 * Delete a list item
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id List id
	 * @param string $itemId Item id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{}|array{message: string}, array{}>
	 *
	 * 200: Item deleted
	 * 401: Current user is not logged in
	 * 404: List or item not found or not owned by the current user
	 * 500: Failed to delete the item
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/lists/{id}/items/{itemId}')]
	public function destroy(string $id, string $itemId): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$list = $this->listMapper->findByIdAndOwner($id, $userId);
		if ($list === null) {
			return new DataResponse(['message' => 'List not found'], Http::STATUS_NOT_FOUND);
		}

		$item = $this->itemMapper->findByIdAndListId($itemId, $id);
		if ($item === null) {
			return new DataResponse(['message' => 'Item not found'], Http::STATUS_NOT_FOUND);
		}

		try {
			$this->itemMapper->delete($item);
		} catch (\Exception $e) {
			$this->logger->error('Failed to delete list item', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to delete item'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * @param array<ProductEntity> $products
	 *
	 * @return array<string, string>
	 */
	private function productNamesByProductId(array $products): array {
		$names = [];
		foreach ($products as $product) {
			$names[$product->getId()] = $product->getName() ?? '';
		}
		return $names;
	}

	private function getCurrentUserId(): ?string {
		return $this->userSession->getUser()?->getUID();
	}

	private function productName(string $productId, string $userId): string {
		$products = $this->productMapper->findByIds([$productId], $userId);
		foreach ($products as $product) {
			if ($product->getId() === $productId) {
				return $product->getName() ?? '';
			}
		}
		return '';
	}

	/**
	 * @return array{id: string, listId: string, productId: string, productName: string, price: ?float, quantity: float, isChecked: bool, createdAt: ?string}
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
			'isChecked' => $item->getIsChecked() ?? false,
			'createdAt' => $createdAt?->format(DateTimeInterface::ATOM),
		];
	}
}
