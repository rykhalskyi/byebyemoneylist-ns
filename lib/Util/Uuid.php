<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Util;

/**
 * Dependency-free UUID generator.
 */
final class Uuid {
	/**
	 * Namespace UUID used to derive stable names (RFC 4122 v5).
	 */
	public const NAMESPACE_DEFAULT = '4f6f7a3e-9c2b-4d8a-8b1e-0f5c6d7e8a9b';

	/**
	 * Generate a random UUID v4.
	 */
	public static function v4(): string {
		$bytes = random_bytes(16);
		$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
		$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
		return self::format($bytes);
	}

	/**
	 * Generate a deterministic, name-based UUID v5 (SHA-1).
	 *
	 * The same namespace and name always produce the same UUID, which makes it
	 * suitable for idempotent per-user identifiers (e.g. default categories).
	 */
	public static function v5(string $name, string $namespace = self::NAMESPACE_DEFAULT): string {
		$namespaceBytes = hex2bin(str_replace('-', '', $namespace));
		if ($namespaceBytes === false || strlen($namespaceBytes) !== 16) {
			throw new \InvalidArgumentException('Namespace must be a valid UUID');
		}

		$hash = sha1($namespaceBytes . $name);
		$bytes = '';
		for ($i = 0; $i < 16; $i++) {
			$bytes .= chr((int)hexdec(substr($hash, $i * 2, 2)));
		}
		$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x50);
		$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

		return self::format($bytes);
	}

	private static function format(string $bytes): string {
		$hex = bin2hex($bytes);
		return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
	}
}
