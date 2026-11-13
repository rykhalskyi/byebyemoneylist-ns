<?php

declare(strict_types=1);

namespace BackgroundJob;

use OCA\ByeByeMoneyList\BackgroundJob\ToBuyAutoMatchJob;
use OCA\ByeByeMoneyList\Service\ToBuy\ToBuyAutoMatchService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

final class ToBuyAutoMatchJobTest extends TestCase {
	private ToBuyAutoMatchJob $job;
	private ToBuyAutoMatchService $service;

	protected function setUp(): void {
		$this->service = $this->createMock(ToBuyAutoMatchService::class);
		$this->job = new ToBuyAutoMatchJob(
			$this->createMock(ITimeFactory::class),
			$this->service,
		);
	}

	/**
	 * @param array<string, mixed> $argument
	 */
	private function run(array $argument): void {
		$method = new \ReflectionMethod(ToBuyAutoMatchJob::class, 'run');
		$method->setAccessible(true);
		$method->invoke($this->job, $argument);
	}

	public function testRunsForPurchasedNames(): void {
		$this->service->expects($this->once())
			->method('matchAfterPurchase')
			->with('alice', ['Milk', 'Bread']);

		$this->run(['userId' => 'alice', 'names' => ['Milk', 7, 'Bread']]);
	}

	public function testRunsForFinishedList(): void {
		$this->service->expects($this->once())
			->method('matchAfterPurchaseList')
			->with('alice', 'list-1');

		$this->run(['userId' => 'alice', 'listId' => 'list-1']);
	}

	public function testIgnoresInvalidArguments(): void {
		$this->service->expects($this->never())->method('matchAfterPurchase');
		$this->service->expects($this->never())->method('matchAfterPurchaseList');

		$this->run(['names' => ['Milk']]);
		$this->run(['userId' => 'alice', 'names' => []]);
		$this->run(['userId' => 'alice', 'names' => 'Milk']);
	}
}
