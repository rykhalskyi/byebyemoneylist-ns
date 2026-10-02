<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use OCA\ByeByeMoneyList\Entity\CatalogShareEntity;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<CatalogShareEntity>
 */
class CatalogShareMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_catalog_shares', CatalogShareEntity::class);
	}

	/**
	 * Active grants on an item, regardless of recipient.
	 *
	 * @return CatalogShareEntity[]
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	public function findActiveByItem(string $itemType, string $itemId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('item_type', $qb->createNamedParameter($itemType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(CatalogShareEntity::STATUS_ACTIVE, IQueryBuilder::PARAM_STR)));

		return $this->findEntities($qb);
	}

	/**
	 * Any grant (including revoked) for an item and recipient.
	 */
	public function findByItemAndUser(string $itemType, string $itemId, string $sharedWith): ?CatalogShareEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('item_type', $qb->createNamedParameter($itemType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('shared_with', $qb->createNamedParameter($sharedWith, IQueryBuilder::PARAM_STR)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Distinct owners who published a catalog item of any type to a recipient.
	 *
	 * @return list<string>
	 */
	public function findActiveOwnerIdsByRecipient(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('owner')
			->from($this->tableName)
			->where($qb->expr()->eq('shared_with', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(CatalogShareEntity::STATUS_ACTIVE, IQueryBuilder::PARAM_STR)));

		$result = $qb->executeQuery();
		/** @var list<array{owner: string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static fn (array $row): string => $row['owner'], $rows);
	}

	/**
	 * Revoke every active grant of an item (e.g. when the item is deleted).
	 */
	public function revokeByItem(string $itemType, string $itemId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('status', $qb->createNamedParameter(CatalogShareEntity::STATUS_REVOKED, IQueryBuilder::PARAM_STR))
			->where($qb->expr()->eq('item_type', $qb->createNamedParameter($itemType, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('item_id', $qb->createNamedParameter($itemId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(CatalogShareEntity::STATUS_ACTIVE, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}
}
