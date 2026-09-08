<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class StoreController extends OCSController {
	private StoreMapper $mapper;
	private CategoryMapper $categoryMapper;
	private IDBConnection $db;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(IRequest $request, StoreMapper $mapper, CategoryMapper $categoryMapper, IDBConnection $db, IUserSession $userSession, LoggerInterface $logger) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
		$this->categoryMapper = $categoryMapper;
		$this->db = $db;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Get all stores for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED, array{stores: list<array{id: string, name: string, address: ?string, categoryIds: list<string>}>}|array{message: string}, array{}>
	 *
	 * 200: Stores returned
	 * 401: Current user is not logged in
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/stores')]
	public function index(): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$stores = $this->mapper->findAllByOwner($userId);
		$storeIds = array_values(array_map(
			fn (StoreEntity $store): string => $store->getId(),
			$stores,
		));
		$categoryIdsByStore = $this->mapper->findCategoryIdsByStoreIds($storeIds);

		$serialized = array_values(array_map(
			fn (StoreEntity $store): array => $this->serializeStore(
				$store,
				$categoryIdsByStore[$store->getId()] ?? [],
			),
			$stores,
		));

		return new DataResponse(['stores' => $serialized], Http::STATUS_OK);
	}

	/**
	 * Create a new store for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $name Store name (required)
	 * @param ?string $address Optional store address (blank/empty clears)
	 * @param list<string> $categoryIds Optional category ids (each must belong to the current user)
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{store: array{id: string, name: string, address: ?string, categoryIds: list<string>}}|array{message: string}, array{}>
	 *
	 * 201: Store created
	 * 401: Current user is not logged in
	 * 422: Name is missing or empty, or a category does not belong to the current user
	 * 500: Failed to create the store
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/stores')]
	public function create(string $name, ?string $address = null, array $categoryIds = []): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$normalizedCategories = $this->validateCategories($userId, $categoryIds);
		if ($normalizedCategories === null) {
			return new DataResponse(['message' => 'Category not found'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$store = new StoreEntity();
		$store->setId(Uuid::v4());
		$store->setOwner($userId);
		$store->setName($name);
		$store->setAddress($this->normalizeBlankToNull($address));

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;
			$created = $this->mapper->insert($store);
			if ($normalizedCategories !== []) {
				$this->mapper->replaceCategoriesByStoreId($created->getId(), $normalizedCategories);
			}
			$this->db->commit();
		} catch (\Exception $e) {
			if ($transactionStarted) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to create store', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to create store'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['store' => $this->serializeStore($created, $normalizedCategories)], Http::STATUS_CREATED);
	}

	/**
	 * Update a store for the current user (client-authoritative full-state push)
	 *
	 * The store is the source of truth: every provided field is applied and a null/blank
	 * `address` clears the stored value; `categoryIds` is fully replaced (empty clears).
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Store id
	 * @param string $name Store name (required)
	 * @param ?string $address Optional store address (null/blank clears)
	 * @param list<string> $categoryIds Optional category ids (each must belong to the current user)
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{store: array{id: string, name: string, address: ?string, categoryIds: list<string>}}|array{message: string}, array{}>
	 *
	 * 200: Store updated
	 * 401: Current user is not logged in
	 * 404: Store not found or not owned by the current user
	 * 422: Name is missing or empty, or a category does not belong to the current user
	 * 500: Failed to update the store
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/stores/{id}')]
	public function update(string $id, string $name, ?string $address = null, array $categoryIds = []): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$store = $this->mapper->findByIdAndOwner($id, $userId);
		if ($store === null) {
			return new DataResponse(['message' => 'Store not found'], Http::STATUS_NOT_FOUND);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$normalizedCategories = $this->validateCategories($userId, $categoryIds);
		if ($normalizedCategories === null) {
			return new DataResponse(['message' => 'Category not found'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$store->setName($name);
		$store->setAddress($this->normalizeBlankToNull($address));

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;
			$updated = $this->mapper->update($store);
			$this->mapper->replaceCategoriesByStoreId($updated->getId(), $normalizedCategories);
			$this->db->commit();
		} catch (\Exception $e) {
			if ($transactionStarted) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to update store', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to update store'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['store' => $this->serializeStore($updated, $normalizedCategories)], Http::STATUS_OK);
	}

	/**
	 * Delete a store for the current user (nulls out lists.store_id references and
	 * removes the store-category links)
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Store id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{}|array{message: string}, array{}>
	 *
	 * 200: Store deleted
	 * 401: Current user is not logged in
	 * 404: Store not found or not owned by the current user
	 * 500: Failed to delete the store
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/stores/{id}')]
	public function destroy(string $id): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$store = $this->mapper->findByIdAndOwner($id, $userId);
		if ($store === null) {
			return new DataResponse(['message' => 'Store not found'], Http::STATUS_NOT_FOUND);
		}

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;

			$qb = $this->db->getQueryBuilder();
			$qb->update('bbml_lists')
				->set('store_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
				->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('store_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)));
			$qb->executeStatement();

			$qb = $this->db->getQueryBuilder();
			$qb->delete('bbml_product_prices')
				->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('store_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)));
			$qb->executeStatement();

			$this->mapper->deleteCategoriesByStoreId($id);
			$this->mapper->delete($store);

			$this->db->commit();
		} catch (\Exception $e) {
			if ($transactionStarted) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to delete store', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to delete store'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * Normalize an optional text field: trim whitespace, treat blank as absent.
	 */
	private function normalizeBlankToNull(?string $value): ?string {
		if ($value === null || trim($value) === '') {
			return null;
		}
		return trim($value);
	}

	/**
	 * Trim/dedupe the provided category ids and verify each one belongs to the
	 * current user (mirroring ProductController's 422 on a foreign category).
	 *
	 * @param array $categoryIds
	 *
	 * @return list<string>|null null when any provided category is not owned
	 */
	private function validateCategories(string $userId, array $categoryIds): ?array {
		$normalized = [];
		foreach ($categoryIds as $categoryId) {
			if (!is_string($categoryId)) {
				continue;
			}
			$categoryId = trim($categoryId);
			if ($categoryId === '') {
				continue;
			}
			$normalized[$categoryId] = true;
		}
		$ids = array_keys($normalized);
		if ($ids === []) {
			return [];
		}

		foreach ($ids as $categoryId) {
			$category = $this->categoryMapper->findByIdAndOwner($categoryId, $userId);
			if ($category === null) {
				return null;
			}
		}
		return $ids;
	}

	/**
	 * @param list<string> $categoryIds
	 *
	 * @return array{id: string, name: string, address: ?string, categoryIds: list<string>}
	 */
	private function serializeStore(StoreEntity $store, array $categoryIds = []): array {
		return [
			'id' => $store->getId(),
			'name' => $store->getName() ?? '',
			'address' => $store->getAddress(),
			'categoryIds' => $categoryIds,
		];
	}
}
