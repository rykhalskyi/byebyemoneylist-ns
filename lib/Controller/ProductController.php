<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use DateTimeInterface;
use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\ProductPriceMapper;
use OCA\ByeByeMoneyList\Entity\ProductAliasEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Entity\ProductPriceEntity;
use OCA\ByeByeMoneyList\Service\ProductMergeService;
use OCA\ByeByeMoneyList\Service\ProductPictureService;
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
class ProductController extends OCSController {
	private ProductMapper $mapper;
	private ProductAliasMapper $aliasMapper;
	private CategoryMapper $categoryMapper;
	private ListItemMapper $itemMapper;
	private ProductPriceMapper $priceMapper;
	private ProductPictureService $pictureService;
	private ProductMergeService $mergeService;
	private IDBConnection $db;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		ProductMapper $mapper,
		ProductAliasMapper $aliasMapper,
		CategoryMapper $categoryMapper,
		ListItemMapper $itemMapper,
		ProductPriceMapper $priceMapper,
		ProductPictureService $pictureService,
		ProductMergeService $mergeService,
		IDBConnection $db,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
		$this->aliasMapper = $aliasMapper;
		$this->categoryMapper = $categoryMapper;
		$this->itemMapper = $itemMapper;
		$this->priceMapper = $priceMapper;
		$this->pictureService = $pictureService;
		$this->mergeService = $mergeService;
		$this->db = $db;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Get products for the current user
	 *
	 * @param string $type Which products to return: normal (default), subscriptions, income or all
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED, array{products: list<array{id: string, name: string, barcode: ?string, categoryId: ?string, aliases: list<string>, isFavorite: bool, status: string, isSubscription: bool, isIncome: bool, lastPrice: ?float, lastPriceDate: ?string, hasPicture: bool}>}|array{message: string}, array{}>
	 *
	 * 200: Products returned
	 * 401: Current user is not logged in
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/products')]
	public function index(string $type = 'normal'): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$products = match ($type) {
			'subscriptions' => $this->mapper->findSubscriptionsByOwner($userId),
			'income' => $this->mapper->findIncomeByOwner($userId),
			'all' => $this->mapper->findAllIncludingSpecialByOwner($userId),
			default => $this->mapper->findAllByOwner($userId),
		};
		$aliasesByProduct = $this->groupAliases(
			$this->aliasMapper->findByProductIds(array_map(fn (ProductEntity $product): string => $product->getId(), $products), $userId)
		);
		$latestPrices = $this->priceMapper->findLatestByProductIds(
			array_values(array_map(fn (ProductEntity $product): string => $product->getId(), $products)),
			$userId,
		);

		$serialized = array_map(
			fn (ProductEntity $product): array => $this->serializeProduct(
				$product,
				$aliasesByProduct[$product->getId()] ?? [],
				$latestPrices[$product->getId()] ?? null,
			),
			$products,
		);

