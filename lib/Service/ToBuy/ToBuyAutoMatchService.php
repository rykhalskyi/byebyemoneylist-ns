<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service\ToBuy;

use DateTime;
use DateTimeZone;
use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\LlmProfileMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Entity\LlmProfileEntity;
use OCA\ByeByeMoneyList\Service\Receipt\OpenAiCompatibleScanner;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Best-effort LLM matching of purchased names against the pending items of the
 * active To Buy list. Never blocks or fails the purchase.
 *
 * @psalm-suppress UnusedClass
 */
class ToBuyAutoMatchService {
	private const MAX_PENDING_ITEMS = 100;

	private LlmProfileMapper $profileMapper;
	private ICrypto $crypto;
	private OpenAiCompatibleScanner $scanner;
	private ListMapper $listMapper;
	private ListItemMapper $itemMapper;
	private ProductMapper $productMapper;
	private LoggerInterface $logger;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		LlmProfileMapper $profileMapper,
		ICrypto $crypto,
		OpenAiCompatibleScanner $scanner,
		ListMapper $listMapper,
		ListItemMapper $itemMapper,
		ProductMapper $productMapper,
		LoggerInterface $logger,
	) {
		$this->profileMapper = $profileMapper;
		$this->crypto = $crypto;
		$this->scanner = $scanner;
		$this->listMapper = $listMapper;
		$this->itemMapper = $itemMapper;
		$this->productMapper = $productMapper;
		$this->logger = $logger;
	}

	/**
	 * Check pending To Buy items against the checked items of a finalized list.
	 */
	public function matchAfterPurchaseList(string $userId, string $listId): void {
		try {
			$names = [];
			$productIds = [];
			foreach ($this->itemMapper->findByListId($listId) as $item) {
				if (!($item->getIsChecked() ?? false) || ($item->getQuantity() ?? 0) <= 0) {
					continue;
				}
				$name = trim($item->getCustomName() ?? '');
				if ($name !== '') {
					$names[] = $name;
				} elseif ($item->getProductId() !== null) {
					$productIds[] = (string)$item->getProductId();
				}
			}
			if ($productIds !== []) {
				foreach ($this->productMapper->findByIdsForOwners($productIds, [$userId]) as $product) {
					$name = trim($product->getName() ?? '');
					if ($name !== '') {
						$names[] = $name;
					}
				}
			}
			$this->matchAfterPurchase($userId, $names);
		} catch (\Throwable $e) {
			$this->logger->warning('To Buy auto-check could not resolve purchased names', ['exception' => $e]);
		}
	}

	/**
	 * @param list<string> $purchasedNames non-coupon purchased item names
	 */
	public function matchAfterPurchase(string $userId, array $purchasedNames): void {
		$purchased = $this->normalizeNames($purchasedNames);
		if ($purchased === []) {
			return;
		}

		try {
			$list = $this->listMapper->findActiveToBuyList($userId);
			if ($list === null) {
				return;
			}
			$pending = $this->pendingItems($userId, $list->getId());
			if ($pending === []) {
				return;
			}
			$profile = $this->profileMapper->findActiveByOwner($userId);
			if ($profile === null) {
				return;
			}
			$matchedIds = $this->askModel($profile, $purchased, $pending);
			if ($matchedIds === []) {
				return;
			}
			$this->applyMatches($userId, $list->getId(), $matchedIds);
		} catch (\Throwable $e) {
			$this->logger->warning('To Buy auto-check failed', ['exception' => $e]);
		}
	}

	/**
	 * @param list<string> $names
	 *
	 * @return list<string>
	 */
	private function normalizeNames(array $names): array {
		$normalized = [];
		foreach ($names as $name) {
			$name = trim($name);
			if ($name === '' || in_array($name, $normalized, true)) {
				continue;
			}
			$normalized[] = $name;
		}

		return $normalized;
	}

	/**
	 * @return array<string, string> pending item id => display name
	 */
	private function pendingItems(string $userId, string $listId): array {
		$pending = [];
		$productIds = [];
		$items = $this->itemMapper->findByListId($listId);
		foreach ($items as $item) {
			if ($item->getIsChecked() ?? false) {
				continue;
			}
			$name = trim($item->getCustomName() ?? '');
			if ($name === '' && $item->getProductId() !== null) {
				$productIds[$item->getId()] = (string)$item->getProductId();
				continue;
			}
			if ($name !== '') {
				$pending[$item->getId()] = $name;
			}
		}

		if ($productIds !== []) {
			$namesById = [];
			foreach ($this->productMapper->findByIdsForOwners(array_values($productIds), [$userId]) as $product) {
				$namesById[$product->getId()] = trim($product->getName() ?? '');
			}
			foreach ($productIds as $itemId => $productId) {
				$name = $namesById[$productId] ?? '';
				if ($name !== '') {
					$pending[$itemId] = $name;
				}
			}
		}

		if (count($pending) > self::MAX_PENDING_ITEMS) {
			$pending = array_slice($pending, 0, self::MAX_PENDING_ITEMS, true);
		}

		return $pending;
	}

	/**
	 * @param list<string> $purchased
	 * @param array<string, string> $pending
	 *
	 * @return list<string>
	 */
	private function askModel(LlmProfileEntity $profile, array $purchased, array $pending): array {
		$pendingPayload = [];
		foreach ($pending as $id => $name) {
			$pendingPayload[] = ['id' => $id, 'name' => $name];
		}

		$prompt = 'You match items that were just purchased against a pending shopping list. '
			. 'Return ONLY a JSON object shaped like {"matchedIds": [string, ...]}. '
			. 'Use only ids from Pending JSON. Match across languages, synonyms and small spelling differences. '
			. 'Omit any item you are not confident about; it is better to miss one than to check the wrong item.'
			. "\n\nPurchased JSON:\n" . $this->encode($purchased)
			. "\n\nPending JSON:\n" . $this->encode($pendingPayload);

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
		$ids = $decoded['matchedIds'] ?? [];
		if (!is_array($ids)) {
			return [];
		}

		$ids = array_values(array_filter($ids, static fn (mixed $value): bool => is_string($value)));

		$matched = [];
		foreach ($ids as $id) {
			if (isset($pending[$id]) && !in_array($id, $matched, true)) {
				$matched[] = $id;
			}
		}

		return $matched;
	}

	/**
	 * @param list<string> $matchedIds
	 */
	private function applyMatches(string $userId, string $listId, array $matchedIds): void {
		$active = $this->listMapper->findActiveToBuyList($userId);
		if ($active === null || $active->getId() !== $listId) {
			return;
		}

		$now = new DateTime('now', new DateTimeZone('UTC'));
		$matched = array_fill_keys($matchedIds, true);
		foreach ($this->itemMapper->findByListId($listId) as $item) {
			$id = $item->getId();
			if (!isset($matched[$id]) || ($item->getIsChecked() ?? false)) {
				continue;
			}
			$item->setIsChecked(true);
			$item->setUpdatedAt($now);
			$this->itemMapper->update($item);
		}
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
			throw new RuntimeException('Failed to parse the To Buy match result');
		}

		/** @var array<string, mixed> $decoded */
		return $decoded;
	}

	private function encode(mixed $value): string {
		$encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		return $encoded === false ? '[]' : $encoded;
	}
}
