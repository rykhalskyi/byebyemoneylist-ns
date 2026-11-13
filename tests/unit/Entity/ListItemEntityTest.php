<?php

declare(strict_types=1);

namespace Entity;

use OCA\ByeByeMoneyList\Entity\ListItemEntity;
use PHPUnit\Framework\TestCase;

final class ListItemEntityTest extends TestCase {
	public function testProductIdIsNullableForPlainTextItems(): void {
		$item = new ListItemEntity();
		$item->setProductId(null);

		self::assertNull($item->getProductId());
	}

	public function testProductIdRoundTrips(): void {
		$item = new ListItemEntity();
		$item->setProductId('product-1');

		self::assertSame('product-1', $item->getProductId());
	}
}
