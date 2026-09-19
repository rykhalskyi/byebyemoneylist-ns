<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use DateTimeInterface;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Read-only monthly analytics aggregates over shopping lists.
 *
 * Mirrors the dashboard semantics ([D-08]): lists are scoped to their owner,
 * must be finished and non-subscription, and are placed in the month by
 * `created_at`. Income and expense lists are reported separately.
 *
 * Each list belongs to at most one analytics category: the first
 * `bbml_list_categories` row ordered by junction id (the app's de-facto primary
 * category), so the pie segments sum to the month total instead of
 * double-counting multi-category lists.
 *
 * @extends QBMapper<ListEntity>
 */
class AnalyticsMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'bbml_lists', ListEntity::class);
	}

	/**
	 * @return array{
	 *   totalSpent: float,
	 *   totalIncome: float,
	 *   byCategory: list<array{categoryId: ?string, total: float}>,
	 *   byStore: list<array{storeId: ?string, total: float}>,
	 *   byList: list<array{listId: string, name: string, total: float}>
	 * }
	 */
	public function overview(string $owner, DateTimeInterface $from, DateTimeInterface $to): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'name', 'store_id', 'final_total', 'is_income')
			->from($this->tableName, 'l')
			->where($qb->expr()->eq('l.owner', $qb->createNamedParameter($owner, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('l.is_finished', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('l.is_subscription', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->gte('l.created_at', $qb->createNamedParameter($from, IQueryBuilder::PARAM_DATETIME_MUTABLE)))
			->andWhere($qb->expr()->lt('l.created_at', $qb->createNamedParameter($to, IQueryBuilder::PARAM_DATETIME_MUTABLE)));

		$result = $qb->executeQuery();
		/** @var list<array{id: string, name: string, store_id: ?string, final_total: float|int|string|null, is_income: bool|int|string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		if ($rows === []) {
			return [
				'totalSpent' => 0.0,
				'totalIncome' => 0.0,
				'byCategory' => [],
				'byStore' => [],
				'byList' => [],
			];
		}

		$listIds = array_map(static fn (array $row): string => $row['id'], $rows);
		$primaryCategories = $this->findPrimaryCategoryByListIds($listIds);

		$totalSpent = 0.0;
		$totalIncome = 0.0;
		$byCategory = [];
		$byStore = [];
		$byList = [];

		foreach ($rows as $row) {
			$total = (float)($row['final_total'] ?? 0.0);
			$isIncome = (bool)$row['is_income'];

			if ($isIncome) {
				$totalIncome += $total;
				continue;
			}

			$totalSpent += $total;
			$byList[] = [
				'listId' => $row['id'],
				'name' => $row['name'],
				'total' => $total,
			];

			$categoryId = $primaryCategories[$row['id']] ?? '';
			$byCategory[$categoryId] = ($byCategory[$categoryId] ?? 0.0) + $total;
			$storeKey = $row['store_id'] ?? '';
			$byStore[$storeKey] = ($byStore[$storeKey] ?? 0.0) + $total;
		}

		return [
			'totalSpent' => $totalSpent,
			'totalIncome' => $totalIncome,
			'byCategory' => $this->toCategoryBreakdown($byCategory),
			'byStore' => $this->toStoreBreakdown($byStore),
			'byList' => $this->sortByTotalDesc($byList, 'total'),
		];
	}

	/**
	 * Map each list to its first category (by junction id), mirroring how the
	 * list API exposes a single `categoryId`.
	 *
	 * @param list<string> $listIds
	 *
	 * @return array<string, string>
	 */
	private function findPrimaryCategoryByListIds(array $listIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('list_id', 'category_id')
			->from('bbml_list_categories')
			->where($qb->expr()->in('list_id', $qb->createNamedParameter($listIds, IQueryBuilder::PARAM_STR_ARRAY)))
			->orderBy('id', 'ASC');

		$result = $qb->executeQuery();
		/** @var list<array{list_id: string, category_id: string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		$primary = [];
		foreach ($rows as $row) {
			if (!isset($primary[$row['list_id']])) {
				$primary[$row['list_id']] = $row['category_id'];
			}
		}

		return $primary;
	}

	/**
	 * @param array<string, float> $totals
	 *
	 * @return list<array{categoryId: ?string, total: float}>
	 */
	private function toCategoryBreakdown(array $totals): array {
		$breakdown = [];
		foreach ($totals as $categoryId => $total) {
			$breakdown[] = [
				'categoryId' => $categoryId === '' ? null : $categoryId,
				'total' => $total,
			];
		}

		return $this->sortByTotalDesc($breakdown, 'total');
	}

	/**
	 * @param array<string, float> $totals
	 *
	 * @return list<array{storeId: ?string, total: float}>
	 */
	private function toStoreBreakdown(array $totals): array {
		$breakdown = [];
		foreach ($totals as $storeId => $total) {
			$breakdown[] = [
				'storeId' => $storeId === '' ? null : $storeId,
				'total' => $total,
			];
		}

		return $this->sortByTotalDesc($breakdown, 'total');
	}

	/**
	 * @template T of array<string, mixed>
	 *
	 * @param list<T> $rows
	 *
	 * @return list<T>
	 */
	private function sortByTotalDesc(array $rows, string $key): array {
		usort($rows, static fn (array $a, array $b): int => ($b[$key] <=> $a[$key]));

		return $rows;
	}
}
