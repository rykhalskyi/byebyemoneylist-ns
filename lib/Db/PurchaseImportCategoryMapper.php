<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use OCA\ByeByeMoneyList\Entity\PurchaseImportCategoryEntity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<PurchaseImportCategoryEntity>
 */
class PurchaseImportCategoryMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_purchase_import_categories', PurchaseImportCategoryEntity::class);
	}

	/**
	 * @return PurchaseImportCategoryEntity[]
	 */
	public function findByImportId(string $importId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('import_id', $qb->createNamedParameter($importId, IQueryBuilder::PARAM_STR)))
			->orderBy('raw_name', 'ASC');

		return $this->findEntities($qb);
	}

	public function countByImportId(string $importId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from($this->tableName)
			->where($qb->expr()->eq('import_id', $qb->createNamedParameter($importId, IQueryBuilder::PARAM_STR)));

		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();

		return $count;
	}

	public function deleteByImportId(string $importId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('import_id', $qb->createNamedParameter($importId, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}
}
