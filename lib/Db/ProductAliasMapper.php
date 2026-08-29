<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use OCA\ByeByeMoneyList\Entity\ProductAliasEntity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ProductAliasEntity>
 */
class ProductAliasMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_product_aliases', ProductAliasEntity::class);
	}

	/**
	 * Find all aliases for the given products of the current user
	 *
	 * @param array<array-key, string> $productIds
	 *
	 * @return ProductAliasEntity[]
	 */
	public function findByProductIds(array $productIds, string $userId): array {
		if ($productIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->in('product_id', $qb->createNamedParameter($productIds, IQueryBuilder::PARAM_STR_ARRAY)));

		return $this->findEntities($qb);
	}

	/**
	 * Delete all aliases for the given product of the current user
	 */
	public function deleteByProductId(string $productId, string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('product_id', $qb->createNamedParameter($productId, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}
}
