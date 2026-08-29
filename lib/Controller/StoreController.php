<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use OCA\ByeByeMoneyList\AppInfo\Application;
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
	private IDBConnection $db;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(IRequest $request, StoreMapper $mapper, IDBConnection $db, IUserSession $userSession, LoggerInterface $logger) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
		$this->db = $db;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Get all stores for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED, array{stores: list<array{id: string, name: string}>}|array{message: string}, array{}>
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

		$stores = array_values(array_map(
			fn (StoreEntity $store): array => $this->serializeStore($store),
			$this->mapper->findAllByOwner($userId),
		));

		return new DataResponse(['stores' => $stores], Http::STATUS_OK);
	}

	/**
	 * Create a new store for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $name Store name (required)
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{store: array{id: string, name: string}}|array{message: string}, array{}>
	 *
	 * 201: Store created
	 * 401: Current user is not logged in
	 * 422: Name is missing or empty
	 * 500: Failed to create the store
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/stores')]
	public function create(string $name): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$store = new StoreEntity();
		$store->setId(Uuid::v4());
		$store->setOwner($userId);
		$store->setName($name);

		try {
			$created = $this->mapper->insert($store);
		} catch (\Exception $e) {
			$this->logger->error('Failed to create store', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to create store'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['store' => $this->serializeStore($created)], Http::STATUS_CREATED);
	}

	/**
	 * Update a store for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Store id
	 * @param string $name Store name (required)
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{store: array{id: string, name: string}}|array{message: string}, array{}>
	 *
	 * 200: Store updated
	 * 401: Current user is not logged in
	 * 404: Store not found or not owned by the current user
	 * 422: Name is missing or empty
	 * 500: Failed to update the store
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/stores/{id}')]
	public function update(string $id, string $name): DataResponse {
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

		$store->setName($name);

		try {
			$updated = $this->mapper->update($store);
		} catch (\Exception $e) {
			$this->logger->error('Failed to update store', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to update store'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['store' => $this->serializeStore($updated)], Http::STATUS_OK);
	}

	/**
	 * Delete a store for the current user (nulls out lists.store_id references)
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
	 * @return array{id: string, name: string}
	 */
	private function serializeStore(StoreEntity $store): array {
		return [
			'id' => $store->getId(),
			'name' => $store->getName() ?? '',
		];
	}
}
