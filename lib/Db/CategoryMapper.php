<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<CategoryEntity>
 */
class CategoryMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_categories', CategoryEntity::class);
	}

	/**
	 * @return CategoryEntity[]
	 */
	public function findAllByOwner(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orderBy('name', 'ASC');

		return $this->findEntities($qb);
	}

	public function findByIdAndOwner(string $id, string $userId): ?CategoryEntity {
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

	public function findByNameAndOwner(string $name, string $userId): ?CategoryEntity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Find an expense category by name, creating it when it does not exist yet
	 * (Android `getOrCreate` parity). Only non-income categories are created here.
	 */
	public function getOrCreate(string $name, string $userId): CategoryEntity {
		$trimmed = trim($name);
		if ($trimmed === '') {
			$trimmed = 'General';
		}

		$existing = $this->findByNameAndOwner($trimmed, $userId);
		if ($existing !== null) {
			return $existing;
		}

		$category = new CategoryEntity();
		$category->setId(Uuid::v4());
		$category->setOwner($userId);
		$category->setName($trimmed);
		$category->setIncome(false);
		$category->setStatus('confirmed');

		return $this->insert($category);
	}
}
