<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\BackgroundJob;

use OCA\ByeByeMoneyList\Service\ToBuy\ToBuyAutoMatchService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

/**
 * Runs the best-effort To Buy auto-check outside the purchase request.
 *
 * @psalm-suppress UnusedClass
 */
class ToBuyAutoMatchJob extends QueuedJob {
	private ToBuyAutoMatchService $toBuyAutoMatch;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(ITimeFactory $time, ToBuyAutoMatchService $toBuyAutoMatch) {
		parent::__construct($time);
		$this->toBuyAutoMatch = $toBuyAutoMatch;
	}

	/**
	 * @param mixed $argument
	 */
	protected function run($argument): void {
		if (!is_array($argument)) {
			return;
		}

		$userId = $argument['userId'] ?? null;
		if (!is_string($userId) || $userId === '') {
			return;
		}

		if (isset($argument['listId']) && is_string($argument['listId']) && $argument['listId'] !== '') {
			$this->toBuyAutoMatch->matchAfterPurchaseList($userId, $argument['listId']);
			return;
		}

		$rawNames = $argument['names'] ?? [];
		if (!is_array($rawNames)) {
			return;
		}

		$names = [];
		/** @psalm-suppress MixedAssignment */
		foreach ($rawNames as $name) {
			if (is_string($name)) {
				$names[] = $name;
			}
		}
		if ($names === []) {
			return;
		}

		$this->toBuyAutoMatch->matchAfterPurchase($userId, $names);
	}
}
