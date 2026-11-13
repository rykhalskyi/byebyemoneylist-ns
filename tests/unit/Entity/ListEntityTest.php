<?php

declare(strict_types=1);

namespace Entity;

use OCA\ByeByeMoneyList\Entity\ListEntity;
use PHPUnit\Framework\TestCase;

final class ListEntityTest extends TestCase {
	public function testKindAndActiveDefaultToUnsetAndInactive(): void {
		$list = new ListEntity();

		self::assertNull($list->getKind());
		self::assertFalse($list->getIsActive());
	}

	public function testExplicitKindWinsOverLegacyFlags(): void {
		$list = new ListEntity();
		$list->setKind(ListEntity::KIND_NEED_TO_BUY);
		$list->setIsIncome(true);
		$list->setIsFinished(true);

		self::assertSame(ListEntity::KIND_NEED_TO_BUY, $list->resolveKind());
	}

	public function testResolveKindDerivesIncome(): void {
		$list = new ListEntity();
		$list->setIsIncome(true);

		self::assertSame(ListEntity::KIND_INCOME, $list->resolveKind());
	}

	public function testResolveKindDerivesSubscription(): void {
		$list = new ListEntity();
		$list->setIsSubscription(true);

		self::assertSame(ListEntity::KIND_SUBSCRIPTION, $list->resolveKind());
	}

	public function testResolveKindDerivesPurchase(): void {
		$list = new ListEntity();
		$list->setIsFinished(true);

		self::assertSame(ListEntity::KIND_PURCHASE, $list->resolveKind());
	}

	public function testResolveKindFallsBackToNeedToBuy(): void {
		$list = new ListEntity();

		self::assertSame(ListEntity::KIND_NEED_TO_BUY, $list->resolveKind());
	}

	public function testActiveFlagRoundTrips(): void {
		$list = new ListEntity();
		$list->setIsActive(true);

		self::assertTrue($list->getIsActive());
	}
}
