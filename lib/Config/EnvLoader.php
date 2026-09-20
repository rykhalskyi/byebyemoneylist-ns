<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Config;

/**
 * Minimal reader for the app-local, git-ignored ".env" file.
 *
 * @psalm-suppress UnusedClass
 */
class EnvLoader {
	private string $envPath;
	/** @var array<string, string>|null */
	private ?array $values = null;

	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	public function __construct(?string $envPath = null) {
		$this->envPath = $envPath ?? dirname(__DIR__, 2) . '/.env';
	}

	public function get(string $key, ?string $default = null): ?string {
		$values = $this->load();
		if (!array_key_exists($key, $values)) {
			return $default;
		}

		return $values[$key];
	}

	/**
	 * @return array<string, string>
	 */
	private function load(): array {
		if ($this->values !== null) {
			return $this->values;
		}

		$this->values = [];
		if (!is_file($this->envPath)) {
			return $this->values;
		}

		$lines = file($this->envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($lines === false) {
			return $this->values;
		}

		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '' || str_starts_with($line, '#')) {
				continue;
			}
			if (str_starts_with($line, 'export ')) {
				$line = trim(substr($line, strlen('export ')));
			}

			$separator = strpos($line, '=');
			if ($separator === false) {
				continue;
			}

			$name = trim(substr($line, 0, $separator));
			if ($name === '') {
				continue;
			}

			$this->values[$name] = $this->stripQuotes(trim(substr($line, $separator + 1)));
		}

		return $this->values;
	}

	private function stripQuotes(string $value): string {
		$length = strlen($value);
		if ($length < 2) {
			return $value;
		}

		$first = $value[0];
		$last = $value[$length - 1];
		if (($first === '"' && $last === '"') || ($first === '\'' && $last === '\'')) {
			return substr($value, 1, -1);
		}

		return $value;
	}
}
