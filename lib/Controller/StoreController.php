<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-suppress UnusedClass
 */
class StoreController extends OCSController {
	private StoreMapper $mapper;
	private IUserSession $userSession;

	public function __construct(IRequest $request, StoreMapper $mapper, IUserSession $userSession) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
		$this->userSession = $userSession;
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
			fn (StoreEntity $store): array => [
				'id' => $store->getId(),
				'name' => $store->getName() ?? '',
			],
			$this->mapper->findAllByOwner($userId),
		));

		return new DataResponse(['stores' => $stores], Http::STATUS_OK);
	}
}
