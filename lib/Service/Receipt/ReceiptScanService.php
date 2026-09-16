<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service\Receipt;

use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\LlmProfileMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Entity\LlmProfileEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Orchestrates receipt OCR: loads the active LLM profile, builds the prompt with
 * the user's categories and top stores, calls the provider, then matches products
 * and categories so the result is ready to commit.
 *
 * @psalm-suppress UnusedClass
 */
class ReceiptScanService {
	private const RECEIPT_EXTRACTION_PROMPT = "Extract items from this receipt. Return ONLY a JSON object with: 'store_name' (string), 'store_address' (string, optional), 'items' (list of {name: string, quantity: number, price: number, discount: number, isCoupon: boolean}), and 'total_sum' (number). 'quantity' should be the number of units or weight, and 'price' should be the unit price BEFORE discount. 'discount' is the total discount amount for this item (positive number). Pay close attention to negative values on the receipt, which usually represent discounts (e.g., -1.50 or 1.50-); these should be captured as 'discount' for the preceding item. If a negative value or discount is a general coupon (not tied to a specific product), set 'isCoupon' to true, 'name' to the coupon description, 'price' to 0, and 'discount' to the coupon value. Ensure ALL discounts and coupons are included. For 'store_name', try to match it against a provided list of existing stores if possible, favoring the most likely match even if slightly different in text. If no match is found, provide the name as written on the receipt.";

