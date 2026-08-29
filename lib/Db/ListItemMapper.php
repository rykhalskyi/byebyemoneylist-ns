<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use OCA\ByeByeMoneyList\Entity\ListItemEntity;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ListItemEntity>
 */
class ListItemMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_list_items', ListItemEntity::class);
	}

	/**
	 * @return ListItemEntity[]
	 */
	public function findByListId(string $listId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_STR)))
			->orderBy('created_at', 'ASC');

		return $this->findEntities($qb);
	}

	public function findByIdAndListId(string $id, string $listId): ?ListItemEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_STR)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Sum of checked items' totals (price × quantity) grouped by list id.
	 *
	 * @param array<array-key, string> $listIds
	 *
	 * @return array<string, float>
	 */
	public function sumCheckedByListIds(array $listIds): array {
		if ($listIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('list_id')
			->selectAlias($qb->func()->sum($qb->createFunction('price * quantity')), 'total')
			->from($this->tableName)
			->where($qb->expr()->in('list_id', $qb->createNamedParameter($listIds, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($qb->expr()->eq('is_checked', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->isNotNull('price'))
			->groupBy('list_id');

		$result = $qb->executeQuery();
		/** @var list<array{list_id: string, total: float|int|string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		$totals = [];
		foreach ($rows as $row) {
			$totals[$row['list_id']] = (float)$row['total'];
		}

		return $totals;
	}
}
