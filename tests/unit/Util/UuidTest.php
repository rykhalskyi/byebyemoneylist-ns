<?php

declare(strict_types=1);

namespace Util;

use OCA\ByeByeMoneyList\Util\Uuid;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase {
	public function testV4GeneratesVersion4VariantUuid(): void {
		$uuid = Uuid::v4();

		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			$uuid,
		);
	}

	public function testV5IsDeterministicAndVersion5(): void {
		$a = Uuid::v5('alice:supermarket');
		$b = Uuid::v5('alice:supermarket');

		$this->assertSame($a, $b);
		$this->assertMatchesRegularExpression(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			$a,
		);
	}

	public function testV5IsScopedByUserAndName(): void {
		$this->assertNotSame(Uuid::v5('alice:supermarket'), Uuid::v5('bob:supermarket'));
		$this->assertNotSame(Uuid::v5('alice:supermarket'), Uuid::v5('alice:bakery'));
	}

	public function testV5MatchesKnownRfc4122Vector(): void {
		// uuid5(NAMESPACE_DNS, 'python.org') per RFC 4122 (SHA-1, name-based).
		$this->assertSame(
			'886313e1-3b8a-5372-9b90-0c9aee199e5d',
			Uuid::v5('python.org', '6ba7b810-9dad-11d1-80b4-00c04fd430c8'),
		);
	}
}
