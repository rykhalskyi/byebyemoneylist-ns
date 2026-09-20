<?php

declare(strict_types=1);

namespace Config;

use OCA\ByeByeMoneyList\Config\EnvLoader;
use PHPUnit\Framework\TestCase;

final class EnvLoaderTest extends TestCase {
	private string $path;

	protected function setUp(): void {
		$this->path = tempnam(sys_get_temp_dir(), 'bbml-env-') ?: '';
	}

	protected function tearDown(): void {
		if ($this->path !== '' && is_file($this->path)) {
			unlink($this->path);
		}
	}

	private function write(string $contents): EnvLoader {
		file_put_contents($this->path, $contents);
		return new EnvLoader($this->path);
	}

	public function testReadsPlainAndQuotedValuesSkippingComments(): void {
		$env = $this->write(implode("\n", [
			'# a comment',
			'SILICONFLOW_API_KEY=secret-token',
			'QUOTED="quoted value"',
			"SINGLE='single value'",
			'export EXPORTED=exported-value',
			'',
		]));

		$this->assertSame('secret-token', $env->get('SILICONFLOW_API_KEY'));
		$this->assertSame('quoted value', $env->get('QUOTED'));
		$this->assertSame('single value', $env->get('SINGLE'));
		$this->assertSame('exported-value', $env->get('EXPORTED'));
	}

	public function testReturnsDefaultForMissingKey(): void {
		$env = $this->write('KNOWN=1');

		$this->assertNull($env->get('UNKNOWN'));
		$this->assertSame('fallback', $env->get('UNKNOWN', 'fallback'));
	}

	public function testMissingFileYieldsDefault(): void {
		$env = new EnvLoader($this->path . '.missing');

		$this->assertNull($env->get('SILICONFLOW_API_KEY'));
	}
}
