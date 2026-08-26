<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
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
class CategoryController extends OCSController {
	private CategoryMapper $mapper;
	private IUserSession $userSession;

	public function __construct(IRequest $request, CategoryMapper $mapper, IUserSession $userSession) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
		$this->userSession = $userSession;
	}

	/**
	 * Get all categories for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED, array{categories: list<array{id: string, name: string, color: ?string, emoji: ?string, parentId: ?string, income: bool}>}|array{message: string}, array{}>
	 *
	 * 200: Categories returned
	 * 401: Current user is not logged in
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/categories')]
	public function index(): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$categories = array_values(array_map(
			fn (CategoryEntity $category): array => [
				'id' => $category->getId(),
				'name' => $category->getName() ?? '',
				'color' => $category->getColor(),
				'emoji' => $category->getEmoji(),
				'parentId' => $category->getParentId(),
				'income' => $category->getIncome() ?? false,
			],
			$this->mapper->findAllByOwner($userId),
		));

		return new DataResponse(['categories' => $categories], Http::STATUS_OK);
	}
}
