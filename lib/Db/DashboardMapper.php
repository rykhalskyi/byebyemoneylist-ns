<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use DateTimeInterface;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Read-only spending aggregates over shopping lists for the dashboard widgets.
 *
 * @extends QBMapper<ListEntity>
 */
class DashboardMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_lists', ListEntity::class);
	}

	/**
	 * Sum of finished expense lists created in [from, to), optionally restricted to
	 * lists linked to a category. Non-finished, income and subscription lists are
	 * excluded; the date is the list creation date (D-08).
	 *
	 * @return array{total: float, byCategory: list<array{categoryId: ?string, total: float}>}
	 */
	public function sumFinishedByRange(string $owner, DateTimeInterface $from, DateTimeInterface $to, ?string $categoryId = null): array {
		return [
			'total' => $this->sumTotal($owner, $from, $to, $categoryId),
			'byCategory' => $this->sumByCategory($owner, $from, $to, $categoryId),
		];
	}

	private function sumTotal(string $owner, DateTimeInterface $from, DateTimeInterface $to, ?string $categoryId): float {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->sum('l.final_total'), 'total')
			->from($this->tableName, 'l');

		if ($categoryId !== null) {
			$qb->innerJoin('l', 'bbml_list_categories', 'lc', $qb->expr()->eq('lc.list_id', 'l.id'));
			$qb->andWhere($qb->expr()->eq('lc.category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_STR)));
		}

		$this->applyBaseFilters($qb, $owner, $from, $to);

		$result = $qb->executeQuery();
		/** @var list<array{total: float|int|string|null}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		return (float)($rows[0]['total'] ?? 0.0);
	}

	/**
	 * @return list<array{categoryId: ?string, total: float}>
	 */
	private function sumByCategory(string $owner, DateTimeInterface $from, DateTimeInterface $to, ?string $categoryId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('lc.category_id')
			->selectAlias($qb->func()->sum('l.final_total'), 'total')
			->from($this->tableName, 'l')
			->leftJoin('l', 'bbml_list_categories', 'lc', $qb->expr()->eq('lc.list_id', 'l.id'));

		if ($categoryId !== null) {
			$qb->andWhere($qb->expr()->eq('lc.category_id', $qb->createNamedParameter($categoryId, IQueryBuilder::PARAM_STR)));
		}

		$this->applyBaseFilters($qb, $owner, $from, $to);
		$qb->groupBy('lc.category_id');

		$result = $qb->executeQuery();
		/** @var list<array{category_id: ?string, total: float|int|string|null}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		$breakdown = [];
		foreach ($rows as $row) {
			$breakdown[] = [
				'categoryId' => $row['category_id'],
				'total' => (float)($row['total'] ?? 0.0),
			];
		}

		return $breakdown;
	}

	private function applyBaseFilters(IQueryBuilder $qb, string $owner, DateTimeInterface $from, DateTimeInterface $to): void {
		$qb->andWhere($qb->expr()->eq('l.owner', $qb->createNamedParameter($owner, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('l.is_finished', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('l.is_income', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('l.is_subscription', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->gte('l.created_at', $qb->createNamedParameter($from, IQueryBuilder::PARAM_DATETIME_MUTABLE)))
			->andWhere($qb->expr()->lt('l.created_at', $qb->createNamedParameter($to, IQueryBuilder::PARAM_DATETIME_MUTABLE)));
	}
}
