<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\ProductPriceMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\ProductPriceEntity;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Product price records: a price a user recorded for a product (optionally at a store).
 * The Android app syncs its price records one-way (device → server); each record mirrors
 * the app's "one current price per (product, store)" model, so re-syncing a record
 * updates its stored value/date instead of inserting a duplicate.
 *
 * @psalm-suppress UnusedClass
 */
class ProductPriceController extends OCSController {
	private const MAX_DECIMAL = 9999999999.99;

	private ProductPriceMapper $priceMapper;
	private ProductMapper $productMapper;
	private StoreMapper $storeMapper;
	private IDBConnection $db;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		ProductPriceMapper $priceMapper,
		ProductMapper $productMapper,
		StoreMapper $storeMapper,
		IDBConnection $db,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->priceMapper = $priceMapper;
		$this->productMapper = $productMapper;
		$this->storeMapper = $storeMapper;
		$this->db = $db;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Get all price records for one of the current user's products
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $productId Product id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, array{prices: list<array{id: string, productId: string, storeId: ?string, value: float, date: ?string, createdAt: ?string}>}|array{message: string}, array{}>
	 *
	 * 200: Prices returned
	 * 401: Current user is not logged in
	 * 404: Product not found or not owned by the current user
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/products/{productId}/prices')]
	public function index(string $productId): DataResponse {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$product = $this->productMapper->findByIdAndOwner($productId, $userId);
		if ($product === null) {
			return new DataResponse(['message' => 'Product not found'], Http::STATUS_NOT_FOUND);
		}

		$prices = array_map(
			fn (ProductPriceEntity $price): array => $this->serializePrice($price),
			$this->priceMapper->findByProductIdAndOwner($productId, $userId),
		);

		return new DataResponse(['prices' => array_values($prices)], Http::STATUS_OK);
	}

	/**
	 * Create or update price records for the current user (idempotent upsert)
	 *
	 * The Android app keeps one current price per (product, optional store) and updates it
	 * in place, so each record is identified by that pair: re-syncing a record updates its
	 * stored value and date instead of inserting a duplicate. A store that is null/blank
	 * means "product-wide price" (not tied to a store). All records are upserted in one
	 * transaction; an invalid record fails the batch.
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement, RedundantConditionGivenDocblockType, DocblockTypeContradiction, RedundantCastGivenDocblockType
	 *
	 * @param list<array{productId: string, storeId?: ?string, value: float, date: string}> $prices Price records to upsert (required)
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{prices: list<array{id: string, productId: string, storeId: ?string, value: float, date: ?string, createdAt: ?string}>}|array{message: string}, array{}>
	 *
	 * 200: Prices upserted
	 * 401: Current user is not logged in
	 * 422: Prices array is empty or contains an invalid record (product/store not owned, value invalid, date invalid)
	 * 500: Failed to upsert the prices
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/product-prices/batch')]
	public function batchUpsert(array $prices = []): DataResponse {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		if (count($prices) === 0) {
			return new DataResponse(['message' => 'Prices array is required and must not be empty'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		// Pass 1: validate & normalize every record before touching the database.
		$normalized = [];
		foreach ($prices as $index => $record) {
			if (!is_array($record)) {
				return new DataResponse(['message' => "Price record at index {$index} must be an object"], Http::STATUS_UNPROCESSABLE_ENTITY);
			}

			$productId = isset($record['productId']) && is_string($record['productId']) ? trim($record['productId']) : '';
			if ($productId === '') {
				return new DataResponse(['message' => "productId is required at index {$index}"], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			$product = $this->productMapper->findByIdAndOwner($productId, $userId);
			if ($product === null) {
				return new DataResponse(['message' => "Product at index {$index} not found"], Http::STATUS_UNPROCESSABLE_ENTITY);
			}

			$storeId = isset($record['storeId']) && is_string($record['storeId']) && trim($record['storeId']) !== ''
				? trim($record['storeId'])
				: null;
			if ($storeId !== null) {
				$store = $this->storeMapper->findByIdAndOwner($storeId, $userId);
				if ($store === null) {
					return new DataResponse(['message' => "Store at index {$index} not found"], Http::STATUS_UNPROCESSABLE_ENTITY);
				}
			}

			$value = isset($record['value']) && is_numeric($record['value']) ? round((float)$record['value'], 2) : null;
			if ($value === null || $value < 0 || $value > self::MAX_DECIMAL) {
				return new DataResponse(['message' => "Value must not be negative at index {$index}"], Http::STATUS_UNPROCESSABLE_ENTITY);
			}

			$date = $this->parseDate($record['date'] ?? null);
			if ($date === false) {
				return new DataResponse(['message' => "date must be a valid ISO-8601 date at index {$index}"], Http::STATUS_UNPROCESSABLE_ENTITY);
			}

			$normalized[] = [
				'productId' => $productId,
				'storeId' => $storeId,
				'value' => $value,
				'date' => $date,
			];
		}

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;

			$upserted = [];
			foreach ($normalized as $record) {
				$upserted[] = $this->upsertOne($userId, $record['productId'], $record['storeId'], $record['value'], $record['date']);
			}

			$this->db->commit();
		} catch (\Exception $e) {
			if ($transactionStarted) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to upsert product prices', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to upsert product prices'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['prices' => $upserted], Http::STATUS_OK);
	}

	/**
	 * @return array{id: string, productId: string, storeId: ?string, value: float, date: ?string, createdAt: ?string}
	 */
	private function serializePrice(ProductPriceEntity $price): array {
		$date = $price->getPriceDate();
		$createdAt = $price->getCreatedAt();
		return [
			'id' => $price->getId(),
			'productId' => $price->getProductId() ?? '',
			'storeId' => $price->getStoreId(),
			'value' => $price->getValue() ?? 0.0,
			'date' => $date?->format(DateTimeInterface::ATOM),
			'createdAt' => $createdAt?->format(DateTimeInterface::ATOM),
		];
	}

