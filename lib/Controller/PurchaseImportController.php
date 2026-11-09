<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\PurchaseImportCategoryMapper;
use OCA\ByeByeMoneyList\Db\PurchaseImportItemMapper;
use OCA\ByeByeMoneyList\Db\PurchaseImportMapper;
use OCA\ByeByeMoneyList\Entity\PurchaseImportCategoryEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportItemEntity;
use OCA\ByeByeMoneyList\Service\Import\PurchaseImportMatcher;
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
 * One-way purchase import inbox.
 *
 * Slice A: accept/replace an uploaded purchase list, list imports, show an import
 * with deterministic (exact) mapping suggestions. Approval, fuzzy matching, AI
 * suggestions and the web UI are later slices.
 *
 * @psalm-suppress UnusedClass
 */
class PurchaseImportController extends OCSController {
	private const SCHEMA_VERSION = 'bbml.purchase-upload/1';
	private const MAX_ITEMS = 1000;
	private const MAX_DECIMAL = 9999999999.99;
	private const STATUSES = [
		PurchaseImportEntity::STATUS_PENDING,
		PurchaseImportEntity::STATUS_IN_REVIEW,
		PurchaseImportEntity::STATUS_APPROVED,
		PurchaseImportEntity::STATUS_DISCARDED,
	];

	private PurchaseImportMapper $importMapper;
	private PurchaseImportItemMapper $itemMapper;
	private PurchaseImportCategoryMapper $categoryMapper;
	private PurchaseImportMatcher $matcher;
	private IDBConnection $db;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		PurchaseImportMapper $importMapper,
		PurchaseImportItemMapper $itemMapper,
		PurchaseImportCategoryMapper $categoryMapper,
		PurchaseImportMatcher $matcher,
		IDBConnection $db,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->importMapper = $importMapper;
		$this->itemMapper = $itemMapper;
		$this->categoryMapper = $categoryMapper;
		$this->matcher = $matcher;
		$this->db = $db;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Accept a purchase list from the Android client
	 *
	 * The request is idempotent by `(owner, listId)`: a resend while the import is
	 * pending/reviewing replaces its content; once approved/discarded it is
	 * returned as a duplicate without changes.
	 *
	 * @param string $schema Contract version (must be `bbml.purchase-upload/1`)
	 * @param string $listId Client-generated UUID v4 (becomes the final list id)
	 * @param array<string, mixed> $list List metadata
	 * @param ?string $clientId Optional client install id
	 * @param list<array<string, mixed>> $items Raw line items
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array<string, mixed>, array{}>
	 *
	 * 201: Import created
	 * 200: Import replaced, or already approved (duplicate)
	 * 401: Current user is not logged in
	 * 422: Invalid payload
	 * 500: Failed to store the import
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/purchase-imports')]
	public function create(
		string $schema = '',
		string $listId = '',
		array $list = [],
		?string $clientId = null,
		array $items = [],
	): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$validated = $this->validatePayload($schema, $listId, $list, $clientId, $items);
		if ($validated instanceof DataResponse) {
			return $validated;
		}

		$existing = $this->importMapper->findByOwnerAndClientListId($userId, $listId);
		if ($existing !== null && in_array($existing->getStatus(), [
			PurchaseImportEntity::STATUS_APPROVED,
			PurchaseImportEntity::STATUS_DISCARDED,
		], true)) {
			return new DataResponse([
				'import' => $this->serializeImport(
					$existing,
					$this->itemMapper->findByImportId($existing->getId()),
					$this->categoryMapper->findByImportId($existing->getId()),
				),
				'duplicate' => true,
			], Http::STATUS_OK);
		}

		$now = new DateTime('now', new DateTimeZone('UTC'));
		$import = $existing ?? new PurchaseImportEntity();
		$isNew = $existing === null;

		if ($isNew) {
			$import->setId(Uuid::v4());
			$import->setOwner($userId);
			$import->setClientListId($listId);
			$import->setCreatedAt($now);
		}

		$import->setClientId($clientId === null ? null : trim($clientId));
		$import->setSchemaVersion(self::SCHEMA_VERSION);
		$import->setName($validated['name']);
		$import->setStatus(PurchaseImportEntity::STATUS_PENDING);
		$import->setStoreName($validated['storeName']);
		$import->setStoreAddress($validated['storeAddress']);
		$import->setStoreReceiptName($validated['storeReceiptName']);
		$import->setCurrency($validated['currency']);
		$import->setFinalTotal($validated['finalTotal']);
		$import->setListCreatedAt($validated['listCreatedAt']);
		$import->setPurchaseDate($validated['purchaseDate']);
		$import->setRawJson($validated['rawJson']);
		$import->setMappedStoreId(null);
		$import->setStoreAction(PurchaseImportEntity::ACTION_PENDING);
		$import->setSuggestedStoreId(null);
		$import->setSuggestedStoreName(null);
		$import->setStoreMatchSource(null);
		$import->setStoreConfidence(null);
		$import->setApprovedListId(null);
		$import->setApprovedAt(null);
		$import->setUpdatedAt($now);

