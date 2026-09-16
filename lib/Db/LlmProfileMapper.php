<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use OCA\ByeByeMoneyList\Entity\LlmProfileEntity;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<LlmProfileEntity>
 */
class LlmProfileMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_llm_profiles', LlmProfileEntity::class);
	}

	/**
	 * @return LlmProfileEntity[]
	 */
	public function findAllByOwner(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orderBy('created_at', 'ASC');

		return $this->findEntities($qb);
	}

	public function findByIdAndOwner(string $id, string $userId): ?LlmProfileEntity {
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

	public function findActiveByOwner(string $userId): ?LlmProfileEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('is_active', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Set a profile active (deactivating all others for this user) or deactivate it.
	 */
	public function setActive(string $id, string $userId, bool $active): void {
		$this->db->beginTransaction();
		try {
			// Clear active flag for all profiles of this user
			$qbDeactivate = $this->db->getQueryBuilder();
			$qbDeactivate->update($this->tableName)
				->set('is_active', $qbDeactivate->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
				->where($qbDeactivate->expr()->eq('owner', $qbDeactivate->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
			$qbDeactivate->executeStatement();

			if ($active) {
				$qbActivate = $this->db->getQueryBuilder();
				$qbActivate->update($this->tableName)
					->set('is_active', $qbActivate->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
					->where($qbActivate->expr()->eq('id', $qbActivate->createNamedParameter($id, IQueryBuilder::PARAM_STR)))
					->andWhere($qbActivate->expr()->eq('owner', $qbActivate->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
				$qbActivate->executeStatement();
			}

			$this->db->commit();
		} catch (\Exception $e) {
			$this->db->rollBack();
			throw $e;
		}
	}
}
