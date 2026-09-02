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
	 * @param ?string $color Hex color #RRGGBB or #AARRGGBB (e.g. #ff0000)
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

		if (!$this->isValidColor($color)) {
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
		$category->setColor($this->normalizeColor($color));
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
	 * Create multiple categories in batch for the current user.
	 *
	 * Roots are created first, then their children, then grandchildren, so the
	 * full hierarchy is restored. A parentId may reference another category in
	 * this batch by its tempId or an already existing category by its id; if a
	 * referenced parent cannot be found, the category is created as a root
	 * instead of failing the whole batch.
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param list<array{name: string, color?: ?string, emoji?: ?string, parentId?: ?string, income?: bool, tempId?: ?string}> $categories Categories list to create (required)
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{categories: list<array{id: string, name: string, color: ?string, emoji: ?string, parentId: ?string, income: bool, tempId?: ?string}>}|array{message: string}, array{}>
	 *
	 * 201: Categories created
	 * 401: Current user is not logged in
	 * 422: Categories array is empty or contains invalid items
	 * 500: Failed to create categories
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/categories/batch')]
	public function batchCreate(array $categories = []): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		if (count($categories) === 0) {
			return new DataResponse(['message' => 'Categories array is required and must not be empty'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;

			// Pass 1: validate and pre-assign UUIDs so that a parent referenced by
			// tempId resolves correctly no matter the order of the submitted list.
			$pendingCategories = [];
			$tempIdToRealIdMap = [];
			foreach ($categories as $index => $catData) {
				if (!is_array($catData)) {
					$this->db->rollBack();
					return new DataResponse(['message' => "Category item at index {$index} must be an object"], Http::STATUS_UNPROCESSABLE_ENTITY);
				}

				$name = isset($catData['name']) && is_string($catData['name']) ? trim($catData['name']) : '';
				if ($name === '') {
					$this->db->rollBack();
					return new DataResponse(['message' => "Category name is required at index {$index}"], Http::STATUS_UNPROCESSABLE_ENTITY);
				}

				$color = isset($catData['color']) && is_string($catData['color']) ? $catData['color'] : null;
				if (!$this->isValidColor($color)) {
					$this->db->rollBack();
					return new DataResponse(['message' => "Invalid color format at index {$index}"], Http::STATUS_UNPROCESSABLE_ENTITY);
				}

				$emoji = isset($catData['emoji']) && is_string($catData['emoji']) ? $catData['emoji'] : null;
				$income = isset($catData['income']) ? (bool)$catData['income'] : false;
				$tempId = isset($catData['tempId']) && is_string($catData['tempId']) ? $catData['tempId'] : null;
				$parentId = isset($catData['parentId']) && is_string($catData['parentId']) && $catData['parentId'] !== '' ? $catData['parentId'] : null;
				$status = isset($catData['status']) && is_string($catData['status']) ? $catData['status'] : 'pending_review';

				$newId = Uuid::v4();
				if ($tempId !== null && $tempId !== '') {
					$tempIdToRealIdMap[$tempId] = $newId;
				}

				$pendingCategories[$index] = [
					'index' => $index,
					'id' => $newId,
					'name' => $name,
					'color' => $color,
					'emoji' => $emoji,
					'income' => $income,
					'tempId' => $tempId,
					'parentId' => $parentId,
					'status' => $status,
				];
			}

			// Pass 2: order categories so that roots come first, then their children,
			// then grandchildren (a parent referenced by tempId is always created
			// before the categories that point to it).
			$byTempId = [];
			foreach ($pendingCategories as $data) {
				if ($data['tempId'] !== null) {
					$byTempId[$data['tempId']] = $data['index'];
				}
			}

			$emitted = [];
			$orderedIndexes = [];
			while (count($orderedIndexes) < count($pendingCategories)) {
				$progress = false;
				foreach ($pendingCategories as $data) {
					$idx = $data['index'];
					if (isset($emitted[$idx])) {
						continue;
					}
					$parentId = $data['parentId'];
					$ready = $parentId === null
						|| !isset($byTempId[$parentId])
						|| isset($emitted[$byTempId[$parentId]]);
					if ($ready) {
						$emitted[$idx] = true;
						$orderedIndexes[] = $idx;
						$progress = true;
					}
				}
				if (!$progress) {
					// Only a cycle between remaining tempId references can get here.
					// Emit them anyway so no submitted category is lost.
					foreach ($pendingCategories as $data) {
						$idx = $data['index'];
						if (!isset($emitted[$idx])) {
							$emitted[$idx] = true;
							$orderedIndexes[] = $idx;
						}
					}
					break;
				}
			}

			// Pass 3: resolve parent ids and insert. A parent that still cannot be
			// found (neither created in this batch nor owned by the user) is reset
			// so the category is created as a root instead of failing the batch.
			$createdCategories = [];
			foreach ($orderedIndexes as $idx) {
				$data = $pendingCategories[$idx];
				$parentId = $data['parentId'];
				if ($parentId !== null) {
					if (isset($tempIdToRealIdMap[$parentId])) {
						$parentId = $tempIdToRealIdMap[$parentId];
					} else {
						$parent = $this->mapper->findByIdAndOwner($parentId, $userId);
						if ($parent === null) {
							$parentId = null;
						}
					}
				}

				$category = new CategoryEntity();
				$category->setId($data['id']);
				$category->setOwner($userId);
				$category->setName($data['name']);
				$category->setColor($this->normalizeColor($data['color']));
				if ($data['emoji'] !== null && $data['emoji'] !== '') {
					$category->setEmoji($data['emoji']);
				}
				if ($parentId !== null && $parentId !== '') {
					$category->setParentId($parentId);
				}
				$category->setIncome($data['income']);
				$category->setStatus($data['status']);

				$created = $this->mapper->insert($category);

				$serialized = $this->serializeCategory($created);
				if ($data['tempId'] !== null && $data['tempId'] !== '') {
					$serialized['tempId'] = $data['tempId'];
				}
				$createdCategories[] = $serialized;
			}

			$this->db->commit();
		} catch (\Exception $e) {
			if ($transactionStarted) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to batch create categories', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to batch create categories'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['categories' => $createdCategories], Http::STATUS_CREATED);
	}

	/**
	 * Confirm a pending category for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Category id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{category: array{id: string, name: string, color: ?string, emoji: ?string, parentId: ?string, income: bool, status: string}}|array{message: string}, array{}>
	 *
	 * 200: Category confirmed
	 * 401: Current user is not logged in
	 * 404: Category not found
	 * 500: Failed to confirm category
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/categories/{id}/confirm')]
	public function confirm(string $id): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$category = $this->mapper->findByIdAndOwner($id, $userId);
		if ($category === null) {
			return new DataResponse(['message' => 'Category not found'], Http::STATUS_NOT_FOUND);
		}

		$category->setStatus('confirmed');
		try {
			$updated = $this->mapper->update($category);
		} catch (\Exception $e) {
			$this->logger->error('Failed to confirm category', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to confirm category'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['category' => $this->serializeCategory($updated)], Http::STATUS_OK);
	}

	/**
	 * Confirm all pending categories for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_INTERNAL_SERVER_ERROR, array{message: string}, array{}>
	 *
	 * 200: All pending categories confirmed
	 * 401: Current user is not logged in
	 * 500: Failed to confirm categories
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/categories/confirm-all')]
	public function confirmAll(): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$qb = $this->db->getQueryBuilder();
			$qb->update('bbml_categories')
				->set('status', $qb->createNamedParameter('confirmed'))
				->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId)))
				->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('pending_review')));
			$qb->executeStatement();
		} catch (\Exception $e) {
			$this->logger->error('Failed to confirm all categories', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to confirm all categories'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['message' => 'All pending categories confirmed'], Http::STATUS_OK);
	}



	/**
	 * Update a category for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Category id
	 * @param string $name Category name (required)
	 * @param ?string $color Hex color #RRGGBB or #AARRGGBB (e.g. #ff0000)
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

		if (!$this->isValidColor($color)) {
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
		$category->setColor($this->normalizeColor($color));
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
	 * @return array{id: string, name: string, color: ?string, emoji: ?string, parentId: ?string, income: bool, status: string}
	 */
	private function serializeCategory(CategoryEntity $category): array {
		return [
			'id' => $category->getId(),
			'name' => $category->getName() ?? '',
			'color' => $category->getColor(),
			'emoji' => $category->getEmoji(),
			'parentId' => $category->getParentId(),
			'income' => $category->getIncome() ?? false,
			'status' => $category->getStatus() ?? 'confirmed',
		];
	}

	/**
	 * Accepts #RRGGBB or #AARRGGBB. Blank/null colors are allowed.
	 */
	private function isValidColor(?string $color): bool {
		if ($color === null || $color === '') {
			return true;
		}
		return preg_match('/^#[0-9a-fA-F]{6}(?:[0-9a-fA-F]{2})?$/', $color) === 1;
	}

	/**
	 * Normalizes a validated color to #RRGGBB form (drops the alpha channel from #AARRGGBB).
	 */
	private function normalizeColor(?string $color): ?string {
		if ($color === null || $color === '') {
			return null;
		}
		$hex = ltrim($color, '#');
		if (strlen($hex) === 8) {
			$hex = substr($hex, 2);
		}
		return '#' . $hex;
	}

}
