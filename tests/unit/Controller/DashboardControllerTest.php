<?php

declare(strict_types=1);

namespace Controller;

use DateTimeInterface;
use OCA\ByeByeMoneyList\Controller\DashboardController;
use OCA\ByeByeMoneyList\Db\DashboardMapper;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class DashboardControllerTest extends TestCase {
	private DashboardController $controller;
	private DashboardMapper $mapper;
	private IUserSession $userSession;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->mapper = $this->createMock(DashboardMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);

		$this->controller = new DashboardController($request, $this->mapper, $this->userSession);
	}

	private function mockUser(string $uid): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
	}

	public function testSpendingReturnsTotals(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('sumFinishedByRange')
			->with('alice', $this->isInstanceOf(DateTimeInterface::class), $this->isInstanceOf(DateTimeInterface::class), null)
			->willReturn([
				'total' => 42.5,
				'byCategory' => [
					['categoryId' => 'cat-1', 'total' => 30.0],
					['categoryId' => null, 'total' => 12.5],
				],
			]);

		$response = $this->controller->spending('2026-09-01T00:00:00+00:00', '2026-10-01T00:00:00+00:00');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertSame(42.5, $data['total']);
		$this->assertSame([
			['categoryId' => 'cat-1', 'total' => 30.0],
			['categoryId' => null, 'total' => 12.5],
		], $data['byCategory']);
	}

	public function testSpendingNormalizesOffsetDatesToUtc(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('sumFinishedByRange')
			->with(
				'alice',
				$this->callback(fn (DateTimeInterface $date): bool => $date->format(DateTimeInterface::ATOM) === '2026-09-01T09:00:00+00:00'),
				$this->callback(fn (DateTimeInterface $date): bool => $date->format(DateTimeInterface::ATOM) === '2026-10-01T09:00:00+00:00'),
				null,
			)
			->willReturn(['total' => 0.0, 'byCategory' => []]);

		$response = $this->controller->spending('2026-09-01T12:00:00+03:00', '2026-10-01T12:00:00+03:00');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testSpendingTrimsCategoryFilterAndTreatsBlankAsNull(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->exactly(2))
			->method('sumFinishedByRange')
			->with(
				'alice',
				$this->isInstanceOf(DateTimeInterface::class),
				$this->isInstanceOf(DateTimeInterface::class),
				$this->logicalOr($this->identicalTo('cat-1'), $this->isNull()),
			)
			->willReturn(['total' => 0.0, 'byCategory' => []]);

		$this->controller->spending('2026-09-01T00:00:00+00:00', '2026-10-01T00:00:00+00:00', ' cat-1 ');
		$this->controller->spending('2026-09-01T00:00:00+00:00', '2026-10-01T00:00:00+00:00', '  ');
	}

	public function testSpendingRejectsInvalidDate(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->never())->method('sumFinishedByRange');

		$response = $this->controller->spending('not-a-date', '2026-10-01T00:00:00+00:00');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testSpendingRejectsInvertedRange(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->never())->method('sumFinishedByRange');

		$response = $this->controller->spending('2026-10-01T00:00:00+00:00', '2026-09-01T00:00:00+00:00');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testSpendingReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$this->mapper->expects($this->never())->method('sumFinishedByRange');

		$response = $this->controller->spending('2026-09-01T00:00:00+00:00', '2026-10-01T00:00:00+00:00');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}
}
