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

	/**
	 * List-category junction ids grouped by list id, ordered by junction row id.
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
			->orderBy('id', 'ASC');

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

	/**
	 * Remove every junction row that references the given category
	 */
	public function deleteCategoriesByCategoryId(string $categoryId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('bbml_list_categories')
			->where($qb->expr()->eq('category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}
}
