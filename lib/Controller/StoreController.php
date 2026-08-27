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
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class StoreController extends OCSController {
	private StoreMapper $mapper;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(IRequest $request, StoreMapper $mapper, IUserSession $userSession, LoggerInterface $logger) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
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
	 * @return array{id: string, name: string}
	 */
	private function serializeStore(StoreEntity $store): array {
		return [
			'id' => $store->getId(),
			'name' => $store->getName() ?? '',
		];
	}
}
