<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use DateTime;
use DateTimeZone;
use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\AnalyticsMapper;
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
class AnalyticsController extends OCSController {
	private AnalyticsMapper $mapper;
	private IUserSession $userSession;

	public function __construct(
		IRequest $request,
		AnalyticsMapper $mapper,
		IUserSession $userSession,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
		$this->userSession = $userSession;
	}

	/**
	 * Monthly analytics overview for the current user
	 *
	 * Returns expense/income totals plus category, store and list breakdowns for
	 * finished, non-subscription lists created in [from, to). The client computes
	 * the month boundaries in its local timezone and sends ISO-8601 instants.
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $from Range start (ISO-8601, inclusive)
	 * @param string $to Range end (ISO-8601, exclusive)
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY, array{totalSpent: float, totalIncome: float, byCategory: list<array{categoryId: ?string, total: float}>, byStore: list<array{storeId: ?string, total: float}>, byList: list<array{listId: string, name: string, total: float}>}|array{message: string}, array{}>
	 *
	 * 200: Analytics overview returned
	 * 401: Current user is not logged in
	 * 422: from/to are missing, invalid or not a valid range
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/analytics/overview')]
	public function overview(string $from, string $to): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$parsedFrom = $this->parseDate($from);
		$parsedTo = $this->parseDate($to);
		if ($parsedFrom === null || $parsedTo === null || $parsedFrom >= $parsedTo) {
			return new DataResponse(['message' => 'A valid from/to range is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		return new DataResponse($this->mapper->overview($userId, $parsedFrom, $parsedTo), Http::STATUS_OK);
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