		return new DataResponse(['products' => array_values($serialized)], Http::STATUS_OK);
	}

	/**
	 * Create a new product for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $name Product name (required)
	 * @param ?string $categoryId Optional category id (must belong to the current user; income categories only allowed for income products)
	 * @param ?string $barcode Optional product barcode
	 * @param list<string> $aliases Optional product aliases (comma-separated in the request)
	 * @param bool $isFavorite Whether the product is a favorite
	 * @param bool $isSubscription Whether the product is a subscription
	 * @param bool $isIncome Whether the product is an income source
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{product: array{id: string, name: string, barcode: ?string, categoryId: ?string, aliases: list<string>, isFavorite: bool, status: string, isSubscription: bool, isIncome: bool, lastPrice: ?float, lastPriceDate: ?string, hasPicture: bool}}|array{message: string}, array{}>
	 *
	 * 201: Product created
	 * 401: Current user is not logged in
	 * 422: Name is missing or empty, category does not exist, or category is an income category for a non-income product
	 * 500: Failed to create the product
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/products')]
	public function create(string $name, ?string $categoryId = null, ?string $barcode = null, array $aliases = [], bool $isFavorite = false, bool $isSubscription = false, bool $isIncome = false): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if ($categoryId !== null && $categoryId !== '') {
			$category = $this->categoryMapper->findByIdAndOwner($categoryId, $userId);
			if ($category === null) {
				return new DataResponse(['message' => 'Category not found'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			if (($category->getIncome() ?? false) && !$isIncome) {
				return new DataResponse(['message' => 'Category must not be an income category'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
		}

		$cleanAliases = $this->normalizeAliases($aliases);

		$product = new ProductEntity();
		$product->setId(Uuid::v4());
		$product->setOwner($userId);
		$product->setName($name);
		$product->setStatus('reviewed');
		$product->setIsFavorite($isFavorite);
		$product->setIsSubscription($isSubscription);
		$product->setIsIncome($isIncome);
		if ($categoryId !== null && $categoryId !== '') {
			$product->setCategoryId($categoryId);
		}
		if ($barcode !== null && $barcode !== '') {
			$product->setBarcode(trim($barcode));
		}

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;
			$this->mapper->insert($product);
			foreach ($cleanAliases as $alias) {
				$aliasEntity = new ProductAliasEntity();
				$aliasEntity->setId(Uuid::v4());
				$aliasEntity->setOwner($userId);
				$aliasEntity->setProductId($product->getId());
				$aliasEntity->setAliasName($alias);
				$this->aliasMapper->insert($aliasEntity);
			}
			$this->db->commit();
		} catch (\Exception $e) {
			if ($transactionStarted) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to create product', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to create product'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['product' => $this->serializeProduct($product, $cleanAliases)], Http::STATUS_CREATED);
	}

	/**
	 * Update a product for the current user (aliases fully replaced)
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Product id
	 * @param string $name Product name (required)
	 * @param ?string $categoryId Optional category id (must belong to the current user; income categories only allowed for income products)
	 * @param ?string $barcode Optional product barcode
	 * @param list<string> $aliases Optional product aliases
	 * @param bool $isFavorite Whether the product is a favorite
	 * @param bool $isSubscription Whether the product is a subscription
	 * @param bool $isIncome Whether the product is an income source
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{product: array{id: string, name: string, barcode: ?string, categoryId: ?string, aliases: list<string>, isFavorite: bool, status: string, isSubscription: bool, isIncome: bool, lastPrice: ?float, lastPriceDate: ?string, hasPicture: bool}}|array{message: string}, array{}>
	 *
	 * 200: Product updated
	 * 401: Current user is not logged in
	 * 404: Product not found or not owned by the current user
	 * 422: Name is missing or empty, category does not exist, or category is an income category for a non-income product
	 * 500: Failed to update the product
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/products/{id}')]
	public function update(string $id, string $name, ?string $categoryId = null, ?string $barcode = null, array $aliases = [], bool $isFavorite = false, bool $isSubscription = false, bool $isIncome = false): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$product = $this->mapper->findByIdAndOwner($id, $userId);
		if ($product === null) {
			return new DataResponse(['message' => 'Product not found'], Http::STATUS_NOT_FOUND);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if ($categoryId !== null && $categoryId !== '') {
			$category = $this->categoryMapper->findByIdAndOwner($categoryId, $userId);
			if ($category === null) {
				return new DataResponse(['message' => 'Category not found'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			if (($category->getIncome() ?? false) && !$isIncome) {
				return new DataResponse(['message' => 'Category must not be an income category'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
		}

		$cleanAliases = $this->normalizeAliases($aliases);

		$product->setName($name);
		$product->setCategoryId($categoryId !== null && $categoryId !== '' ? $categoryId : null);
		$product->setBarcode($barcode !== null && $barcode !== '' ? trim($barcode) : null);
		$product->setIsFavorite($isFavorite);
		$product->setIsSubscription($isSubscription);
		$product->setIsIncome($isIncome);

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;
			$this->mapper->update($product);
			$this->aliasMapper->deleteByProductId($product->getId(), $userId);
			foreach ($cleanAliases as $alias) {
				$aliasEntity = new ProductAliasEntity();
				$aliasEntity->setId(Uuid::v4());
				$aliasEntity->setOwner($userId);
				$aliasEntity->setProductId($product->getId());
				$aliasEntity->setAliasName($alias);
				$this->aliasMapper->insert($aliasEntity);
			}
			$this->db->commit();
		} catch (\Exception $e) {
			if ($transactionStarted) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to update product', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to update product'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$latestPrices = $this->priceMapper->findLatestByProductIds([$product->getId()], $userId);

		return new DataResponse([
			'product' => $this->serializeProduct(
				$product,
				$cleanAliases,
				$latestPrices[$product->getId()] ?? null,
			),
		], Http::STATUS_OK);
	}

	/**
	 * Delete a product for the current user (removes aliases and list items referencing it)
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Product id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{}|array{message: string}, array{}>
	 *
	 * 200: Product deleted
	 * 401: Current user is not logged in
	 * 404: Product not found or not owned by the current user
	 * 500: Failed to delete the product
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/products/{id}')]
	public function destroy(string $id): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$product = $this->mapper->findByIdAndOwner($id, $userId);
		if ($product === null) {
			return new DataResponse(['message' => 'Product not found'], Http::STATUS_NOT_FOUND);
		}

		$picturePath = $product->getPicturePath();

		$transactionStarted = false;
		try {
			$this->db->beginTransaction();
			$transactionStarted = true;

			$this->aliasMapper->deleteByProductId($id, $userId);

			$qb = $this->db->getQueryBuilder();
			$qb->delete('bbml_list_items')
				->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('product_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)));
			$qb->executeStatement();

			$qb = $this->db->getQueryBuilder();
			$qb->delete('bbml_product_prices')
				->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
				->andWhere($qb->expr()->eq('product_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)));
			$qb->executeStatement();

			$this->mapper->delete($product);

			$this->db->commit();
		} catch (\Exception $e) {
			if ($transactionStarted) {
				$this->db->rollBack();
			}
			$this->logger->error('Failed to delete product', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to delete product'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		try {
			$this->pictureService->delete($userId, $picturePath);
		} catch (\Exception $e) {
			$this->logger->warning('Failed to delete product picture', ['exception' => $e]);
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * Merge two of the current user's products into the primary one
	 *
	 * The primary product keeps its id and the chosen fields; the secondary one is
	 * deleted. Aliases of both products (and their original names) are concatenated,
	 * and all list items and price records of the secondary are moved to the primary.
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $primaryId Product id that is kept
	 * @param string $secondaryId Product id that is merged into the primary and deleted
	 * @param string $name Name of the merged product (required)
	 * @param ?string $categoryId Optional category id (must belong to the current user; income categories only allowed for income products)
	 * @param ?string $barcode Optional barcode of the merged product
	 * @param bool $isFavorite Whether the merged product is a favorite
	 * @param bool $isSubscription Whether the merged product is a subscription
	 * @param bool $isIncome Whether the merged product is an income source
	 * @param string $pictureFrom Which picture to keep: primary, secondary or none
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{product: array{id: string, name: string, barcode: ?string, categoryId: ?string, aliases: list<string>, isFavorite: bool, status: string, isSubscription: bool, isIncome: bool, lastPrice: ?float, lastPriceDate: ?string, hasPicture: bool}}|array{message: string}, array{}>
	 *
	 * 200: Products merged
	 * 401: Current user is not logged in
	 * 404: A product does not exist or is not owned by the current user
	 * 422: Invalid input (same id, empty name, invalid category or picture choice)
	 * 500: Failed to merge the products
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/products/merge')]
	public function merge(string $primaryId, string $secondaryId, string $name, ?string $categoryId = null, ?string $barcode = null, bool $isFavorite = false, bool $isSubscription = false, bool $isIncome = false, string $pictureFrom = 'primary'): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		if ($primaryId === $secondaryId) {
			return new DataResponse(['message' => 'Cannot merge a product with itself'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$primary = $this->mapper->findByIdAndOwner($primaryId, $userId);
		if ($primary === null) {
			return new DataResponse(['message' => 'Primary product not found'], Http::STATUS_NOT_FOUND);
		}
		$secondary = $this->mapper->findByIdAndOwner($secondaryId, $userId);
		if ($secondary === null) {
			return new DataResponse(['message' => 'Secondary product not found'], Http::STATUS_NOT_FOUND);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if (!in_array($pictureFrom, ['primary', 'secondary', 'none'], true)) {
			return new DataResponse(['message' => 'Invalid picture choice'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		if ($categoryId !== null && $categoryId !== '') {
			$category = $this->categoryMapper->findByIdAndOwner($categoryId, $userId);
			if ($category === null) {
				return new DataResponse(['message' => 'Category not found'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			if (($category->getIncome() ?? false) && !$isIncome) {
				return new DataResponse(['message' => 'Category must not be an income category'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
		}

		try {
			$product = $this->mergeService->merge(
				$userId,
				$primary,
				$secondary,
				$name,
				$categoryId !== null && $categoryId !== '' ? $categoryId : null,
				$barcode !== null && $barcode !== '' ? trim($barcode) : null,
				$isFavorite,
				$isSubscription,
				$isIncome,
				$pictureFrom,
			);
		} catch (\Exception $e) {
			$this->logger->error('Failed to merge products', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to merge products'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$aliasesByProduct = $this->groupAliases($this->aliasMapper->findByProductIds([$product->getId()], $userId));
		$latestPrices = $this->priceMapper->findLatestByProductIds([$product->getId()], $userId);

		return new DataResponse([
			'product' => $this->serializeProduct(
				$product,
				$aliasesByProduct[$product->getId()] ?? [],
				$latestPrices[$product->getId()] ?? null,
			),
		], Http::STATUS_OK);
	}

	/**
	 * @param array $aliases
	 *
	 * @return list<string>
	 */
	private function normalizeAliases(array $aliases): array {
		$normalized = [];
		foreach ($aliases as $alias) {
			if (!is_string($alias)) {
				continue;
			}
			$trimmed = trim($alias);
			if ($trimmed === '') {
				continue;
			}
			$normalized[$trimmed] = true;
		}
		return array_keys($normalized);
	}

	/**
	 * @param array<ProductAliasEntity> $aliases
	 *
	 * @return array<string, list<string>>
	 */
	private function groupAliases(array $aliases): array {
		$grouped = [];
		foreach ($aliases as $alias) {
			$productId = $alias->getProductId();
			if ($productId === null) {
				continue;
			}
			$grouped[$productId][] = $alias->getAliasName() ?? '';
		}
		return $grouped;
	}

	/**
	 * @param list<string> $aliases
	 *
	 * @return array{id: string, name: string, barcode: ?string, categoryId: ?string, aliases: list<string>, isFavorite: bool, status: string, isSubscription: bool, isIncome: bool, lastPrice: ?float, lastPriceDate: ?string, hasPicture: bool}
	 */
	private function serializeProduct(ProductEntity $product, array $aliases = [], ?ProductPriceEntity $lastPrice = null): array {
		return [
			'id' => $product->getId(),
			'name' => $product->getName() ?? '',
			'barcode' => $product->getBarcode(),
			'categoryId' => $product->getCategoryId(),
			'aliases' => $aliases,
			'isFavorite' => $product->getIsFavorite() ?? false,
			'status' => $product->getStatus() ?? 'reviewed',
			'isSubscription' => $product->getIsSubscription() ?? false,
			'isIncome' => $product->getIsIncome() ?? false,
			'lastPrice' => $lastPrice?->getValue(),
			'lastPriceDate' => $lastPrice?->getPriceDate()?->format(DateTimeInterface::ATOM),
			'hasPicture' => $product->getPicturePath() !== null,
		];
	}
}
