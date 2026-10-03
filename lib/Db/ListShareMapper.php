<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use OCA\ByeByeMoneyList\Entity\ListShareEntity;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ListShareEntity>
 */
class ListShareMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_list_shares', ListShareEntity::class);
	}

	/**
	 * Find a share by its id.
	 */
	public function findById(string $id): ?ListShareEntity {
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

	/**
	 * All shares of a list, any status (owner management view).
	 *
	 * @return ListShareEntity[]
	 */
	public function findByListId(string $listId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_STR)))
			->orderBy('created_at', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * Active share between a list and a guest, if any.
	 */
	public function findActiveByListAndUser(string $listId, string $sharedWith): ?ListShareEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('shared_with', $qb->createNamedParameter($sharedWith, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(ListShareEntity::STATUS_ACTIVE, IQueryBuilder::PARAM_STR)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Any share (including revoked) between a list and a guest.
	 */
	public function findByListAndUser(string $listId, string $sharedWith): ?ListShareEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('list_id', $qb->createNamedParameter($listId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('shared_with', $qb->createNamedParameter($sharedWith, IQueryBuilder::PARAM_STR)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Shares (any status) received by a user, newest first. Includes revoked rows
	 * so the guest can still render a name-only placeholder.
	 *
	 * @return ListShareEntity[]
	 */
	public function findByRecipient(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('shared_with', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orderBy('created_at', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * Ids of lists actively shared with a user.
	 *
	 * @return list<string>
	 */
	public function findActiveListIdsByRecipient(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('list_id')
			->from($this->tableName)
			->where($qb->expr()->eq('shared_with', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(ListShareEntity::STATUS_ACTIVE, IQueryBuilder::PARAM_STR)));

		$result = $qb->executeQuery();
		/** @var list<array{list_id: string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static fn (array $row): string => $row['list_id'], $rows);
	}

	/**
	 * Active shares created by a user.
	 *
	 * @return ListShareEntity[]
	 */
	public function findActiveByOwner(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(ListShareEntity::STATUS_ACTIVE, IQueryBuilder::PARAM_STR)))
			->orderBy('created_at', 'DESC');

		return $this->findEntities($qb);
	}
}
