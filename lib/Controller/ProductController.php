<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Entity\ProductAliasEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
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
class ProductController extends OCSController {
	private ProductMapper $mapper;
	private ProductAliasMapper $aliasMapper;
	private CategoryMapper $categoryMapper;
	private IDBConnection $db;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		ProductMapper $mapper,
		ProductAliasMapper $aliasMapper,
		CategoryMapper $categoryMapper,
		IDBConnection $db,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
		$this->aliasMapper = $aliasMapper;
		$this->categoryMapper = $categoryMapper;
		$this->db = $db;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Get all normal products (not subscription, not income) for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED, array{products: list<array{id: string, name: string, barcode: ?string, categoryId: ?string, aliases: list<string>, isFavorite: bool, status: string}>}|array{message: string}, array{}>
	 *
	 * 200: Products returned
	 * 401: Current user is not logged in
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/products')]
	public function index(): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$products = $this->mapper->findAllByOwner($userId);
		$aliasesByProduct = $this->groupAliases(
			$this->aliasMapper->findByProductIds(array_map(fn (ProductEntity $product): string => $product->getId(), $products), $userId)
		);

		$serialized = array_map(
			fn (ProductEntity $product): array => $this->serializeProduct($product, $aliasesByProduct[$product->getId()] ?? []),
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
	 * @param ?string $categoryId Optional category id (must belong to the current user and not be an income category)
	 * @param ?string $barcode Optional product barcode
	 * @param list<string> $aliases Optional product aliases (comma-separated in the request)
	 * @param bool $isFavorite Whether the product is a favorite
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{product: array{id: string, name: string, barcode: ?string, categoryId: ?string, aliases: list<string>, isFavorite: bool, status: string}}|array{message: string}, array{}>
	 *
	 * 201: Product created
	 * 401: Current user is not logged in
	 * 422: Name is missing or empty, category does not exist, or category is an income category
	 * 500: Failed to create the product
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/products')]
	public function create(string $name, ?string $categoryId = null, ?string $barcode = null, array $aliases = [], bool $isFavorite = false): DataResponse {
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
			if ($category->getIncome() ?? false) {
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
	 * @return array{id: string, name: string, barcode: ?string, categoryId: ?string, aliases: list<string>, isFavorite: bool, status: string}
	 */
	private function serializeProduct(ProductEntity $product, array $aliases = []): array {
		return [
			'id' => $product->getId(),
			'name' => $product->getName() ?? '',
			'barcode' => $product->getBarcode(),
			'categoryId' => $product->getCategoryId(),
			'aliases' => $aliases,
			'isFavorite' => $product->getIsFavorite() ?? false,
			'status' => $product->getStatus() ?? 'reviewed',
		];
	}
}
