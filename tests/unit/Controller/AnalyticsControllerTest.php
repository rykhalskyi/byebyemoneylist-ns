<?php

declare(strict_types=1);

namespace Controller;

use DateTimeInterface;
use OCA\ByeByeMoneyList\Controller\AnalyticsController;
use OCA\ByeByeMoneyList\Db\AnalyticsMapper;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class AnalyticsControllerTest extends TestCase {
	private AnalyticsController $controller;
	private AnalyticsMapper $mapper;
	private IUserSession $userSession;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$this->mapper = $this->createMock(AnalyticsMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);

		$this->controller = new AnalyticsController($request, $this->mapper, $this->userSession);
	}

	private function mockUser(string $uid): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
	}

	public function testOverviewReturnsBreakdowns(): void {
		$this->mockUser('alice');

		$overview = [
			'totalSpent' => 42.5,
			'totalIncome' => 100.0,
			'byCategory' => [['categoryId' => 'cat-1', 'total' => 42.5]],
			'byStore' => [['storeId' => null, 'total' => 42.5]],
			'byList' => [['listId' => 'list-1', 'name' => 'Groceries', 'total' => 42.5]],
		];
		$this->mapper->expects($this->once())
			->method('overview')
			->with('alice', $this->isInstanceOf(DateTimeInterface::class), $this->isInstanceOf(DateTimeInterface::class))
			->willReturn($overview);

		$response = $this->controller->overview('2026-09-01T00:00:00+00:00', '2026-10-01T00:00:00+00:00');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($overview, $response->getData());
	}

	public function testOverviewNormalizesOffsetDatesToUtc(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->once())
			->method('overview')
			->with(
				'alice',
				$this->callback(fn (DateTimeInterface $date): bool => $date->format(DateTimeInterface::ATOM) === '2026-09-01T09:00:00+00:00'),
				$this->callback(fn (DateTimeInterface $date): bool => $date->format(DateTimeInterface::ATOM) === '2026-10-01T09:00:00+00:00'),
			)
			->willReturn([
				'totalSpent' => 0.0,
				'totalIncome' => 0.0,
				'byCategory' => [],
				'byStore' => [],
				'byList' => [],
			]);

		$response = $this->controller->overview('2026-09-01T12:00:00+03:00', '2026-10-01T12:00:00+03:00');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testOverviewRejectsInvalidDate(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->never())->method('overview');

		$response = $this->controller->overview('not-a-date', '2026-10-01T00:00:00+00:00');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testOverviewRejectsInvertedRange(): void {
		$this->mockUser('alice');

		$this->mapper->expects($this->never())->method('overview');

		$response = $this->controller->overview('2026-10-01T00:00:00+00:00', '2026-09-01T00:00:00+00:00');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testOverviewReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$this->mapper->expects($this->never())->method('overview');

		$response = $this->controller->overview('2026-09-01T00:00:00+00:00', '2026-10-01T00:00:00+00:00');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}
}