		$itemEntities = $this->buildItemEntities($validated['items'], $import->getId(), $userId, $now);
		$categoryEntities = $this->buildCategoryEntities($validated['categories'], $import->getId(), $userId, $now);

		$started = false;
		try {
			$this->db->beginTransaction();
			$started = true;

			if ($isNew) {
				$import = $this->importMapper->insert($import);
				foreach ($itemEntities as $item) {
					$item->setImportId($import->getId());
				}
				foreach ($categoryEntities as $category) {
					$category->setImportId($import->getId());
				}
			} else {
				$this->itemMapper->deleteByImportId($import->getId());
				$this->categoryMapper->deleteByImportId($import->getId());
				$import = $this->importMapper->update($import);
			}

			foreach ($itemEntities as $item) {
				$this->itemMapper->insert($item);
			}
			foreach ($categoryEntities as $category) {
				$this->categoryMapper->insert($category);
			}

			try {
				$this->matcher->suggestForImport($import, $itemEntities, $categoryEntities, $userId);
				$this->importMapper->update($import);
				foreach ($itemEntities as $item) {
					$this->itemMapper->update($item);
				}
				foreach ($categoryEntities as $category) {
					$this->categoryMapper->update($category);
				}
			} catch (\Throwable $e) {
				// Suggestions are best-effort; never fail an upload because matching failed.
				$this->logger->warning('Failed to compute purchase import suggestions', ['exception' => $e]);
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			if ($started) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to store purchase import', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to store the import'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse([
			'import' => $this->serializeImport($import, $itemEntities, $categoryEntities),
			'duplicate' => false,
		], $isNew ? Http::STATUS_CREATED : Http::STATUS_OK);
	}

	/**
	 * List purchase imports for the current user.
	 *
	 * @param ?string $status Optional status filter (`pending`, `in_review`, `approved`, `discarded`)
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY, array<string, mixed>, array{}>
	 *
	 * 200: Imports returned
	 * 401: Current user is not logged in
	 * 422: Invalid status filter
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/purchase-imports')]
	public function index(?string $status = null): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}
		if ($status !== null && $status !== '' && !in_array($status, self::STATUSES, true)) {
			return new DataResponse(['message' => 'Invalid status'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$imports = $this->importMapper->findAllByOwner($userId, $status);
		$serialized = array_map(function (PurchaseImportEntity $import): array {
			$id = $import->getId();
			return $this->serializeImportSummary(
				$import,
				$this->itemMapper->countByImportId($id),
				$this->categoryMapper->countByImportId($id),
			);
		}, $imports);

		return new DataResponse(['imports' => array_values($serialized)], Http::STATUS_OK);
	}

	/**
	 * Show one purchase import with its items and current suggestions.
	 *
	 * @param string $id Import id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, array<string, mixed>, array{}>
	 *
	 * 200: Import returned
	 * 401: Current user is not logged in
	 * 404: Import not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/purchase-imports/{id}')]
	public function show(string $id): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$import = $this->importMapper->findByIdAndOwner($id, $userId);
		if ($import === null) {
			return new DataResponse(['message' => 'Import not found'], Http::STATUS_NOT_FOUND);
		}

		return new DataResponse([
			'import' => $this->serializeImport(
				$import,
				$this->itemMapper->findByImportId($id),
				$this->categoryMapper->findByImportId($id),
			),
		], Http::STATUS_OK);
	}

	private function getCurrentUserId(): ?string {
		return $this->userSession->getUser()?->getUID();
	}

	/**
	 * @param array<string, mixed> $list
	 * @param array<array-key, mixed> $items
	 *
	 * @return array{
	 *   name: string,
	 *   storeName: ?string,
	 *   storeAddress: ?string,
	 *   storeReceiptName: ?string,
	 *   currency: ?string,
	 *   finalTotal: ?float,
	 *   listCreatedAt: ?DateTime,
	 *   purchaseDate: ?DateTime,
	 *   rawJson: string,
	 *   items: list<array{position: int, lineId: ?string, name: string, quantity: float, unitPrice: ?float, discount: ?float, isCoupon: bool, barcode: ?string, categoryHint: ?string}>,
	 *   categories: list<array{name: string, isIncome: bool}>
	 * }|DataResponse
	 */
	private function validatePayload(
		string $schema,
		string $listId,
		array $list,
		?string $clientId,
		array $items,
	): array|DataResponse {
		if (trim($schema) !== self::SCHEMA_VERSION) {
			return new DataResponse(['message' => 'Unsupported schema'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		if (!$this->isUuidV4($listId)) {
			return new DataResponse(['message' => 'listId must be a UUID v4'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		if ($clientId !== null && mb_strlen($clientId) > 64) {
			return new DataResponse(['message' => 'clientId is too long'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$name = isset($list['name']) && is_string($list['name']) ? trim($list['name']) : '';
		if ($name === '' || mb_strlen($name) > 255) {
			return new DataResponse(['message' => 'List name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$finalTotal = $list['finalTotal'] ?? null;
		if ($finalTotal !== null) {
			if (!is_numeric($finalTotal)) {
				return new DataResponse(['message' => 'finalTotal must be a number'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			$finalTotal = (float)$finalTotal;
			if (!is_finite($finalTotal) || $finalTotal < 0 || $finalTotal > self::MAX_DECIMAL) {
				return new DataResponse(['message' => 'finalTotal is out of range'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
		}

		$listCreatedAt = $this->parseDate($list['createdAt'] ?? null);
		if ($listCreatedAt === false) {
			return new DataResponse(['message' => 'createdAt must be a valid date'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		$purchaseDate = $this->parseDate($list['purchaseDate'] ?? null);
		if ($purchaseDate === false) {
			return new DataResponse(['message' => 'purchaseDate must be a valid date'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$store = $list['store'] ?? null;
		if ($store !== null && !is_array($store)) {
			return new DataResponse(['message' => 'store must be an object'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		$storeName = is_array($store) ? $this->trimmedString($store['name'] ?? null, 255) : null;
		$storeAddress = is_array($store) ? $this->trimmedString($store['address'] ?? null, 512) : null;
		$storeReceiptName = is_array($store) ? $this->trimmedString($store['receiptName'] ?? null, 255) : null;
		if (($storeName === false) || ($storeAddress === false) || ($storeReceiptName === false)) {
			return new DataResponse(['message' => 'Store fields are too long'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$currency = $this->trimmedString($list['currency'] ?? null, 8);
		if ($currency === false) {
			return new DataResponse(['message' => 'currency is too long'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if (count($items) > self::MAX_ITEMS) {
			return new DataResponse(['message' => 'Too many items'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$itemRows = [];
		foreach ($items as $index => $item) {
			if (!is_array($item)) {
				return new DataResponse(['message' => 'Each item must be an object'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			$rawName = isset($item['name']) && is_string($item['name']) ? trim($item['name']) : '';
			if ($rawName === '' || mb_strlen($rawName) > 255) {
				return new DataResponse(['message' => 'Item name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			$quantity = $item['quantity'] ?? 1;
			if (!is_numeric($quantity)) {
				return new DataResponse(['message' => 'Item quantity must be a number'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			$quantity = (float)$quantity;
			if (!is_finite($quantity) || $quantity <= 0 || $quantity > self::MAX_DECIMAL) {
				return new DataResponse(['message' => 'Item quantity is out of range'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}

			$unitPrice = $this->nullableAmount($item['unitPrice'] ?? null);
			$discount = $this->nullableAmount($item['discount'] ?? null);
			if ($unitPrice === false || $discount === false) {
				return new DataResponse(['message' => 'Item amount is out of range'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}

			$lineId = $this->trimmedString($item['lineId'] ?? null, 64);
			$barcode = $this->trimmedString($item['barcode'] ?? null, 64);
			$categoryHint = $this->trimmedString($item['categoryHint'] ?? null, 255);
			if ($lineId === false || $barcode === false || $categoryHint === false) {
				return new DataResponse(['message' => 'Item field is too long'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}

			$itemRows[] = [
				'position' => is_int($index) ? $index : count($itemRows),
				'lineId' => $lineId,
				'name' => $rawName,
				'quantity' => $quantity,
				'unitPrice' => $unitPrice,
				'discount' => $discount,
				'isCoupon' => (bool)($item['isCoupon'] ?? false),
				'barcode' => $barcode,
				'categoryHint' => $categoryHint,
			];
		}

		$categoryInput = $list['categories'] ?? [];
		if (!is_array($categoryInput)) {
			return new DataResponse(['message' => 'categories must be a list'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		$categoryRows = [];
		foreach ($categoryInput as $category) {
			if (!is_array($category)) {
				return new DataResponse(['message' => 'Each category must be an object'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			$rawName = isset($category['name']) && is_string($category['name']) ? trim($category['name']) : '';
			if ($rawName === '' || mb_strlen($rawName) > 255) {
				return new DataResponse(['message' => 'Category name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			$categoryRows[] = [
				'name' => $rawName,
				'isIncome' => (bool)($category['isIncome'] ?? false),
			];
		}

		$rawJson = json_encode([
			'schema' => self::SCHEMA_VERSION,
			'clientId' => $clientId,
			'listId' => $listId,
			'list' => $list,
			'items' => $items,
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($rawJson === false) {
			return new DataResponse(['message' => 'Invalid payload'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		if (strlen($rawJson) > 1024 * 1024) {
			return new DataResponse(['message' => 'Payload is too large'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		return [
			'name' => $name,
			'storeName' => $storeName,
			'storeAddress' => $storeAddress,
			'storeReceiptName' => $storeReceiptName,
			'currency' => $currency,
			'finalTotal' => $finalTotal,
			'listCreatedAt' => $listCreatedAt,
			'purchaseDate' => $purchaseDate,
			'rawJson' => $rawJson,
			'items' => $itemRows,
			'categories' => $categoryRows,
		];
	}

	/**
	 * @param list<array{position: int, lineId: ?string, name: string, quantity: float, unitPrice: ?float, discount: ?float, isCoupon: bool, barcode: ?string, categoryHint: ?string}> $rows
	 *
	 * @return PurchaseImportItemEntity[]
	 */
	private function buildItemEntities(array $rows, string $importId, string $owner, DateTime $now): array {
		$entities = [];
		foreach ($rows as $row) {
			$item = new PurchaseImportItemEntity();
			$item->setId(Uuid::v4());
			$item->setImportId($importId);
			$item->setOwner($owner);
			$item->setPosition($row['position']);
			$item->setLineId($row['lineId']);
			$item->setRawName($row['name']);
			$item->setQuantity($row['quantity']);
			$item->setUnitPrice($row['unitPrice']);
			$item->setDiscount($row['discount']);
			$item->setIsCoupon($row['isCoupon']);
			$item->setBarcode($row['barcode']);
			$item->setCategoryHint($row['categoryHint']);
			$item->setAction(PurchaseImportEntity::ACTION_PENDING);
			$item->setCreatedAt($now);
			$item->setUpdatedAt($now);
			$entities[] = $item;
		}
		return $entities;
	}

	/**
	 * @param list<array{name: string, isIncome: bool}> $rows
	 *
	 * @return PurchaseImportCategoryEntity[]
	 */
	private function buildCategoryEntities(array $rows, string $importId, string $owner, DateTime $now): array {
		$entities = [];
		foreach ($rows as $row) {
			$category = new PurchaseImportCategoryEntity();
			$category->setId(Uuid::v4());
			$category->setImportId($importId);
			$category->setOwner($owner);
			$category->setRawName($row['name']);
			$category->setIsIncome($row['isIncome']);
			$category->setAction(PurchaseImportEntity::ACTION_PENDING);
			$category->setCreatedAt($now);
			$category->setUpdatedAt($now);
			$entities[] = $category;
		}
		return $entities;
	}

	/**
	 * @param PurchaseImportItemEntity[] $items
	 * @param PurchaseImportCategoryEntity[] $categories
	 *
	 * @return array<string, mixed>
	 */
	private function serializeImport(PurchaseImportEntity $import, array $items, array $categories): array {
		return [
			'id' => $import->getId(),
			'clientListId' => $import->getClientListId(),
			'clientId' => $import->getClientId(),
			'schemaVersion' => $import->getSchemaVersion(),
			'name' => $import->getName(),
			'status' => $import->getStatus(),
			'storeName' => $import->getStoreName(),
			'storeAddress' => $import->getStoreAddress(),
			'storeReceiptName' => $import->getStoreReceiptName(),
			'currency' => $import->getCurrency(),
			'finalTotal' => $this->numberOrNull($import->getFinalTotal()),
			'listCreatedAt' => $this->dateOrNull($import->getListCreatedAt()),
			'purchaseDate' => $this->dateOrNull($import->getPurchaseDate()),
			'createdAt' => $this->dateOrNull($import->getCreatedAt()),
			'updatedAt' => $this->dateOrNull($import->getUpdatedAt()),
			'approvedListId' => $import->getApprovedListId(),
			'approvedAt' => $this->dateOrNull($import->getApprovedAt()),
			'storeMapping' => [
				'mappedStoreId' => $import->getMappedStoreId(),
				'action' => $import->getStoreAction(),
				'suggestedStoreId' => $import->getSuggestedStoreId(),
				'suggestedStoreName' => $import->getSuggestedStoreName(),
				'source' => $import->getStoreMatchSource(),
				'confidence' => $this->numberOrNull($import->getStoreConfidence()),
			],
			'items' => array_map($this->serializeItem(...), $items),
			'categories' => array_map($this->serializeCategory(...), $categories),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializeImportSummary(PurchaseImportEntity $import, int $itemCount, int $categoryCount): array {
		return [
			'id' => $import->getId(),
			'clientListId' => $import->getClientListId(),
			'clientId' => $import->getClientId(),
			'name' => $import->getName(),
			'status' => $import->getStatus(),
			'storeName' => $import->getStoreName(),
			'currency' => $import->getCurrency(),
			'finalTotal' => $this->numberOrNull($import->getFinalTotal()),
			'purchaseDate' => $this->dateOrNull($import->getPurchaseDate()),
			'createdAt' => $this->dateOrNull($import->getCreatedAt()),
			'updatedAt' => $this->dateOrNull($import->getUpdatedAt()),
			'approvedListId' => $import->getApprovedListId(),
			'approvedAt' => $this->dateOrNull($import->getApprovedAt()),
			'itemCount' => $itemCount,
			'categoryCount' => $categoryCount,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializeItem(PurchaseImportItemEntity $item): array {
		return [
			'id' => $item->getId(),
			'position' => $item->getPosition(),
			'lineId' => $item->getLineId(),
			'rawName' => $item->getRawName(),
			'quantity' => $this->numberOrNull($item->getQuantity()),
			'unitPrice' => $this->numberOrNull($item->getUnitPrice()),
			'discount' => $this->numberOrNull($item->getDiscount()),
			'isCoupon' => (bool)$item->getIsCoupon(),
			'barcode' => $item->getBarcode(),
			'categoryHint' => $item->getCategoryHint(),
			'action' => $item->getAction(),
			'mappedProductId' => $item->getMappedProductId(),
			'mappedCategoryId' => $item->getMappedCategoryId(),
			'newProductName' => $item->getNewProductName(),
			'suggestion' => [
				'productId' => $item->getSuggestedProductId(),
				'productName' => $item->getSuggestedProductName(),
				'categoryId' => $item->getSuggestedCategoryId(),
				'source' => $item->getMatchSource(),
				'confidence' => $this->numberOrNull($item->getConfidence()),
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializeCategory(PurchaseImportCategoryEntity $category): array {
		return [
			'id' => $category->getId(),
			'rawName' => $category->getRawName(),
			'isIncome' => (bool)$category->getIsIncome(),
			'action' => $category->getAction(),
			'mappedCategoryId' => $category->getMappedCategoryId(),
			'suggestion' => [
				'categoryId' => $category->getSuggestedCategoryId(),
				'source' => $category->getMatchSource(),
				'confidence' => $this->numberOrNull($category->getConfidence()),
			],
		];
	}

	private function isUuidV4(string $value): bool {
		return preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			$value,
		) === 1;
	}

	private function trimmedString(mixed $value, int $maxLength): string|null|false {
		if ($value === null || $value === '') {
			return null;
		}
		if (!is_string($value)) {
			return false;
		}
		$trimmed = trim($value);
		if ($trimmed === '') {
			return null;
		}
		if (mb_strlen($trimmed) > $maxLength) {
			return false;
		}
		return $trimmed;
	}

	private function nullableAmount(mixed $value): float|null|false {
		if ($value === null || $value === '') {
			return null;
		}
		if (!is_numeric($value)) {
			return false;
		}
		$amount = (float)$value;
		if (!is_finite($amount) || $amount < 0 || $amount > self::MAX_DECIMAL) {
			return false;
		}
		return $amount;
	}

	private function parseDate(mixed $value): DateTime|null|false {
		if ($value === null) {
			return null;
		}
		if (!is_string($value) || trim($value) === '') {
			return null;
		}
		$value = trim($value);
		if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})$/', $value) !== 1) {
			return false;
		}
		try {
			$date = new DateTime($value);
		} catch (\Exception) {
			return false;
		}
		return $date->setTimezone(new DateTimeZone('UTC'));
	}

	private function numberOrNull(mixed $value): ?float {
		return $value === null ? null : (float)$value;
	}

	private function dateOrNull(?DateTime $value): ?string {
		return $value?->format(DateTimeInterface::ATOM);
	}
}
