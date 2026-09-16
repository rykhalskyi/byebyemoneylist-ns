<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service\Receipt;

use OCA\ByeByeMoneyList\Entity\ProductEntity;

/**
 * Port of the Android `ProductMatcher`: exact (case-insensitive) name/alias match
 * first, then a fuzzy match over normalized product names using Levenshtein
 * distance and token Jaccard similarity.
 *
 * @psalm-suppress UnusedClass
 */
class ReceiptProductMatcher {
	/** @var list<string> */
	private const UNIT_TOKENS = [
		'kg', 'g', 'l', 'ml', 'cl', 'liter', 'stuck', 'stk', 'pack', 'packung',
		'beutel', 'tute', 'flasche', 'dose', 'bund',
	];

	private const CHAR_RATIO_STRONG = 0.9;
	private const CHAR_RATIO_MIN = 0.75;
	private const JACCARD_MIN = 0.6;
	private const MIN_SIGNIFICANT_TOKEN_LENGTH = 2;

	/**
	 * @param list<ProductEntity> $products
	 * @param array<string, ProductEntity> $byExactAlias lowercased alias name => product
	 */
	public function match(string $name, array $products, array $byExactAlias = []): ?ProductEntity {
		$trimmed = trim($name);
		if ($trimmed === '' || $products === []) {
			return null;
		}

		$lower = mb_strtolower($trimmed);
		foreach ($products as $product) {
			if (mb_strtolower((string)$product->getName()) === $lower) {
				return $product;
			}
		}
		if (isset($byExactAlias[$lower])) {
			return $byExactAlias[$lower];
		}

		$targetNorm = $this->normalize($trimmed);
		if ($targetNorm === '') {
			return null;
		}
		$targetTokens = $this->significantTokens($targetNorm);
		$targetCompact = str_replace(' ', '', $targetNorm);

		$best = null;
		$bestCharRatio = 0.0;
		$bestJaccard = 0.0;
		foreach ($products as $product) {
			$candidateNorm = $this->normalize((string)$product->getName());
			if ($candidateNorm === '') {
				continue;
			}
			$candidateCompact = str_replace(' ', '', $candidateNorm);

			if ($targetCompact !== '' && $candidateCompact !== '' && $targetCompact === $candidateCompact) {
				return $product;
			}
			if (!$this->passesPreFilter($targetNorm, $targetTokens, $targetCompact, $candidateNorm, $candidateCompact)) {
				continue;
			}

			$candidateTokens = $this->significantTokens($candidateNorm);
			$charRatio = $this->charRatio($targetNorm, $candidateNorm);
			$jaccard = $this->tokenJaccard($targetTokens, $candidateTokens);
			if (!$this->isMatch($charRatio, $jaccard)) {
				continue;
			}

			if ($charRatio > $bestCharRatio || ($charRatio === $bestCharRatio && $jaccard > $bestJaccard)) {
				$bestCharRatio = $charRatio;
				$bestJaccard = $jaccard;
				$best = $product;
			}
		}

		return $best;
	}

	public function normalize(string $raw): string {
		$lower = mb_strtolower($raw);
		$ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
		if ($ascii === false) {
			$ascii = $lower;
		}
		$ascii = (string)preg_replace('/[^a-z0-9]/', ' ', $ascii);
		$tokens = preg_split('/\s+/', trim($ascii)) ?: [];
		$tokens = array_values(array_filter($tokens, static fn (string $t): bool => $t !== ''));

		while ($tokens !== []) {
			$lastIndex = count($tokens) - 1;
			$stripped = $this->stripTrailingUnit($tokens[$lastIndex]);
			if ($stripped === $tokens[$lastIndex]) {
				break;
			}
			if ($stripped === '') {
				array_pop($tokens);
			} else {
				$tokens[$lastIndex] = $stripped;
				break;
			}
		}

		return implode(' ', $tokens);
	}

	private function stripTrailingUnit(string $token): string {
		if (in_array($token, self::UNIT_TOKENS, true)) {
			return '';
		}
		if ($token !== '' && preg_match('/^\d+$/', $token) === 1) {
			return '';
		}
		if (preg_match('/^(.*?)(\d+)([a-z]+)$/', $token, $m) === 1) {
			if (in_array($m[3], self::UNIT_TOKENS, true)) {
				return $m[1];
			}
		}
		return $token;
	}

	/**
	 * @return list<string>
	 */
	private function significantTokens(string $normalized): array {
		$tokens = explode(' ', $normalized);
		return array_values(array_filter($tokens, static fn (string $t): bool => mb_strlen($t) >= self::MIN_SIGNIFICANT_TOKEN_LENGTH));
	}

	/**
	 * @param list<string> $targetTokens
	 */
	private function passesPreFilter(string $targetNorm, array $targetTokens, string $targetCompact, string $candidateNorm, string $candidateCompact): bool {
		if (str_contains($candidateNorm, $targetNorm) || str_contains($targetNorm, $candidateNorm)) {
			return true;
		}
		if (strlen($targetCompact) >= 4 && str_contains($candidateCompact, $targetCompact)) {
			return true;
		}
		if (strlen($candidateCompact) >= 4 && str_contains($targetCompact, $candidateCompact)) {
			return true;
		}
		$candidateTokens = explode(' ', $candidateNorm);
		foreach ($targetTokens as $token) {
			if (in_array($token, $candidateTokens, true)) {
				return true;
			}
		}
		return false;
	}

	private function isMatch(float $charRatio, float $jaccard): bool {
		return $charRatio >= self::CHAR_RATIO_STRONG
			|| ($jaccard >= self::JACCARD_MIN && $charRatio >= self::CHAR_RATIO_MIN);
	}

	private function charRatio(string $a, string $b): float {
		if ($a === '' || $b === '') {
			return 0.0;
		}
		if ($a === $b) {
			return 1.0;
		}
		$maxLen = max(strlen($a), strlen($b));
		$distance = levenshtein($a, $b);
		if ($distance < 0) {
			$distance = $maxLen;
		}
		return 1.0 - $distance / $maxLen;
	}

	/**
	 * @param list<string> $a
	 * @param list<string> $b
	 */
	private function tokenJaccard(array $a, array $b): float {
		$setA = array_values(array_unique($a));
		$setB = array_values(array_unique($b));
		if ($setA === [] && $setB === []) {
			return 0.0;
		}
		$intersection = count(array_intersect($setA, $setB));
		$union = count(array_unique(array_merge($setA, $setB)));
		return $union === 0 ? 0.0 : $intersection / $union;
	}
}
