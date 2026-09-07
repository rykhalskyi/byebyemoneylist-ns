<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<StoreEntity>
 */
class StoreMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_stores', StoreEntity::class);
	}

	/**
	 * @return StoreEntity[]
	 */
	public function findAllByOwner(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orderBy('name', 'ASC');

		return $this->findEntities($qb);
	}

	public function findByIdAndOwner(string $id, string $userId): ?StoreEntity {
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
	 * Store-category junction ids grouped by store id, ordered by junction row id.
	 *
	 * @param array<array-key, string> $storeIds
	 *
	 * @return array<string, list<string>>
	 */
	public function findCategoryIdsByStoreIds(array $storeIds): array {
		if ($storeIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('store_id', 'category_id')
			->from('bbml_store_categories')
			->where($qb->expr()->in('store_id', $qb->createNamedParameter($storeIds, IQueryBuilder::PARAM_STR_ARRAY)))
			->orderBy('id', 'ASC');

		$result = $qb->executeQuery();
		/** @var list<array{store_id: string, category_id: string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		$grouped = [];
		foreach ($rows as $row) {
			$grouped[$row['store_id']][] = $row['category_id'];
		}

		return $grouped;
	}

	/**
	 * Replace the store-category junction rows for a store.
	 *
	 * @param list<string> $categoryIds
	 */
	public function replaceCategoriesByStoreId(string $storeId, array $categoryIds): void {
		$this->deleteCategoriesByStoreId($storeId);

		if ($categoryIds === []) {
			return;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('bbml_store_categories')
			->values([
				'id' => $insert->createParameter('id'),
				'store_id' => $insert->createParameter('store_id'),
				'category_id' => $insert->createParameter('category_id'),
			]);

		foreach ($categoryIds as $categoryId) {
			$insert->setParameter('id', Uuid::v4(), IQueryBuilder::PARAM_STR);
			$insert->setParameter('store_id', $storeId, IQueryBuilder::PARAM_STR);
			$insert->setParameter('category_id', $categoryId, IQueryBuilder::PARAM_STR);
			$insert->executeStatement();
		}
	}

	public function deleteCategoriesByStoreId(string $storeId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('bbml_store_categories')
			->where($qb->expr()->eq('store_id', $qb->createNamedParameter($storeId, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}
}
