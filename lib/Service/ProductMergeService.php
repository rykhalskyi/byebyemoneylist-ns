<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service;

use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\ProductPriceMapper;
use OCA\ByeByeMoneyList\Entity\ProductAliasEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Merges two products of the same owner into one: the chosen fields of the
 * primary product are kept, aliases of both products (and the dropped names) are
 * concatenated, list items and price records are re-pointed to the primary, and
 * the secondary product is deleted.
 *
 * @psalm-suppress UnusedClass
 */
class ProductMergeService {
	private IDBConnection $db;
	private ProductMapper $productMapper;
	private ProductAliasMapper $productAliasMapper;
	private ProductPriceMapper $productPriceMapper;
	private ListItemMapper $listItemMapper;
	private ProductPictureService $pictureService;
	private LoggerInterface $logger;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		IDBConnection $db,
		ProductMapper $productMapper,
		ProductAliasMapper $productAliasMapper,
		ProductPriceMapper $productPriceMapper,
		ListItemMapper $listItemMapper,
		ProductPictureService $pictureService,
		LoggerInterface $logger,
	) {
		$this->db = $db;
		$this->productMapper = $productMapper;
		$this->productAliasMapper = $productAliasMapper;
		$this->productPriceMapper = $productPriceMapper;
		$this->listItemMapper = $listItemMapper;
		$this->pictureService = $pictureService;
		$this->logger = $logger;
	}

	/**
	 * @param 'primary'|'secondary'|'none' $pictureFrom which product's picture to keep
	 */
	public function merge(
		string $userId,
		ProductEntity $primary,
		ProductEntity $secondary,
		string $name,
		?string $categoryId,
		?string $barcode,
		bool $isFavorite,
		bool $isSubscription,
		bool $isIncome,
		string $pictureFrom,
	): ProductEntity {
		$primaryId = $primary->getId();
		$secondaryId = $secondary->getId();
		$primaryPath = $primary->getPicturePath();
		$secondaryPath = $secondary->getPicturePath();

		$targetPath = $primaryPath;
		$copiedPath = null;
		if ($pictureFrom === 'none') {
			$targetPath = null;
		} elseif ($pictureFrom === 'secondary') {
			$copiedPath = $this->pictureService->copy($userId, $secondaryPath, $primaryId);
			// Keep the primary picture if the secondary file could not be copied.
			$targetPath = $copiedPath ?? $primaryPath;
		}

		$aliases = $this->collectAliases($userId, $primary, $secondary, $name);

		try {
			$this->db->beginTransaction();

			$primary->setName($name);
			$primary->setCategoryId($categoryId);
			$primary->setBarcode($barcode);
			$primary->setIsFavorite($isFavorite);
			$primary->setIsSubscription($isSubscription);
			$primary->setIsIncome($isIncome);
			$primary->setStatus('reviewed');
			$primary->setPicturePath($targetPath);
			$updated = $this->productMapper->update($primary);

			$this->productAliasMapper->deleteByProductId($primaryId, $userId);
			$this->productAliasMapper->deleteByProductId($secondaryId, $userId);
			foreach ($aliases as $aliasData) {
				$alias = new ProductAliasEntity();
				$alias->setId(Uuid::v4());
				$alias->setOwner($userId);
				$alias->setProductId($primaryId);
				$alias->setAliasName($aliasData['name']);
				if ($aliasData['storeId'] !== null) {
					$alias->setStoreId($aliasData['storeId']);
				}
				$this->productAliasMapper->insert($alias);
			}

			$this->listItemMapper->reassignProduct($secondaryId, $primaryId, $userId);
			$this->reassignPrices($userId, $secondaryId, $primaryId);
			$this->productMapper->delete($secondary);

			$this->db->commit();

			$this->cleanupPictures($userId, $primaryPath, $secondaryPath, $targetPath);

			return $updated;
		} catch (\Exception $e) {
			$this->db->rollBack();
			if ($copiedPath !== null && $copiedPath !== $primaryPath) {
				try {
					$this->pictureService->delete($userId, $copiedPath);
				} catch (\Exception $cleanupError) {
					$this->logger->warning('Failed to clean up a copied product picture', ['exception' => $cleanupError]);
				}
			}
			$this->logger->error('Failed to merge products', ['exception' => $e]);
			throw new RuntimeException('Failed to merge products', 0, $e);
		}
	}

	/**
	 * Concatenate the aliases of both products plus their original names, remove
	 * duplicates (case-insensitive) and the chosen result name. Alias store
	 * associations are carried over so matching stays store-aware.
	 *
	 * @return list<array{name: string, storeId: ?string}>
	 */
	private function collectAliases(string $userId, ProductEntity $primary, ProductEntity $secondary, string $name): array {
		$ids = [$primary->getId(), $secondary->getId()];
		$candidates = [
			['name' => $primary->getName() ?? '', 'storeId' => null],
			['name' => $secondary->getName() ?? '', 'storeId' => null],
		];
		foreach ($this->productAliasMapper->findByProductIds($ids, $userId) as $alias) {
			$candidates[] = [
				'name' => $alias->getAliasName() ?? '',
				'storeId' => $alias->getStoreId(),
			];
		}

		$resultName = mb_strtolower(trim($name));
		$seen = [];
		$aliases = [];
		foreach ($candidates as $candidate) {
			$trimmed = trim($candidate['name']);
			if ($trimmed === '') {
				continue;
			}
			$lower = mb_strtolower($trimmed);
			if ($lower === $resultName || isset($seen[$lower])) {
				continue;
			}
			$seen[$lower] = true;
			$aliases[] = ['name' => $trimmed, 'storeId' => $candidate['storeId']];
		}

		return $aliases;
	}

	/**
	 * Move the secondary product's prices to the primary, merging records that
	 * share a store and keeping the most recent value.
	 */
	private function reassignPrices(string $userId, string $fromProductId, string $toProductId): void {
		foreach ($this->productPriceMapper->findByProductIdAndOwner($fromProductId, $userId) as $price) {
			$existing = $this->productPriceMapper->findByProductAndStore($toProductId, $price->getStoreId(), $userId);
			if ($existing === null) {
				$price->setProductId($toProductId);
				$this->productPriceMapper->update($price);
				continue;
			}

			$priceDate = $price->getPriceDate();
			$existingDate = $existing->getPriceDate();
			if ($priceDate !== null && ($existingDate === null || $priceDate > $existingDate)) {
				$value = $price->getValue();
				if ($value !== null) {
					$existing->setValue($value);
				}
				$existing->setPriceDate($priceDate);
				$this->productPriceMapper->update($existing);
			}
			$this->productPriceMapper->delete($price);
		}
	}

	private function cleanupPictures(string $userId, ?string $primaryPath, ?string $secondaryPath, ?string $targetPath): void {
		foreach ([$primaryPath, $secondaryPath] as $path) {
			if ($path === null || $path === '' || $path === $targetPath) {
				continue;
			}
			try {
				$this->pictureService->delete($userId, $path);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to remove a merged product picture', ['exception' => $e]);
			}
		}
	}
}
