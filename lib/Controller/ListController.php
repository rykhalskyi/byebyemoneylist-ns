<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
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
 * @psalm-suppress UnusedClass
 */
class ListController extends OCSController {
	private const MAX_DECIMAL = 9999999999.99;

	private ListMapper $mapper;
	private ListItemMapper $itemMapper;
	private IDBConnection $db;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		ListMapper $mapper,
		ListItemMapper $itemMapper,
		IDBConnection $db,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
		$this->itemMapper = $itemMapper;
		$this->db = $db;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Get all lists for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED, array{lists: list<array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool}>}|array{message: string}, array{}>
	 *
	 * 200: Lists returned
	 * 401: Current user is not logged in
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/lists')]
	public function index(): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$lists = $this->mapper->findAllByOwner($userId);
		$listIds = array_values(array_map(
			fn (ListEntity $list): string => $list->getId(),
			$lists,
		));
		$totals = $this->itemMapper->sumCheckedByListIds($listIds);
		$categoryIdsByList = $this->mapper->findCategoryIdsByListIds($listIds);

		$serialized = array_values(array_map(
			fn (ListEntity $list): array => $this->serializeList(
				$list,
				$totals[$list->getId()] ?? null,
				$categoryIdsByList[$list->getId()] ?? [],
			),
			$lists,
		));

		return new DataResponse(['lists' => $serialized], Http::STATUS_OK);
	}

	/**
	 * Create a new empty shopping list
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $name List name (required)
	 * @param ?string $storeId Optional store id
	 * @param ?string $categoryId Optional single category id (kept for compatibility)
	 * @param list<string> $categoryIds Optional category ids
	 * @param int $position Sort position
	 * @param ?string $purchaseDate Optional purchase date (ISO-8601)
	 * @param bool $isFinished Whether the list is finished
	 * @param ?float $finalTotal Optional final total amount
	 * @param ?string $createDate Optional original creation date (ISO-8601), preserved when provided
	 * @param bool $isRecurring Whether the list recurs
	 * @param string $recurringPeriod Recurring period (WEEK, MONTH, YEAR)
	 * @param bool $isForwardEmpty Whether empty items are forwarded
	 * @param bool $isSubscription Whether the list is a subscription
	 * @param bool $isIncome Whether the list represents income
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{list: array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool}}|array{message: string}, array{}>
	 *
	 * 201: List created
	 * 401: Current user is not logged in
	 * 422: Name is missing or empty, a date is invalid, or finalTotal is negative or out of range
	 * 500: Failed to create the list
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/lists')]
	public function create(string $name, ?string $storeId = null, ?string $categoryId = null, array $categoryIds = [], int $position = 0, ?string $purchaseDate = null, bool $isFinished = false, ?float $finalTotal = null, ?string $createDate = null, bool $isRecurring = false, string $recurringPeriod = 'MONTH', bool $isForwardEmpty = true, bool $isSubscription = false, bool $isIncome = false): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if ($finalTotal !== null && (!is_finite($finalTotal) || $finalTotal < 0 || $finalTotal > self::MAX_DECIMAL)) {
			return new DataResponse(['message' => 'Final total must not be negative'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$parsedCreateDate = $this->parseDate($createDate);
		if ($createDate !== null && $parsedCreateDate === null) {
			return new DataResponse(['message' => 'createDate must be a valid date'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$parsedPurchaseDate = $this->parseDate($purchaseDate);
		if ($purchaseDate !== null && $parsedPurchaseDate === null) {
			return new DataResponse(['message' => 'purchaseDate must be a valid date'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$listCategoryIds = $this->normalizeCategoryIds($categoryId, $categoryIds);
		$position = max(0, $position);

		$now = new DateTime('now', new DateTimeZone('UTC'));
		$list = new ListEntity();
		$list->setId(Uuid::v4());
		$list->setOwner($userId);
		$list->setName($name);
		$list->setStatus('new');
		if ($storeId !== null && $storeId !== '') {
			$list->setStoreId($storeId);
		}
		$list->setCategoryId($listCategoryIds[0] ?? null);
		$list->setPosition($position);
		$list->setIsFinished($isFinished);
		if ($parsedPurchaseDate !== null) {
			$list->setPurchaseDate($parsedPurchaseDate);
		}
		if ($finalTotal !== null) {
			$list->setFinalTotal(round($finalTotal, 2));
		}
		$list->setCreatedAt($parsedCreateDate ?? $now);
		$list->setUpdatedAt($now);
		$list->setIsRecurring($isRecurring);
		$list->setRecurringPeriod($recurringPeriod);
		$list->setIsForwardEmpty($isForwardEmpty);
		$list->setIsSubscription($isSubscription);
		$list->setIsIncome($isIncome);
		if ($isFinished) {
			$list->setStatus('finished');
		}

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;
			$created = $this->mapper->insert($list);
			if ($listCategoryIds !== []) {
				$this->mapper->replaceCategoriesByListId($created->getId(), $listCategoryIds);
			}
			$this->db->commit();
		} catch (\Exception $e) {
			if ($transactionStarted) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to create list', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to create list'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['list' => $this->serializeList($created, null, $listCategoryIds)], Http::STATUS_CREATED);
	}

	/**
	 * Update a shopping list (client-authoritative full state push)
	 *
	 * The list is the source of truth: every provided field is applied and null
	 * scalar fields (storeId, purchaseDate, finalTotal) clear the stored value.
	 * Boolean/recurring fields are only changed when explicitly provided, so a
	 * partial update never resets unrelated flags.
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id List id
	 * @param string $name List name (required)
	 * @param ?string $storeId Store id (null clears)
	 * @param ?string $categoryId Optional single category id (kept for compatibility)
	 * @param list<string> $categoryIds Category ids (empty clears)
	 * @param ?int $position Sort position
	 * @param ?string $purchaseDate Purchase date (ISO-8601), null/empty clears
	 * @param ?float $finalTotal Final total amount (null clears)
	 * @param ?bool $isFinished Whether the list is finished
	 * @param ?bool $isRecurring Whether the list recurs
	 * @param ?string $recurringPeriod Recurring period (WEEK, MONTH, YEAR)
	 * @param ?bool $isForwardEmpty Whether empty items are forwarded
	 * @param ?bool $isSubscription Whether the list is a subscription
	 * @param ?bool $isIncome Whether the list represents income
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{list: array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool}}|array{message: string}, array{}>
	 *
	 * 200: List updated
	 * 401: Current user is not logged in
	 * 404: List not found or not owned by the current user
	 * 422: Name is missing or empty, a date is invalid, or finalTotal is negative or out of range
	 * 500: Failed to update the list
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/lists/{id}')]
	public function update(string $id, string $name, ?string $storeId = null, ?string $categoryId = null, array $categoryIds = [], ?int $position = null, ?string $purchaseDate = null, ?float $finalTotal = null, ?bool $isFinished = null, ?bool $isRecurring = null, ?string $recurringPeriod = null, ?bool $isForwardEmpty = null, ?bool $isSubscription = null, ?bool $isIncome = null): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$list = $this->mapper->findByIdAndOwner($id, $userId);
		if ($list === null) {
			return new DataResponse(['message' => 'List not found'], Http::STATUS_NOT_FOUND);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if ($finalTotal !== null && (!is_finite($finalTotal) || $finalTotal < 0 || $finalTotal > self::MAX_DECIMAL)) {
			return new DataResponse(['message' => 'Final total must not be negative'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$parsedPurchaseDate = $this->parseDate($purchaseDate);
		if ($purchaseDate !== null && $parsedPurchaseDate === null) {
			return new DataResponse(['message' => 'purchaseDate must be a valid date'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$listCategoryIds = $this->normalizeCategoryIds($categoryId, $categoryIds);

		$list->setName($name);
		$list->setStoreId($storeId !== null && $storeId !== '' ? $storeId : null);
		$list->setCategoryId($listCategoryIds[0] ?? null);
		if ($position !== null) {
			$list->setPosition(max(0, $position));
		}
		$list->setPurchaseDate($parsedPurchaseDate);
		if ($finalTotal !== null) {
			$list->setFinalTotal(round($finalTotal, 2));
		}
		if ($isFinished !== null) {
			$list->setIsFinished($isFinished);
			$currentStatus = $list->getStatus() ?? 'new';
			if ($isFinished) {
				$list->setStatus('finished');
			} elseif ($currentStatus === 'finished') {
				$list->setStatus('new');
			}
		}
		if ($isRecurring !== null) {
			$list->setIsRecurring($isRecurring);
		}
		if ($recurringPeriod !== null && $recurringPeriod !== '') {
			$list->setRecurringPeriod($recurringPeriod);
		}
		if ($isForwardEmpty !== null) {
			$list->setIsForwardEmpty($isForwardEmpty);
		}
		if ($isSubscription !== null) {
			$list->setIsSubscription($isSubscription);
		}
		if ($isIncome !== null) {
			$list->setIsIncome($isIncome);
		}
		$list->setUpdatedAt(new DateTime('now', new DateTimeZone('UTC')));

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;
			$updated = $this->mapper->update($list);
			$this->mapper->replaceCategoriesByListId($updated->getId(), $listCategoryIds);
			$this->db->commit();
		} catch (\Exception $e) {
			if ($transactionStarted) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to update list', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to update list'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['list' => $this->serializeList($updated, null, $listCategoryIds)], Http::STATUS_OK);
	}

	/**
	 * Delete a shopping list for the current user (removes its items and list-category links)
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id List id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{}|array{message: string}, array{}>
	 *
	 * 200: List deleted
	 * 401: Current user is not logged in
	 * 404: List not found or not owned by the current user
	 * 500: Failed to delete the list
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/lists/{id}')]
	public function destroy(string $id): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$list = $this->mapper->findByIdAndOwner($id, $userId);
		if ($list === null) {
			return new DataResponse(['message' => 'List not found'], Http::STATUS_NOT_FOUND);
		}

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;
			$this->itemMapper->deleteByListId($id);
			$this->mapper->deleteCategoriesByListId($id);
			$this->mapper->delete($list);
			$this->db->commit();
		} catch (\Exception $e) {
			if ($transactionStarted) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to delete list', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to delete list'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	private function getCurrentUserId(): ?string {
		return $this->userSession->getUser()?->getUID();
	}

	/**
	 * Merge the legacy single categoryId with the categoryIds array, preserving
	 * order and removing blanks/duplicates.
	 *
	 * @param ?string $categoryId
	 * @param array $categoryIds
	 *
	 * @return list<string>
	 */
	private function normalizeCategoryIds(?string $categoryId, array $categoryIds): array {
		$ids = [];
		if ($categoryId !== null && $categoryId !== '') {
			$ids[] = trim($categoryId);
		}
		foreach ($categoryIds as $id) {
			if (!is_string($id)) {
				continue;
			}
			$id = trim($id);
			if ($id === '') {
				continue;
			}
			$ids[] = $id;
		}
		return array_values(array_unique($ids));
	}

	private function parseDate(?string $value): ?DateTime {
		if ($value === null || $value === '') {
			return null;
		}
		try {
			return new DateTime($value);
		} catch (\Exception) {
			return null;
		}
	}

	/**
	 * @param list<string> $categoryIds
	 *
	 * @return array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool}
	 */
	private function serializeList(ListEntity $list, ?float $totalPrice = null, array $categoryIds = []): array {
		$createdAt = $list->getCreatedAt();
		$updatedAt = $list->getUpdatedAt();
		$purchaseDate = $list->getPurchaseDate();
		return [
			'id' => $list->getId(),
			'name' => $list->getName() ?? '',
			'storeId' => $list->getStoreId(),
			'categoryId' => $categoryIds[0] ?? $list->getCategoryId(),
			'categoryIds' => $categoryIds,
			'status' => $list->getStatus() ?? 'new',
			'finalTotal' => $list->getFinalTotal(),
			'totalPrice' => $totalPrice,
			'createdAt' => $createdAt?->format(DateTimeInterface::ATOM),
			'createDate' => $createdAt?->format(DateTimeInterface::ATOM),
			'updatedAt' => $updatedAt?->format(DateTimeInterface::ATOM),
			'purchaseDate' => $purchaseDate?->format(DateTimeInterface::ATOM),
			'position' => $list->getPosition() ?? 0,
			'isFinished' => (bool)$list->getIsFinished(),
			'isSubscription' => (bool)$list->getIsSubscription(),
			'isIncome' => (bool)$list->getIsIncome(),
			'isRecurring' => (bool)$list->getIsRecurring(),
			'recurringPeriod' => $list->getRecurringPeriod() ?? 'MONTH',
			'isForwardEmpty' => (bool)$list->getIsForwardEmpty(),
		];
	}
}
