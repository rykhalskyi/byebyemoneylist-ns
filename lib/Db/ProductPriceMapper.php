<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use OCA\ByeByeMoneyList\Entity\ProductPriceEntity;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ProductPriceEntity>
 */
class ProductPriceMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_product_prices', ProductPriceEntity::class);
	}

	/**
	 * @return ProductPriceEntity[]
	 */
	public function findByProductIdAndOwner(string $productId, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('product_id', $qb->createNamedParameter($productId, IQueryBuilder::PARAM_STR)))
			->orderBy('price_date', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Find the latest price record per product for the given products, keyed by
	 * product id. "Latest" is the most recent price_date (then created_at) so a
	 * single query covers the whole batch and avoids N+1 lookups.
	 *
	 * @param list<string> $productIds
	 *
	 * @return array<string, ProductPriceEntity>
	 */
	public function findLatestByProductIds(array $productIds, string $userId): array {
		if ($productIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->in('product_id', $qb->createNamedParameter($productIds, IQueryBuilder::PARAM_STR_ARRAY)))
			->orderBy('price_date', 'DESC')
			->addOrderBy('created_at', 'DESC');

		$latest = [];
		foreach ($this->findEntities($qb) as $price) {
			$productId = $price->getProductId();
			if ($productId === null || isset($latest[$productId])) {
				continue;
			}
			$latest[$productId] = $price;
		}

		return $latest;
	}

	/**
	 * Find the current price record for a (product, store) pair, if any.
	 */
	public function findByProductAndStore(string $productId, ?string $storeId, string $userId): ?ProductPriceEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('product_id', $qb->createNamedParameter($productId, IQueryBuilder::PARAM_STR)));

		if ($storeId === null) {
			$qb->andWhere($qb->expr()->isNull('store_id'));
		} else {
			$qb->andWhere($qb->expr()->eq('store_id', $qb->createNamedParameter($storeId, IQueryBuilder::PARAM_STR)));
		}

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
