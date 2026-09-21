<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Db;

use DateTimeInterface;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Util\CategorySpendingSplitter;
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
 * Each expense list total is split across the product categories of its items so
 * drilling into a parent category reveals the purchased subcategories. A list
 * with no priced items falls back to its stored `bbml_lists.category_id` (the
 * user-selected category). Segments still sum to the month total instead of
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
		$qb->select('id', 'name', 'store_id', 'category_id', 'final_total', 'is_income')
			->from($this->tableName, 'l')
			->where($qb->expr()->eq('l.owner', $qb->createNamedParameter($owner, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('l.is_finished', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->eq('l.is_subscription', $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL)))
			->andWhere($qb->expr()->gte('l.created_at', $qb->createNamedParameter($from, IQueryBuilder::PARAM_DATETIME_MUTABLE)))
			->andWhere($qb->expr()->lt('l.created_at', $qb->createNamedParameter($to, IQueryBuilder::PARAM_DATETIME_MUTABLE)));

		$result = $qb->executeQuery();
		/** @var list<array{id: string, name: string, store_id: ?string, category_id: ?string, final_total: float|int|string|null, is_income: bool|int|string}> $rows */
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

		$totalSpent = 0.0;
		$totalIncome = 0.0;
		$byStore = [];
		$byList = [];
		/** @var array<string, array{total: float, categoryId: ?string}> $expenseLists */
		$expenseLists = [];

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

			$expenseLists[$row['id']] = [
				'total' => $total,
				'categoryId' => $row['category_id'],
			];
			$storeKey = $row['store_id'] ?? '';
			$byStore[$storeKey] = ($byStore[$storeKey] ?? 0.0) + $total;
		}

		$weightsByList = $this->findItemWeightsByListIds(array_keys($expenseLists));
		$splitInputs = [];
		foreach ($expenseLists as $listId => $entry) {
			$splitInputs[] = [
				'total' => $entry['total'],
				'categoryId' => $entry['categoryId'],
				'weights' => $weightsByList[$listId] ?? [],
			];
		}

		return [
			'totalSpent' => $totalSpent,
			'totalIncome' => $totalIncome,
			'byCategory' => $this->toCategoryBreakdown(CategorySpendingSplitter::split($splitInputs)),
			'byStore' => $this->toStoreBreakdown($byStore),
			'byList' => $this->sortByTotalDesc($byList, 'total'),
		];
	}

	/**
	 * Sum item weights per list and product category.
	 *
	 * Weight mirrors the Android item total: `price * quantity - discount`,
	 * clamped at zero. Items without a resolvable product category are keyed by
	 * the empty string so they end up in the uncategorized bucket.
	 *
	 * @param list<string> $listIds
	 *
	 * @return array<string, array<string, float>> list id => (category id or '' => weight)
	 */
	private function findItemWeightsByListIds(array $listIds): array {
		if ($listIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('li.list_id', 'p.category_id', 'li.price', 'li.quantity', 'li.discount')
			->from('bbml_list_items', 'li')
			->leftJoin('li', 'bbml_products', 'p', 'li.product_id = p.id')
			->where($qb->expr()->in('li.list_id', $qb->createNamedParameter($listIds, IQueryBuilder::PARAM_STR_ARRAY)));

		$result = $qb->executeQuery();
		/** @var list<array{list_id: string, category_id: ?string, price: float|int|string|null, quantity: float|int|string|null, discount: float|int|string|null}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		$weights = [];
		foreach ($rows as $row) {
			$price = (float)($row['price'] ?? 0.0);
			$quantity = (float)($row['quantity'] ?? 0.0);
			$discount = (float)($row['discount'] ?? 0.0);
			$weight = max(0.0, $price * $quantity - $discount);
			if ($weight <= 0.0) {
				continue;
			}

			$categoryKey = $row['category_id'] ?? '';
			$weights[$row['list_id']][$categoryKey] = ($weights[$row['list_id']][$categoryKey] ?? 0.0) + $weight;
		}

		return $weights;
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
