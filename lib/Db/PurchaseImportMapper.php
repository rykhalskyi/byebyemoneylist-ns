<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use OCA\ByeByeMoneyList\Entity\PurchaseImportEntity;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<PurchaseImportEntity>
 */
class PurchaseImportMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_purchase_imports', PurchaseImportEntity::class);
	}

	/**
	 * @return PurchaseImportEntity[]
	 */
	public function findAllByOwner(string $owner, ?string $status = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($owner, IQueryBuilder::PARAM_STR)));

		if ($status !== null && $status !== '') {
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_STR)));
		}

		$qb->orderBy('created_at', 'DESC');

		return $this->findEntities($qb);
	}

	public function findByIdAndOwner(string $id, string $owner): ?PurchaseImportEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('owner', $qb->createNamedParameter($owner, IQueryBuilder::PARAM_STR)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function findByOwnerAndClientListId(string $owner, string $clientListId): ?PurchaseImportEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($owner, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('client_list_id', $qb->createNamedParameter($clientListId, IQueryBuilder::PARAM_STR)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
