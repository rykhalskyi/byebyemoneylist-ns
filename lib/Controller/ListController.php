<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\ListShareMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Entity\ListItemEntity;
use OCA\ByeByeMoneyList\Entity\ListShareEntity;
use OCA\ByeByeMoneyList\Service\ReceiptPictureService;
use OCA\ByeByeMoneyList\Service\Sharing\ListCopyService;
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
	private const MAX_ITEM_NAME_LENGTH = 255;

	private ListMapper $mapper;
	private ListItemMapper $itemMapper;
	private ListShareMapper $shareMapper;
	private ReceiptPictureService $receiptPictureService;
	private ListCopyService $listCopyService;
	private IDBConnection $db;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		ListMapper $mapper,
		ListItemMapper $itemMapper,
		ListShareMapper $shareMapper,
		ReceiptPictureService $receiptPictureService,
		ListCopyService $listCopyService,
		IDBConnection $db,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
		$this->itemMapper = $itemMapper;
		$this->shareMapper = $shareMapper;
		$this->receiptPictureService = $receiptPictureService;
		$this->listCopyService = $listCopyService;
		$this->db = $db;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Get all lists for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED, array{lists: list<array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, kind: string, isActive: bool, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool, hasReceipt: bool, sharedBy: ?string, shareMode: ?string, revoked: bool, hasShares: bool}>}|array{message: string}, array{}>
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
		$ownedListIds = array_values(array_map(
			fn (ListEntity $list): string => $list->getId(),
			$lists,
		));

		$sharedListIds = [];
		foreach ($this->shareMapper->findActiveByOwner($userId) as $share) {
			$listId = $share->getListId();
			if ($listId !== null) {
				$sharedListIds[$listId] = true;
			}
		}

		$incoming = [];
		$visibleSharedListIds = [];
		foreach ($this->shareMapper->findByRecipient($userId) as $share) {
			$listId = $share->getListId();
			if ($listId === null || $share->getOwner() === $userId) {
				continue;
			}
			$list = $this->mapper->findById($listId);
			if ($list === null) {
				continue;
			}
			$incoming[] = ['list' => $list, 'share' => $share];
			if ($share->getStatus() === ListShareEntity::STATUS_ACTIVE) {
				$visibleSharedListIds[$listId] = true;
			}
		}

		$allListIds = array_values(array_unique([...$ownedListIds, ...array_keys($visibleSharedListIds)]));
		$totals = $this->itemMapper->sumCheckedByListIds($allListIds);
		$categoryIdsByList = $this->mapper->findCategoryIdsByListIds($allListIds);

		$serialized = array_map(
			fn (ListEntity $list): array => $this->serializeList(
				$list,
				$totals[$list->getId()] ?? null,
				$categoryIdsByList[$list->getId()] ?? [],
				null,
				null,
				false,
				isset($sharedListIds[$list->getId()]),
			),
			$lists,
		);

		foreach ($incoming as ['list' => $list, 'share' => $share]) {
			$serialized[] = $share->getStatus() === ListShareEntity::STATUS_REVOKED
				? $this->serializeRevokedList($list, $share->getOwner(), $share->getMode())
				: $this->serializeList(
					$list,
					$totals[$list->getId()] ?? null,
					$categoryIdsByList[$list->getId()] ?? [],
					$share->getOwner(),
					$share->getMode(),
					false,
				);
		}

		return new DataResponse(['lists' => array_values($serialized)], Http::STATUS_OK);
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
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{list: array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, kind: string, isActive: bool, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool, hasReceipt: bool, sharedBy: ?string, shareMode: ?string, revoked: bool, hasShares: bool}}|array{message: string}, array{}>
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
		if ($parsedCreateDate === false) {
			return new DataResponse(['message' => 'createDate must be a valid date'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$parsedPurchaseDate = $this->parseDate($purchaseDate);
		if ($parsedPurchaseDate === false) {
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
		$list->setKind($isIncome
			? ListEntity::KIND_INCOME
			: ($isSubscription
				? ListEntity::KIND_SUBSCRIPTION
				: ($isFinished ? ListEntity::KIND_PURCHASE : ListEntity::KIND_NEED_TO_BUY)));
		$list->setIsActive(false);
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
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{list: array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, kind: string, isActive: bool, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool, hasReceipt: bool, sharedBy: ?string, shareMode: ?string, revoked: bool, hasShares: bool}}|array{message: string}, array{}>
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

		if ($isFinished === true && $list->resolveKind() === ListEntity::KIND_NEED_TO_BUY) {
			return new DataResponse(['message' => 'A To Buy list cannot be finished as a purchase'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if ($finalTotal !== null && (!is_finite($finalTotal) || $finalTotal < 0 || $finalTotal > self::MAX_DECIMAL)) {
			return new DataResponse(['message' => 'Final total must not be negative'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$parsedPurchaseDate = $this->parseDate($purchaseDate);
		if ($parsedPurchaseDate === false) {
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
		$list->setFinalTotal($finalTotal === null ? null : round($finalTotal, 2));
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
		if ($isIncome === true) {
			$list->setKind(ListEntity::KIND_INCOME);
		} elseif ($isSubscription === true) {
			$list->setKind(ListEntity::KIND_SUBSCRIPTION);
		} elseif ($isFinished === true && $list->getKind() === null) {
			$list->setKind(ListEntity::KIND_PURCHASE);
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

		$receiptPath = $list->getReceiptPath();

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;
			$this->itemMapper->deleteByListId($id);
			$this->shareMapper->deleteByListId($id);
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

		if ($receiptPath !== null) {
			try {
				$this->receiptPictureService->delete($userId, $receiptPath);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to delete list receipt picture', ['exception' => $e]);
			}
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * Copy a readable list (and its catalog items) into the current user's catalog
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id List id
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{list: array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, kind: string, isActive: bool, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool, hasReceipt: bool, sharedBy: ?string, shareMode: ?string, revoked: bool, hasShares: bool}}|array{message: string}, array{}>
	 *
	 * 201: List copied
	 * 401: Current user is not logged in
	 * 404: List not found or not readable by the current user
	 * 500: Failed to copy the list
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/lists/{id}/copy')]
	public function copy(string $id): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$list = $this->listCopyService->copy($id, $userId);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to copy list', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to copy list'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if ($list === null) {
			return new DataResponse(['message' => 'List not found'], Http::STATUS_NOT_FOUND);
		}

		$categoryIds = $this->mapper->findCategoryIdsByListIds([$list->getId()])[$list->getId()] ?? [];

		return new DataResponse(['list' => $this->serializeList($list, null, $categoryIds)], Http::STATUS_CREATED);
	}

	/**
	 * Get the current user's active To Buy list
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED, array{list: array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, kind: string, isActive: bool, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool, hasReceipt: bool, sharedBy: ?string, shareMode: ?string, revoked: bool, hasShares: bool}|null}|array{message: string}, array{}>
	 *
	 * 200: Active To Buy list returned (or null)
	 * 401: Current user is not logged in
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/lists/to-buy')]
	public function activeToBuy(): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$list = $this->mapper->findActiveToBuyList($userId);
		if ($list === null) {
			return new DataResponse(['list' => null], Http::STATUS_OK);
		}

		$categoryIds = $this->mapper->findCategoryIdsByListIds([$list->getId()])[$list->getId()] ?? [];

		return new DataResponse(['list' => $this->serializeList($list, null, $categoryIds)], Http::STATUS_OK);
	}

	/**
	 * Create a new active To Buy list, optionally carrying over item names
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param ?string $prefix Localized "To Buy" prefix (client-supplied)
	 * @param list<string> $itemNames Optional plain-text item names to carry over
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{list: array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, kind: string, isActive: bool, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool, hasReceipt: bool, sharedBy: ?string, shareMode: ?string, revoked: bool, hasShares: bool}}|array{message: string}, array{}>
	 *
	 * 201: To Buy list created and activated
	 * 401: Current user is not logged in
	 * 422: An item name is too long
	 * 500: Failed to create the list
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/lists/to-buy')]
	public function createToBuy(?string $prefix = null, array $itemNames = []): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$names = $this->normalizeItemNames($itemNames);
		if ($names === false) {
			return new DataResponse(['message' => 'Item name is too long'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$prefix = trim($prefix ?? '');
		if ($prefix === '') {
			$prefix = 'To Buy';
		}

		$now = new DateTime('now', new DateTimeZone('UTC'));
		$list = new ListEntity();
		$list->setId(Uuid::v4());
		$list->setOwner($userId);
		$list->setName($prefix . ' ' . $now->format('d.m.Y'));
		$list->setStatus('new');
		$list->setKind(ListEntity::KIND_NEED_TO_BUY);
		$list->setIsActive(true);
		$list->setIsFinished(false);
		$list->setIsRecurring(false);
		$list->setRecurringPeriod('MONTH');
		$list->setIsForwardEmpty(true);
		$list->setIsSubscription(false);
		$list->setIsIncome(false);
		$list->setPosition(0);
		$list->setCreatedAt($now);
		$list->setUpdatedAt($now);

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;
			$created = $this->mapper->insertActiveToBuyList($list);
			foreach ($names as $index => $itemName) {
				$item = new ListItemEntity();
				$item->setId(Uuid::v4());
				$item->setOwner($userId);
				$item->setListId($created->getId());
				$item->setProductId(null);
				$item->setQuantity(1.0);
				$item->setIsChecked(false);
				$item->setStatus('added');
				$item->setPosition($index);
				$item->setCustomName($itemName);
				$item->setCreatedAt($now);
				$item->setUpdatedAt($now);
				$this->itemMapper->insert($item);
			}
			$this->db->commit();
		} catch (\Throwable $e) {
			if ($transactionStarted) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to create To Buy list', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to create To Buy list'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['list' => $this->serializeList($created)], Http::STATUS_CREATED);
	}

	/**
	 * @return list<string>|false
	 */
	private function normalizeItemNames(array $itemNames): array|false {
		$names = [];
		foreach ($itemNames as $itemName) {
			if (!is_string($itemName)) {
				continue;
			}
			$itemName = trim($itemName);
			if ($itemName === '') {
				continue;
			}
			if (mb_strlen($itemName) > self::MAX_ITEM_NAME_LENGTH) {
				return false;
			}
			$names[] = $itemName;
		}
		return $names;
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

	/**
	 * Parse an ISO-8601 date (with optional fractional seconds and a 'Z' or
	 * ±hh:mm offset) and normalize it to UTC. Blank values mean "absent".
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
	 * @param list<string> $categoryIds
	 *
	 * @return array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, kind: string, isActive: bool, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool, hasReceipt: bool, sharedBy: ?string, shareMode: ?string, revoked: bool, hasShares: bool}
	 */
	private function serializeList(ListEntity $list, ?float $totalPrice = null, array $categoryIds = [], ?string $sharedBy = null, ?string $shareMode = null, bool $revoked = false, bool $hasShares = false): array {
		$createdAt = $list->getCreatedAt();
		$updatedAt = $list->getUpdatedAt();
		$purchaseDate = $list->getPurchaseDate();
		$primaryId = $list->getCategoryId();
		if ($primaryId !== null && $primaryId !== '' && in_array($primaryId, $categoryIds, true)) {
			$categoryIds = array_merge(
				[$primaryId],
				array_filter($categoryIds, static fn (string $id): bool => $id !== $primaryId),
			);
		}
		return [
			'id' => $list->getId(),
			'name' => $list->getName() ?? '',
			'storeId' => $list->getStoreId(),
			'categoryId' => $primaryId ?? ($categoryIds[0] ?? null),
			'categoryIds' => $categoryIds,
			'status' => $list->getStatus() ?? 'new',
			'kind' => $list->resolveKind(),
			'isActive' => (bool)$list->getIsActive(),
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
			'hasReceipt' => $list->getReceiptPath() !== null,
			'sharedBy' => $sharedBy,
			'shareMode' => $shareMode,
			'revoked' => $revoked,
			'hasShares' => $hasShares,
		];
	}

	/**
	 * Name-only placeholder for a revoked share: the guest keeps the list name and
	 * the fact that it is revoked, but no other list metadata.
	 *
	 * @return array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, kind: string, isActive: bool, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool, hasReceipt: bool, sharedBy: ?string, shareMode: ?string, revoked: bool, hasShares: bool}
	 */
	private function serializeRevokedList(ListEntity $list, ?string $sharedBy = null, ?string $shareMode = null): array {
		return [
			'id' => $list->getId(),
			'name' => $list->getName() ?? '',
			'storeId' => null,
			'categoryId' => null,
			'categoryIds' => [],
			'status' => 'new',
			'kind' => $list->resolveKind(),
			'isActive' => false,
			'finalTotal' => null,
			'totalPrice' => null,
			'createdAt' => null,
			'createDate' => null,
			'updatedAt' => null,
			'purchaseDate' => null,
			'position' => 0,
			'isFinished' => false,
			'isSubscription' => false,
			'isIncome' => false,
			'isRecurring' => false,
			'recurringPeriod' => 'MONTH',
			'isForwardEmpty' => true,
			'hasReceipt' => false,
			'sharedBy' => $sharedBy,
			'shareMode' => $shareMode,
			'revoked' => true,
			'hasShares' => false,
		];
	}
}
