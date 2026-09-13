<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use DateTime;
use DateTimeZone;
use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\DashboardMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * @psalm-suppress UnusedClass
 */
class DashboardController extends OCSController {
	private DashboardMapper $mapper;
	private IUserSession $userSession;

	public function __construct(
		IRequest $request,
		DashboardMapper $mapper,
		IUserSession $userSession,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
		$this->userSession = $userSession;
	}

	/**
	 * Spending totals for the current user within an explicit time range
	 *
	 * Sums finished expense lists (income and subscriptions excluded) created in
	 * [from, to) and returns the total plus a per-category breakdown. The client
	 * computes the range in its local timezone and sends ISO-8601 instants.
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $from Range start (ISO-8601, inclusive)
	 * @param string $to Range end (ISO-8601, exclusive)
	 * @param ?string $categoryId Optional category filter
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY, array{total: float, byCategory: list<array{categoryId: ?string, total: float}>}|array{message: string}, array{}>
	 *
	 * 200: Spending totals returned
	 * 401: Current user is not logged in
	 * 422: from/to are missing, invalid or not a valid range
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/dashboard/spending')]
	public function spending(string $from, string $to, ?string $categoryId = null): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$parsedFrom = $this->parseDate($from);
		$parsedTo = $this->parseDate($to);
		if ($parsedFrom === null || $parsedTo === null || $parsedFrom >= $parsedTo) {
			return new DataResponse(['message' => 'A valid from/to range is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$filter = $categoryId !== null && trim($categoryId) !== '' ? trim($categoryId) : null;
		$result = $this->mapper->sumFinishedByRange($userId, $parsedFrom, $parsedTo, $filter);

		return new DataResponse($result, Http::STATUS_OK);
	}

	private function getCurrentUserId(): ?string {
		return $this->userSession->getUser()?->getUID();
	}

	/**
	 * Parse an ISO-8601 instant (with a 'Z' or ±hh:mm offset) to UTC. Returns null
	 * for blank or invalid values.
	 */
	private function parseDate(string $value): ?DateTime {
		$value = trim($value);
		if ($value === '') {
			return null;
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})$/', $value)) {
			return null;
		}
		try {
			$date = new DateTime($value);
		} catch (\Exception) {
			return null;
		}
		return $date->setTimezone(new DateTimeZone('UTC'));
	}
}
