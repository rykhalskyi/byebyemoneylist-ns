<?php

declare(strict_types=1);

namespace Util;

use OCA\ByeByeMoneyList\Util\CategorySpendingSplitter;
use PHPUnit\Framework\TestCase;

final class CategorySpendingSplitterTest extends TestCase {
	public function testSplitsListTotalAcrossItemCategoriesProportionally(): void {
		$result = CategorySpendingSplitter::split([
			[
				'total' => 30.0,
				'categoryId' => 'supermarket',
				'weights' => [
					'fruit' => 10.0,
					'dairy' => 20.0,
				],
			],
		]);

		$this->assertEqualsWithDelta(['fruit' => 10.0, 'dairy' => 20.0], $result, 0.0001);
	}

	public function testFallsBackToTheListCategoryWhenThereAreNoWeights(): void {
		$result = CategorySpendingSplitter::split([
			[
				'total' => 25.62,
				'categoryId' => 'supermarket',
				'weights' => [],
			],
		]);

		$this->assertEqualsWithDelta(['supermarket' => 25.62], $result, 0.0001);
	}

	public function testFallsBackToTheListCategoryWhenAllWeightsAreZero(): void {
		$result = CategorySpendingSplitter::split([
			[
				'total' => 5.0,
				'categoryId' => 'supermarket',
				'weights' => ['fruit' => 0.0, 'dairy' => -1.0],
			],
		]);

		$this->assertEqualsWithDelta(['supermarket' => 5.0], $result, 0.0001);
	}

	public function testUncategorizedItemWeightUsesTheEmptyKey(): void {
		$result = CategorySpendingSplitter::split([
			[
				'total' => 10.0,
				'categoryId' => 'supermarket',
				'weights' => ['fruit' => 5.0, '' => 5.0],
			],
		]);

		$this->assertEqualsWithDelta(['fruit' => 5.0, '' => 5.0], $result, 0.0001);
	}

	public function testAggregatesMultipleListsPerCategory(): void {
		$result = CategorySpendingSplitter::split([
			[
				'total' => 10.0,
				'categoryId' => null,
				'weights' => ['fruit' => 10.0],
			],
			[
				'total' => 30.0,
				'categoryId' => 'supermarket',
				'weights' => ['fruit' => 15.0, 'dairy' => 15.0],
			],
		]);

		$this->assertEqualsWithDelta(['fruit' => 25.0, 'dairy' => 15.0], $result, 0.0001);
	}

	public function testIgnoresNonPositiveListTotals(): void {
		$result = CategorySpendingSplitter::split([
			[
				'total' => 0.0,
				'categoryId' => 'supermarket',
				'weights' => ['fruit' => 10.0],
			],
		]);

		$this->assertSame([], $result);
	}
}
