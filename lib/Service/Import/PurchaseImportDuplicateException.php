<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service\Import;

/**
 * Thrown when a purchase import was already approved or its client list id is
 * already used by an existing list.
 *
 * @psalm-suppress UnusedClass
 */
class PurchaseImportDuplicateException extends \RuntimeException {
}
