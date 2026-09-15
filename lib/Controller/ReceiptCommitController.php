<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Service\Receipt\ReceiptCommitService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\Files\IMimeTypeDetector;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Commits a reviewed receipt scan: creates the finished list with its items and
 * optionally stores the receipt image.
 *
 * @psalm-suppress UnusedClass
 */
class ReceiptCommitController extends OCSController {
	private const MAX_BYTES = 4 * 1024 * 1024;

	/** @var array<string, string> allowed MIME type => file extension */
	private const ALLOWED_MIME = [
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/webp' => 'webp',
		'image/gif' => 'gif',
	];

	private ReceiptCommitService $commitService;
	private ListMapper $listMapper;
	private IMimeTypeDetector $mimeTypeDetector;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		ReceiptCommitService $commitService,
		ListMapper $listMapper,
		IMimeTypeDetector $mimeTypeDetector,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->commitService = $commitService;
		$this->listMapper = $listMapper;
		$this->mimeTypeDetector = $mimeTypeDetector;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Save a reviewed receipt as a finished list
	 *
	 * @param string $payload JSON payload with the reviewed receipt (name, store, items, total)
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement, RedundantConditionGivenDocblockType, DocblockTypeContradiction, MixedAssignment, MixedArrayAccess
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{list: array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool, hasReceipt: bool}}|array{message: string}, array{}>
	 *
	 * 201: List saved
	 * 401: Current user is not logged in
	 * 422: Missing name, invalid total, no items, or invalid image
	 * 500: Failed to save
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/receipts/commit')]
	public function commit(?string $payload = null): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		if ($payload === null || $payload === '') {
			return new DataResponse(['message' => 'payload is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$decoded = json_decode($payload, true);
		if (!is_array($decoded)) {
			return new DataResponse(['message' => 'payload must be valid JSON'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$name = trim((string)($decoded['name'] ?? ''));
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$finalTotal = null;
		if (isset($decoded['finalTotal']) && $decoded['finalTotal'] !== null) {
			if (!is_numeric($decoded['finalTotal'])) {
				return new DataResponse(['message' => 'finalTotal must be a number'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			$finalTotal = round((float)$decoded['finalTotal'], 2);
		}

		$items = [];
		if (isset($decoded['items']) && is_array($decoded['items'])) {
			foreach ($decoded['items'] as $item) {
				if (!is_array($item) || trim((string)($item['name'] ?? '')) === '') {
					continue;
				}
				$items[] = [
					'productId' => isset($item['productId']) && is_string($item['productId']) ? $item['productId'] : null,
					'name' => trim((string)$item['name']),
					'quantity' => (float)($item['quantity'] ?? 1),
					'price' => (float)($item['price'] ?? 0),
					'discount' => isset($item['discount']) && $item['discount'] !== null ? (float)$item['discount'] : null,
					'isCoupon' => (bool)($item['isCoupon'] ?? false),
					'categoryId' => isset($item['categoryId']) && is_string($item['categoryId']) ? $item['categoryId'] : null,
					'categoryName' => isset($item['categoryName']) && is_string($item['categoryName']) ? $item['categoryName'] : null,
				];
			}
		}

		$purchaseDate = new DateTime('now', new DateTimeZone('UTC'));
		if (isset($decoded['purchaseDate']) && is_string($decoded['purchaseDate']) && $decoded['purchaseDate'] !== '') {
			try {
				$purchaseDate = new DateTime($decoded['purchaseDate'], new DateTimeZone('UTC'));
			} catch (\Exception) {
				// fall back to now on an unparseable date
			}
		}

		$saveReceipt = (bool)($decoded['saveReceipt'] ?? false);
		$receiptTmpPath = null;
		$receiptExtension = null;
		if ($saveReceipt) {
			$uploaded = $this->request->getUploadedFile('receipt');
			if (!is_array($uploaded) || ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
				return new DataResponse(['message' => 'Receipt file is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			$receiptTmpPath = isset($uploaded['tmp_name']) && is_string($uploaded['tmp_name']) ? $uploaded['tmp_name'] : '';
			if ($receiptTmpPath === '' || !is_file($receiptTmpPath)) {
				return new DataResponse(['message' => 'Receipt file is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			$size = filesize($receiptTmpPath);
			if ($size === false || $size > self::MAX_BYTES) {
				return new DataResponse(['message' => 'The receipt exceeds the 4 MB limit'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			$mime = $this->mimeTypeDetector->detectContent($receiptTmpPath);
			if (!isset(self::ALLOWED_MIME[$mime])) {
				return new DataResponse(['message' => 'Unsupported image type'], Http::STATUS_UNPROCESSABLE_ENTITY);
			}
			$receiptExtension = self::ALLOWED_MIME[$mime];
		}

		try {
			$list = $this->commitService->commit(
				$userId,
				$name,
				isset($decoded['storeName']) && is_string($decoded['storeName']) ? $decoded['storeName'] : null,
				isset($decoded['storeAddress']) && is_string($decoded['storeAddress']) ? $decoded['storeAddress'] : null,
				$finalTotal,
				$purchaseDate,
				$items,
				$saveReceipt,
				$receiptTmpPath,
				$receiptExtension,
			);
		} catch (\Exception $e) {
			$this->logger->error('Failed to save scanned receipt', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to save the list'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$categoryIds = $this->listMapper->findCategoryIdsByListIds([$list->getId()])[$list->getId()] ?? [];

		return new DataResponse(['list' => $this->serializeList($list, $categoryIds)], Http::STATUS_CREATED);
	}

	/**
	 * @param list<string> $categoryIds
	 *
	 * @return array{id: string, name: string, storeId: ?string, categoryId: ?string, categoryIds: list<string>, status: string, finalTotal: ?float, totalPrice: ?float, createdAt: ?string, createDate: ?string, updatedAt: ?string, purchaseDate: ?string, position: int, isFinished: bool, isSubscription: bool, isIncome: bool, isRecurring: bool, recurringPeriod: string, isForwardEmpty: bool, hasReceipt: bool}
	 */
	private function serializeList(ListEntity $list, array $categoryIds): array {
		$createdAt = $list->getCreatedAt();
		$updatedAt = $list->getUpdatedAt();
		$purchaseDate = $list->getPurchaseDate();
		return [
			'id' => $list->getId(),
			'name' => $list->getName() ?? '',
			'storeId' => $list->getStoreId(),
			'categoryId' => $categoryIds[0] ?? $list->getCategoryId(),
			'categoryIds' => $categoryIds,
			'status' => $list->getStatus() ?? 'new',
			'finalTotal' => $list->getFinalTotal(),
			'totalPrice' => null,
			'createdAt' => $createdAt?->format(DateTimeInterface::ATOM),
			'createDate' => $createdAt?->format(DateTimeInterface::ATOM),
			'updatedAt' => $updatedAt?->format(DateTimeInterface::ATOM),
			'purchaseDate' => $purchaseDate?->format(DateTimeInterface::ATOM),
			'position' => $list->getPosition() ?? 0,
			'isFinished' => (bool)$list->getIsFinished(),
			'isSubscription' => (bool)$list->getIsSubscription(),
			'isIncome' => (bool)$list->getIsIncome(),
			'isRecurring' => (bool)$list->getIsRecurring(),
			'recurringPeriod' => $list->getRecurringPeriod() ?? 'MONTH',
			'isForwardEmpty' => (bool)$list->getIsForwardEmpty(),
			'hasReceipt' => $list->getReceiptPath() !== null,
		];
	}
}
