<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Util;

/**
 * Splits finished-list totals across the product categories of their items.
 *
 * Analytics used to attribute a whole shopping list to a single "primary" list
 * category, so drilling into a parent category showed only that one category even
 * when the purchased items belonged to many subcategories. Each list total is
 * instead distributed proportionally to its item weights; a list without any item
 * weight falls back to its own category.
 */
final class CategorySpendingSplitter {
	/**
	 * `weights` maps a category id ('' for uncategorized) to the summed item weight.
	 *
	 * @param list<array{total: float, categoryId: ?string, weights: array<string, float>}> $lists
	 *
	 * @return array<string, float> category id ('' for uncategorized) => total
	 */
	public static function split(array $lists): array {
		$totals = [];
		foreach ($lists as $list) {
			$total = $list['total'];
			if ($total <= 0.0) {
				continue;
			}

			$weights = array_filter(
				$list['weights'],
				static fn (float $weight): bool => $weight > 0.0,
			);
			$weightSum = array_sum($weights);
			if ($weights === [] || $weightSum <= 0.0) {
				$key = $list['categoryId'] ?? '';
				$totals[$key] = ($totals[$key] ?? 0.0) + $total;
				continue;
			}

			foreach ($weights as $categoryKey => $weight) {
				$totals[$categoryKey] = ($totals[$categoryKey] ?? 0.0) + $total * ($weight / $weightSum);
			}
		}

		return $totals;
	}
}
