<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
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
class CategoryController extends OCSController {
	private CategoryMapper $mapper;
	private IDBConnection $db;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(IRequest $request, CategoryMapper $mapper, IDBConnection $db, IUserSession $userSession, LoggerInterface $logger) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
		$this->db = $db;
		$this->userSession = $userSession;
		$this->logger = $logger;
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
			fn (CategoryEntity $category): array => $this->serializeCategory($category),
			$this->mapper->findAllByOwner($userId),
		));

		return new DataResponse(['categories' => $categories], Http::STATUS_OK);
	}

	/**
	 * Create a new category for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $name Category name (required)
	 * @param ?string $color Hex color (e.g. #ff0000)
	 * @param ?string $emoji Emoji for the category
	 * @param ?string $parentId Optional parent category id (must belong to the current user)
	 * @param bool $income Whether the category records income
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{category: array{id: string, name: string, color: ?string, emoji: ?string, parentId: ?string, income: bool}}|array{message: string}, array{}>
	 *
	 * 201: Category created
	 * 401: Current user is not logged in
	 * 422: Name is missing or empty, parent does not exist, or color is invalid
	 * 500: Failed to create the category
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/categories')]
	public function create(string $name, ?string $color = null, ?string $emoji = null, ?string $parentId = null, bool $income = false): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if ($color !== null && $color !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
			return new DataResponse(['message' => 'Color must be a hex color like #ff0000'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if ($parentId !== null && $parentId !== '') {
			$parent = $this->mapper->findByIdAndOwner($parentId, $userId);
			if ($parent === null) {
				return new DataResponse(['message' => 'Parent category not found'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
		}

		$category = new CategoryEntity();
		$category->setId(Uuid::v4());
		$category->setOwner($userId);
		$category->setName($name);
		if ($color !== null && $color !== '') {
			$category->setColor($color);
		}
		if ($emoji !== null && $emoji !== '') {
			$category->setEmoji($emoji);
		}
		if ($parentId !== null && $parentId !== '') {
			$category->setParentId($parentId);
		}
		$category->setIncome($income);

		try {
			$created = $this->mapper->insert($category);
		} catch (\Exception $e) {
			$this->logger->error('Failed to create category', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to create category'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['category' => $this->serializeCategory($created)], Http::STATUS_CREATED);
	}

	/**
	 * Update a category for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Category id
	 * @param string $name Category name (required)
	 * @param ?string $color Hex color (e.g. #ff0000)
	 * @param ?string $emoji Emoji for the category
	 * @param ?string $parentId Optional parent category id (must belong to the current user)
	 * @param bool $income Whether the category records income
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{category: array{id: string, name: string, color: ?string, emoji: ?string, parentId: ?string, income: bool}}|array{message: string}, array{}>
	 *
	 * 200: Category updated
	 * 401: Current user is not logged in
	 * 404: Category not found or not owned by the current user
	 * 422: Name is missing or empty, parent does not exist, or color is invalid
	 * 500: Failed to update the category
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/categories/{id}')]
	public function update(string $id, string $name, ?string $color = null, ?string $emoji = null, ?string $parentId = null, bool $income = false): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$category = $this->mapper->findByIdAndOwner($id, $userId);
		if ($category === null) {
			return new DataResponse(['message' => 'Category not found'], Http::STATUS_NOT_FOUND);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if ($color !== null && $color !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
			return new DataResponse(['message' => 'Color must be a hex color like #ff0000'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if ($parentId !== null && $parentId !== '') {
			if ($parentId === $id) {
				return new DataResponse(['message' => 'Category cannot be its own parent'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			$parent = $this->mapper->findByIdAndOwner($parentId, $userId);
			if ($parent === null) {
				return new DataResponse(['message' => 'Parent category not found'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
		}

		$category->setName($name);
		$category->setColor($color !== null && $color !== '' ? $color : null);
		$category->setEmoji($emoji !== null && $emoji !== '' ? $emoji : null);
		$category->setParentId($parentId !== null && $parentId !== '' ? $parentId : null);
		$category->setIncome($income);

		try {
			$updated = $this->mapper->update($category);
		} catch (\Exception $e) {
			$this->logger->error('Failed to update category', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to update category'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['category' => $this->serializeCategory($updated)], Http::STATUS_OK);
	}

	/**
	 * Delete a category for the current user
	 *
	 * References are nulled out: products.category_id, lists.category_id and
	 * child categories' parent_id.
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Category id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{}|array{message: string}, array{}>
	 *
	 * 200: Category deleted
	 * 401: Current user is not logged in
	 * 404: Category not found or not owned by the current user
	 * 500: Failed to delete the category
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/categories/{id}')]
	public function destroy(string $id): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$category = $this->mapper->findByIdAndOwner($id, $userId);
		if ($category === null) {
			return new DataResponse(['message' => 'Category not found'], Http::STATUS_NOT_FOUND);
		}

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;

			$this->nullReference('bbml_products', 'category_id', $id, $userId);
			$this->nullReference('bbml_lists', 'category_id', $id, $userId);
			$this->reparentChildren('bbml_categories', $id, $userId);

			$this->mapper->delete($category);

			$this->db->commit();
		} catch (\Exception $e) {
			if ($transactionStarted) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to delete category', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to delete category'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	private function nullReference(string $table, string $column, string $categoryId, string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($table)
			->set($column, $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq($column, $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}

	private function reparentChildren(string $table, string $categoryId, string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($table)
			->set('parent_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('parent_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}

	/**
	 * @return array{id: string, name: string, color: ?string, emoji: ?string, parentId: ?string, income: bool}
	 */
	private function serializeCategory(CategoryEntity $category): array {
		return [
			'id' => $category->getId(),
			'name' => $category->getName() ?? '',
			'color' => $category->getColor(),
			'emoji' => $category->getEmoji(),
			'parentId' => $category->getParentId(),
			'income' => $category->getIncome() ?? false,
		];
	}
}