	private function currentUserId(): ?string {
		return $this->userSession->getUser()?->getUID();
	}

	/**
	 * Parse an ISO-8601 date string and normalize it to UTC. Blank values mean "absent".
	 *
	 * @return DateTime|null|false DateTime = valid (UTC), null = absent/blank, false = invalid
	 */
	private function parseDate(?string $value): DateTime|null|false {
		if ($value === null || trim($value) === '') {
			return null;
		}
		$value = trim($value);
		if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})$/', $value)) {
			return false;
		}
		try {
			$date = new DateTime($value);
		} catch (\Exception) {
			return false;
		}
		return $date->setTimezone(new DateTimeZone('UTC'));
	}

	/**
	 * Insert a record, or update the existing record with the same (product, store)
	 * pair. The pair mirrors the app's "one current price per product/store" model.
	 *
	 * @return array{id: string, productId: string, storeId: ?string, value: float, date: ?string, createdAt: ?string}
	 */
	private function upsertOne(string $userId, string $productId, ?string $storeId, float $value, ?DateTime $date): array {
		$existing = $this->priceMapper->findByProductAndStore($productId, $storeId, $userId);
		if ($existing !== null) {
			$existing->setValue($value);
			$existing->setPriceDate($date ?? new DateTime('now', new DateTimeZone('UTC')));
			$this->priceMapper->update($existing);
			return $this->serializePrice($existing);
		}

		$price = new ProductPriceEntity();
		$price->setId(Uuid::v4());
		$price->setOwner($userId);
		$price->setProductId($productId);
		$price->setStoreId($storeId);
		$price->setValue($value);
		$price->setPriceDate($date ?? new DateTime('now', new DateTimeZone('UTC')));
		$price->setCreatedAt(new DateTime('now', new DateTimeZone('UTC')));
		$created = $this->priceMapper->insert($price);
		return $this->serializePrice($created);
	}
}
