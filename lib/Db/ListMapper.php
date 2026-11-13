<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ListEntity>
 */
class ListMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_lists', ListEntity::class);
	}

	/**
	 * @return ListEntity[]
	 */
	public function findAllByOwner(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orderBy('created_at', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * Highest sort position used by the owner's lists (0 when there are none).
	 */
	public function getMaxPosition(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('position'))
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		$result = $qb->executeQuery();
		$max = (int)($result->fetchOne() ?? 0);
		$result->closeCursor();

		return $max;
	}

	public function findById(string $id): ?ListEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function findByIdAndOwner(string $id, string $userId): ?ListEntity {
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

	public function findActiveToBuyList(string $userId): ?ListEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('kind', $qb->createNamedParameter(ListEntity::KIND_NEED_TO_BUY, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('is_active', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->orderBy('created_at', 'DESC')
			->addOrderBy('id', 'DESC')
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function deactivateAllToBuyLists(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('is_active', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('kind', $qb->createNamedParameter(ListEntity::KIND_NEED_TO_BUY, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}

	/**
	 * Deactivate the owner's previous To Buy list and insert the new active one.
	 * The caller is responsible for wrapping this in a transaction when the
	 * switch must be atomic with additional writes.
	 */
	public function insertActiveToBuyList(ListEntity $list): ListEntity {
		$owner = $list->getOwner();
		if ($owner === null || $owner === '') {
			throw new \InvalidArgumentException('An active To Buy list needs an owner');
		}

		$this->deactivateAllToBuyLists($owner);

		return $this->insert($list);
	}

	/**
	 * List-category junction category ids grouped by list id.
	 *
	 * Ordered deterministically by category id (the junction primary key is a
	 * random UUID, so ordering by it was nondeterministic). Callers that need the
	 * user-selected primary category first should reorder using the list entity.
	 *
	 * @param array<array-key, string> $listIds
	 *
	 * @return array<string, list<string>>
	 */
	public function findCategoryIdsByListIds(array $listIds): array {
		if ($listIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('list_id', 'category_id')
			->from('bbml_list_categories')
			->where($qb->expr()->in('list_id', $qb->createNamedParameter($listIds, IQueryBuilder::PARAM_STR_ARRAY)))
			->orderBy('list_id', 'ASC')
			->addOrderBy('category_id', 'ASC');

		$result = $qb->executeQuery();
		/** @var list<array{list_id: string, category_id: string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		$grouped = [];
		foreach ($rows as $row) {
			$grouped[$row['list_id']][] = $row['category_id'];
		}

		return $grouped;
	}

	/**
	 * Replace the list-category junction rows for a list.
	 *
	 * @param list<string> $categoryIds
	 */
	public function replaceCategoriesByListId(string $listId, array $categoryIds): void {
		$this->deleteCategoriesByListId($listId);

		if ($categoryIds === []) {
			return;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('bbml_list_categories')
			->values([
				'id' => $insert->createParameter('id'),
				'list_id' => $insert->createParameter('list_id'),
				'category_id' => $insert->createParameter('category_id'),
			]);

		foreach ($categoryIds as $categoryId) {
			$insert->setParameter('id', Uuid::v4(), IQueryBuilder::PARAM_STR);
			$insert->setParameter('list_id', $listId, IQueryBuilder::PARAM_STR);
			$insert->setParameter('category_id', $categoryId, IQueryBuilder::PARAM_STR);
			$insert->executeStatement();
		}
	}

	public function deleteCategoriesByListId(string $listId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('bbml_list_categories')
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}
}
