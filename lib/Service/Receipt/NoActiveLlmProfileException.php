<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service\Receipt;

/**
 * Thrown when the current user has no active LLM profile to scan with.
 *
 * @psalm-suppress UnusedClass
 */
class NoActiveLlmProfileException extends \RuntimeException {
}
