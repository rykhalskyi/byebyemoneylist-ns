<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service\Import;

use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\LlmProfileMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\LlmProfileEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportCategoryEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportItemEntity;
use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCA\ByeByeMoneyList\Service\Receipt\NoActiveLlmProfileException;
use OCA\ByeByeMoneyList\Service\Receipt\OpenAiCompatibleScanner;
use OCA\ByeByeMoneyList\Service\Receipt\ReceiptProductMatcher;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * AI-assisted mapping suggestions for a pending purchase import.
 *
 * The model may only fill suggestion fields for rows that are still `pending`; it
 * never approves an import or writes to the catalog.
 *
 * @psalm-suppress UnusedClass
 */
class PurchaseImportAiService {
	private const CONFIDENCE_THRESHOLD = 0.6;
	private const MAX_PRODUCT_CANDIDATES = 300;
	private const MAX_STORE_CANDIDATES = 200;

	private LlmProfileMapper $profileMapper;
	private ICrypto $crypto;
	private OpenAiCompatibleScanner $scanner;
	private StoreMapper $storeMapper;
	private CategoryMapper $categoryMapper;
	private ProductMapper $productMapper;
	private ReceiptProductMatcher $matcher;
	private LoggerInterface $logger;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		LlmProfileMapper $profileMapper,
		ICrypto $crypto,
		OpenAiCompatibleScanner $scanner,
		StoreMapper $storeMapper,
		CategoryMapper $categoryMapper,
		ProductMapper $productMapper,
		ReceiptProductMatcher $matcher,
		LoggerInterface $logger,
	) {
		$this->profileMapper = $profileMapper;
		$this->crypto = $crypto;
		$this->scanner = $scanner;
		$this->storeMapper = $storeMapper;
		$this->categoryMapper = $categoryMapper;
		$this->productMapper = $productMapper;
		$this->matcher = $matcher;
		$this->logger = $logger;
	}

	/**
	 * @param PurchaseImportItemEntity[] $items
	 * @param PurchaseImportCategoryEntity[] $categories
	 *
	 * @throws NoActiveLlmProfileException
	 */
	public function suggest(
		PurchaseImportEntity $import,
		array $items,
		array $categories,
		string $owner,
	): void {
		$profile = $this->profileMapper->findActiveByOwner($owner);
		if ($profile === null) {
			$profiles = $this->profileMapper->findAllByOwner($owner);
			if (count($profiles) === 1) {
				$profile = $profiles[0];
			}
		}
		if ($profile === null) {
			throw new NoActiveLlmProfileException('No active LLM profile');
		}

		$stores = $this->storeMapper->findAllByOwner($owner);
		$ownCategories = $this->categoryMapper->findAllByOwner($owner);
		$products = $this->productMapper->findAllIncludingSpecialByOwner($owner);

		$storeById = [];
		$storeCandidates = [];
		foreach (array_slice($stores, 0, self::MAX_STORE_CANDIDATES) as $store) {
			$id = $store->getId();
			$storeById[$id] = $store;
			$storeCandidates[] = [
				'id' => $id,
				'name' => (string)$store->getName(),
				'address' => $store->getAddress(),
			];
		}

		$categoryById = [];
		$categoryCandidates = [];
		foreach ($ownCategories as $category) {
			$id = $category->getId();
			$categoryById[$id] = $category;
			$categoryCandidates[] = [
				'id' => $id,
				'name' => (string)$category->getName(),
				'income' => (bool)$category->getIncome(),
			];
		}

		$productById = [];
		$productCandidates = [];
		foreach ($this->selectProductCandidates($products, $items) as $product) {
			$id = $product->getId();
			$productById[$id] = $product;
			$productCandidates[] = [
				'id' => $id,
				'name' => (string)$product->getName(),
				'barcode' => $product->getBarcode(),
				'categoryId' => $product->getCategoryId(),
			];
		}

		$itemByKey = [];
		$itemPayload = [];
		foreach ($items as $item) {
			$key = $this->itemKey($item);
			$itemByKey[$key] = $item;
			$itemPayload[] = [
				'lineId' => $key,
				'name' => (string)$item->getRawName(),
				'quantity' => (float)$item->getQuantity(),
				'unitPrice' => $item->getUnitPrice(),
				'barcode' => $item->getBarcode(),
				'categoryHint' => $item->getCategoryHint(),
				'isCoupon' => (bool)$item->getIsCoupon(),
			];
		}

		$categoryByIdRawName = [];
		foreach ($categories as $category) {
			$categoryByIdRawName[$this->matcher->normalize((string)$category->getRawName())] = $category;
		}

		$prompt = $this->buildPrompt(
			[
				'name' => (string)$import->getName(),
				'storeName' => $import->getStoreName(),
				'storeAddress' => $import->getStoreAddress(),
				'storeReceiptName' => $import->getStoreReceiptName(),
				'finalTotal' => $import->getFinalTotal(),
				'categories' => array_map(static fn (PurchaseImportCategoryEntity $c): array => [
					'rawName' => (string)$c->getRawName(),
					'isIncome' => (bool)$c->getIsIncome(),
				], $categories),
				'items' => $itemPayload,
			],
			[
				'stores' => $storeCandidates,
				'categories' => $categoryCandidates,
				'products' => $productCandidates,
			],
		);

		$content = $this->scanner->complete(
			$profile->getProvider() ?? '',
			$this->decryptKey($profile),
			$profile->getModel() ?? '',
			$prompt,
			$profile->getConnectTimeout() ?? 30,
			$profile->getReadTimeout() ?? 60,
			$profile->getMaxTokens() ?? 2048,
		);

		$decoded = $this->parseJson($content);

		if ($import->getStoreAction() === PurchaseImportEntity::ACTION_PENDING) {
			$this->applyStore($import, $decoded, $storeById);
		}

		$categoryPayload = [];
		if (isset($decoded['categories']) && is_array($decoded['categories'])) {
			$categoryPayload = $decoded['categories'];
		}
		if ($categoryPayload !== []) {
			foreach ($categoryPayload as $row) {
				if (!is_array($row)) {
					continue;
				}
				$rawName = isset($row['rawName']) && is_string($row['rawName']) ? $row['rawName'] : '';
				$category = $categoryByIdRawName[$this->matcher->normalize($rawName)] ?? null;
				if ($category === null || $category->getAction() !== PurchaseImportEntity::ACTION_PENDING) {
					continue;
				}
				$categoryId = $this->validId($row['categoryId'] ?? null, $categoryById);
				if ($categoryId === null) {
					continue;
				}
				$category->setSuggestedCategoryId($categoryId);
				$category->setMatchSource('ai');
				$category->setConfidence(0.9);
			}
		}

		$itemResult = [];
		if (isset($decoded['items']) && is_array($decoded['items'])) {
			$itemResult = $decoded['items'];
		}
		/** @psalm-suppress MixedAssignment */
		foreach ($itemResult as $row) {
			if (!is_array($row)) {
				continue;
			}
			$key = isset($row['lineId']) && is_string($row['lineId']) ? $row['lineId'] : '';
			$item = $itemByKey[$key] ?? null;
			if ($item === null || $item->getAction() !== PurchaseImportEntity::ACTION_PENDING) {
				continue;
			}

			$confidence = $this->number($row['confidence'] ?? null, self::CONFIDENCE_THRESHOLD);
			if ($confidence < self::CONFIDENCE_THRESHOLD) {
				continue;
			}

			$productId = $this->validId($row['productId'] ?? null, $productById);
			$newProductName = isset($row['newProductName']) && is_string($row['newProductName'])
				? trim($row['newProductName'])
				: '';
			if ($productId !== null) {
				$item->setSuggestedProductId($productId);
			} elseif ($newProductName !== '') {
				$item->setSuggestedProductId(null);
				$item->setSuggestedProductName($newProductName);
			}
			$categoryId = $this->validId($row['categoryId'] ?? null, $categoryById);
			if ($categoryId !== null) {
				$item->setSuggestedCategoryId($categoryId);
			}
			$item->setMatchSource('ai');
			$item->setConfidence(min(1.0, max(0.0, $confidence)));
		}
	}

	/**
	 * @param array<string, mixed> $import
	 * @param array<string, mixed> $candidates
	 */
	private function buildPrompt(array $import, array $candidates): string {
		$importJson = json_encode($import, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$candidatesJson = json_encode($candidates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return "You map an imported purchase list to an existing catalog. Return ONLY a JSON object shaped like:\n"
			. '{"storeId": string|null, "newStoreName": string|null, "categories": [{"rawName": string, "categoryId": string|null, "newCategoryName": string|null}], "items": [{"lineId": string, "productId": string|null, "newProductName": string|null, "categoryId": string|null, "confidence": number}]}'
			. "\nRules: use only ids that appear in the candidates; never invent ids. When no candidate fits, set the id to null and suggest a new display name. Confidence is a number between 0 and 1. Coupon items can be left unmatched."
			. "\n\nImport JSON:\n" . ($importJson === false ? '{}' : $importJson)
			. "\n\nCandidate JSON:\n" . ($candidatesJson === false ? '{}' : $candidatesJson);
	}

	/**
	 * @param mixed $decoded
	 * @param array<string, StoreEntity> $storeById
	 */
	private function applyStore(PurchaseImportEntity $import, mixed $decoded, array $storeById): void {
		if (!is_array($decoded)) {
			return;
		}

		$storeId = $this->validId($decoded['storeId'] ?? null, $storeById);
		if ($storeId !== null) {
			$import->setSuggestedStoreId($storeId);
			$import->setStoreMatchSource('ai');
			$import->setStoreConfidence(0.9);
			return;
		}

		$newName = isset($decoded['newStoreName']) && is_string($decoded['newStoreName'])
			? trim($decoded['newStoreName'])
			: '';
		if ($newName !== '') {
			$import->setSuggestedStoreId(null);
			$import->setSuggestedStoreName($newName);
			$import->setStoreMatchSource('ai');
			$import->setStoreConfidence(0.9);
		}
	}

	/**
	 * @param ProductEntity[] $products
	 * @param PurchaseImportItemEntity[] $items
	 *
	 * @return ProductEntity[]
	 */
	private function selectProductCandidates(array $products, array $items): array {
		if (count($products) <= self::MAX_PRODUCT_CANDIDATES) {
			return $products;
		}

		$needleTokens = [];
		foreach ($items as $item) {
			foreach (explode(' ', $this->matcher->normalize((string)$item->getRawName())) as $token) {
				if (mb_strlen($token) >= 3) {
					$needleTokens[$token] = true;
				}
			}
		}

		$scored = [];
		foreach ($products as $product) {
			$score = 0;
			foreach (explode(' ', $this->matcher->normalize((string)$product->getName())) as $token) {
				if (isset($needleTokens[$token])) {
					$score++;
				}
			}
			$scored[] = ['product' => $product, 'score' => $score];
		}

		usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

		return array_map(
			static fn (array $entry): ProductEntity => $entry['product'],
			array_slice($scored, 0, self::MAX_PRODUCT_CANDIDATES),
		);
	}

	private function itemKey(PurchaseImportItemEntity $item): string {
		$lineId = trim((string)$item->getLineId());

		return $lineId !== '' ? $lineId : 'pos:' . (int)$item->getPosition();
	}

	/**
	 * @param array<string, object> $idMap
	 */
	private function validId(mixed $value, array $idMap): ?string {
		if (!is_string($value) || $value === '' || !isset($idMap[$value])) {
			return null;
		}

		return $value;
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
	 * @return array<string, mixed>
	 */
	private function parseJson(string $content): array {
		$clean = trim($content);
		$clean = (string)preg_replace('/^```(?:json)?\s*/', '', $clean);
		$clean = (string)preg_replace('/\s*```$/', '', $clean);

		$decoded = json_decode($clean, true);
		if (!is_array($decoded)) {
			throw new RuntimeException('Failed to parse AI mapping suggestions');
		}

		/** @var array<string, mixed> $decoded */
		return $decoded;
	}

	private function number(mixed $value, float $default): float {
		if (is_int($value) || is_float($value)) {
			return (float)$value;
		}
		if (is_string($value) && is_numeric($value)) {
			return (float)$value;
		}

		return $default;
	}
}