	private LlmProfileMapper $profileMapper;
	private ICrypto $crypto;
	private OpenAiCompatibleScanner $scanner;
	private CategoryMapper $categoryMapper;
	private StoreMapper $storeMapper;
	private ProductMapper $productMapper;
	private ProductAliasMapper $productAliasMapper;
	private ReceiptProductMatcher $matcher;
	private LoggerInterface $logger;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		LlmProfileMapper $profileMapper,
		ICrypto $crypto,
		OpenAiCompatibleScanner $scanner,
		CategoryMapper $categoryMapper,
		StoreMapper $storeMapper,
		ProductMapper $productMapper,
		ProductAliasMapper $productAliasMapper,
		ReceiptProductMatcher $matcher,
		LoggerInterface $logger,
	) {
		$this->profileMapper = $profileMapper;
		$this->crypto = $crypto;
		$this->scanner = $scanner;
		$this->categoryMapper = $categoryMapper;
		$this->storeMapper = $storeMapper;
		$this->productMapper = $productMapper;
		$this->productAliasMapper = $productAliasMapper;
		$this->matcher = $matcher;
		$this->logger = $logger;
	}

	/**
	 * @param string $tmpPath path to the uploaded image on disk
	 * @param string $mime the detected image MIME type
	 *
	 * @return array{storeName: ?string, storeAddress: ?string, storeId: ?string, totalSum: ?float, items: list<array{name: string, quantity: float, price: float, discount: ?float, isCoupon: bool, productId: ?string, categoryId: ?string, categoryName: ?string}>, profile: array{id: string, name: string, provider: string}}
	 */
	public function scan(string $userId, string $tmpPath, string $mime): array {
		$profile = $this->profileMapper->findActiveByOwner($userId);
		if ($profile === null) {
			throw new NoActiveLlmProfileException('No active LLM profile');
		}

		$apiKey = $this->decryptKey($profile);

		$categories = array_values($this->categoryMapper->findAllByOwner($userId));
		$expenseCategoryNames = [];
		foreach ($categories as $category) {
			if (!($category->getIncome() ?? false)) {
				$expenseCategoryNames[] = (string)$category->getName();
			}
		}

		$stores = array_values($this->storeMapper->findTopByOwner($userId, 5));
		$storeNames = array_map(static fn (StoreEntity $s): string => (string)$s->getName(), $stores);

		$imageBase64 = base64_encode((string)file_get_contents($tmpPath));
		$prompt = $this->buildPrompt($expenseCategoryNames, $storeNames);

		$content = $this->scanner->scan(
			$profile->getProvider() ?? '',
			$apiKey,
			$profile->getModel() ?? '',
			$imageBase64,
			$mime,
			$prompt,
			$profile->getConnectTimeout() ?? 30,
			$profile->getReadTimeout() ?? 60,
			$profile->getMaxTokens() ?? 2048,
		);

		$receipt = $this->parseReceipt($content);

		$products = array_values($this->productMapper->findAllIncludingSpecialByOwner($userId));
		$aliasMap = $this->buildAliasMap($products, $userId);
		$categoryByName = $this->buildCategoryMap($categories);

		$storeId = $this->matchStore($receipt['store_name'] ?? null, $stores);

		$items = [];
		$receiptItems = [];
		if (isset($receipt['items']) && is_array($receipt['items'])) {
			$receiptItems = $receipt['items'];
		}
		foreach ($receiptItems as $item) {
			if (!is_array($item)) {
				continue;
			}
			$name = trim((string)($item['name'] ?? ''));
			if ($name === '') {
				continue;
			}
			$isCoupon = (bool)($item['isCoupon'] ?? false);
			$categoryName = isset($item['category']) && is_string($item['category']) ? trim($item['category']) : null;

			$matched = $isCoupon ? null : $this->matcher->match($name, $products, $aliasMap);
			$categoryId = null;
			if ($categoryName !== null && $categoryName !== '') {
				$categoryId = $this->matchCategory($categoryName, $categoryByName);
			}

			$items[] = [
				'name' => $name,
				'quantity' => $this->toFloat($item['quantity'] ?? 1),
				'price' => $this->toFloat($item['price'] ?? 0),
				'discount' => isset($item['discount']) ? $this->toFloat($item['discount']) : null,
				'isCoupon' => $isCoupon,
				'productId' => $matched !== null ? $matched->getId() : null,
				'categoryId' => $categoryId,
				'categoryName' => $categoryName,
			];
		}

		return [
			'storeName' => isset($receipt['store_name']) && is_string($receipt['store_name']) ? $receipt['store_name'] : null,
			'storeAddress' => isset($receipt['store_address']) && is_string($receipt['store_address']) ? $receipt['store_address'] : null,
			'storeId' => $storeId,
			'totalSum' => isset($receipt['total_sum']) ? $this->toFloat($receipt['total_sum']) : null,
			'items' => $items,
			'profile' => [
				'id' => $profile->getId(),
				'name' => $profile->getName() ?? '',
				'provider' => $profile->getProvider() ?? '',
			],
		];
	}

	private function decryptKey(LlmProfileEntity $profile): string {
		$encrypted = $profile->getApiKey();
		if ($encrypted === null || $encrypted === '') {
			throw new RuntimeException('LLM profile has no API key');
		}
		try {
			return $this->crypto->decrypt($encrypted);
		} catch (\Exception $e) {
			$this->logger->error('Failed to decrypt LLM API key', ['exception' => $e]);
			throw new RuntimeException('Failed to decrypt LLM API key', 0, $e);
		}
	}

	/**
	 * @param list<string> $categories
	 * @param list<string> $stores
	 */
	private function buildPrompt(array $categories, array $stores): string {
		$prompt = self::RECEIPT_EXTRACTION_PROMPT;

		if ($categories !== []) {
			$prompt .= "\nFor each item, suggest the most appropriate category from this list: " . implode(', ', $categories) . ". Return it in the 'category' field.";
		}
		if ($stores !== []) {
			$prompt .= "\nTry to match the store name against this list: " . implode(', ', $stores) . ". Return the matched name in 'store_name'. If there is no good match, return the name exactly as printed on the receipt and do NOT pick a name from the list.";
		}

		return $prompt;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function parseReceipt(string $content): array {
		$clean = trim($content);
		$clean = (string)preg_replace('/^```(?:json)?\s*/', '', $clean);
		$clean = (string)preg_replace('/\s*```$/', '', $clean);

		$decoded = json_decode($clean, true);
		if (!is_array($decoded)) {
			throw new RuntimeException('Failed to parse receipt data');
		}

		/** @var array<string, mixed> $decoded */
		return $decoded;
	}

	/**
	 * @param list<ProductEntity> $products
	 *
	 * @return array<string, ProductEntity>
	 */
	private function buildAliasMap(array $products, string $userId): array {
		$byId = [];
		foreach ($products as $product) {
			$byId[$product->getId()] = $product;
		}
		$ids = array_keys($byId);

		$map = [];
		foreach ($this->productAliasMapper->findByProductIds($ids, $userId) as $alias) {
			$productId = $alias->getProductId();
			$aliasName = $alias->getAliasName();
			if ($productId === null || $aliasName === null) {
				continue;
			}
			$product = $byId[$productId] ?? null;
			if ($product !== null) {
				$map[mb_strtolower($aliasName)] = $product;
			}
		}
		return $map;
	}

	/**
	 * @param list<CategoryEntity> $categories
	 *
	 * @return array<string, CategoryEntity>
	 */
	private function buildCategoryMap(array $categories): array {
		$map = [];
		foreach ($categories as $category) {
			$map[mb_strtolower((string)$category->getName())] = $category;
		}
		return $map;
	}

	/**
	 * @param array<string, CategoryEntity> $categoryByName
	 */
	private function matchCategory(string $name, array $categoryByName): ?string {
		$key = mb_strtolower(trim($name));
		if ($key === '') {
			return null;
		}
		if (!array_key_exists($key, $categoryByName)) {
			return null;
		}
		return $categoryByName[$key]->getId();
	}

	/**
	 * @param list<StoreEntity> $stores
	 */
	private function matchStore(mixed $storeName, array $stores): ?string {
		if (!is_string($storeName)) {
			return null;
		}
		$trimmed = trim($storeName);
		if ($trimmed === '') {
			return null;
		}
		foreach ($stores as $store) {
			if (mb_strtolower((string)$store->getName()) === mb_strtolower($trimmed)) {
				return $store->getId();
			}
		}
		return null;
	}

	private function toFloat(mixed $value): float {
		if (is_int($value) || is_float($value)) {
			return (float)$value;
		}
		if (is_string($value)) {
			$normalized = str_replace(',', '.', trim($value));
			if (is_numeric($normalized)) {
				return (float)$normalized;
			}
		}
		return 0.0;
	}
}
