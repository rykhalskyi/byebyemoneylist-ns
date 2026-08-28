<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ProductEntity>
 */
class ProductMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_products', ProductEntity::class);
	}

	/**
	 * Find all normal products (not subscription, not income) for the current user
	 *
	 * @return ProductEntity[]
	 */
	public function findAllByOwner(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('is_subscription', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('is_income', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->orderBy('name', 'ASC');

		return $this->findEntities($qb);
	}

	public function findByIdAndOwner(string $id, string $userId): ?ProductEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Find the given products of the current user, regardless of type
	 *
	 * @param array<array-key, string> $productIds
	 *
	 * @return ProductEntity[]
	 */
	public function findByIds(array $productIds, string $userId): array {
		if ($productIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->in('id', $qb->createNamedParameter($productIds, IQueryBuilder::PARAM_STR_ARRAY)));

		return $this->findEntities($qb);
	}
}
