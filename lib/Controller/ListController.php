<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
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
class ListController extends OCSController {
	private ListMapper $mapper;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		ListMapper $mapper,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Get all lists for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED, array{lists: list<array{id: string, name: string, storeId: ?string, categoryId: ?string, status: string, finalTotal: ?float, createdAt: ?string}>}|array{message: string}, array{}>
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

		$lists = array_values(array_map(
			fn (ListEntity $list): array => $this->serializeList($list),
			$this->mapper->findAllByOwner($userId),
		));

		return new DataResponse(['lists' => $lists], Http::STATUS_OK);
	}

	/**
	 * Create a new empty shopping list
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $name List name (required)
	 * @param ?string $storeId Optional store id
	 * @param ?string $categoryId Optional category id
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{list: array{id: string, name: string, storeId: ?string, categoryId: ?string, status: string, finalTotal: ?float, createdAt: ?string}}|array{message: string}, array{}>
	 *
	 * 201: List created
	 * 401: Current user is not logged in
	 * 422: Name is missing or empty
	 * 500: Failed to create the list
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/lists')]
	public function create(string $name, ?string $storeId = null, ?string $categoryId = null): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$list = new ListEntity();
		$list->setId(Uuid::v4());
		$list->setOwner($userId);
		$list->setName($name);
		$list->setStatus('new');
		if ($storeId !== null && $storeId !== '') {
			$list->setStoreId($storeId);
		}
		if ($categoryId !== null && $categoryId !== '') {
			$list->setCategoryId($categoryId);
		}
		$list->setCreatedAt(new DateTime('now', new DateTimeZone('UTC')));

		try {
			$created = $this->mapper->insert($list);
		} catch (\Exception $e) {
			$this->logger->error('Failed to create list', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to create list'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['list' => $this->serializeList($created)], Http::STATUS_CREATED);
	}

	private function getCurrentUserId(): ?string {
		return $this->userSession->getUser()?->getUID();
	}

	/**
	 * @return array{id: string, name: string, storeId: ?string, categoryId: ?string, status: string, finalTotal: ?float, createdAt: ?string}
	 */
	private function serializeList(ListEntity $list): array {
		$createdAt = $list->getCreatedAt();
		return [
			'id' => $list->getId(),
			'name' => $list->getName() ?? '',
			'storeId' => $list->getStoreId(),
			'categoryId' => $list->getCategoryId(),
			'status' => $list->getStatus() ?? 'new',
			'finalTotal' => $list->getFinalTotal(),
			'createdAt' => $createdAt?->format(DateTimeInterface::ATOM),
		];
	}
}
