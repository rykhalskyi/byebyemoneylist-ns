<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Service\Receipt\NoActiveLlmProfileException;
use OCA\ByeByeMoneyList\Service\Receipt\ReceiptScanService;
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
 * Receipt scanning: upload a receipt photo and get back the parsed, product- and
 * category-matched result from the active LLM profile.
 *
 * @psalm-suppress UnusedClass
 */
class ReceiptScanController extends OCSController {
	private const MAX_BYTES = 4 * 1024 * 1024;

	/** @var array<string, string> allowed MIME type => file extension */
	private const ALLOWED_MIME = [
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/webp' => 'webp',
		'image/gif' => 'gif',
	];

	private ReceiptScanService $scanService;
	private IMimeTypeDetector $mimeTypeDetector;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		ReceiptScanService $scanService,
		IMimeTypeDetector $mimeTypeDetector,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->scanService = $scanService;
		$this->mimeTypeDetector = $mimeTypeDetector;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Scan an uploaded receipt image with the active LLM profile
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement, RedundantConditionGivenDocblockType, DocblockTypeContradiction
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_CONFLICT|Http::STATUS_BAD_GATEWAY, array{scan: array{storeName: ?string, storeAddress: ?string, storeId: ?string, totalSum: ?float, items: list<array{name: string, quantity: float, price: float, discount: ?float, isCoupon: bool, productId: ?string, categoryId: ?string, categoryName: ?string}>, profile: array{id: string, name: string, provider: string}}}|array{message: string}, array{}>
	 *
	 * 200: Receipt scanned
	 * 401: Current user is not logged in
	 * 422: Missing file, too large, or unsupported image type
	 * 409: No active LLM profile configured
	 * 502: LLM provider or parse failure
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/receipts/scan')]
	public function scan(): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$uploaded = $this->request->getUploadedFile('receipt');
		if (!is_array($uploaded)) {
			return new DataResponse(['message' => 'Receipt file is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		if (($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			return new DataResponse(['message' => 'Upload failed'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$tmpName = isset($uploaded['tmp_name']) && is_string($uploaded['tmp_name']) ? $uploaded['tmp_name'] : '';
		if ($tmpName === '' || !is_file($tmpName)) {
			return new DataResponse(['message' => 'Upload failed'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$size = filesize($tmpName);
		if ($size === false || $size <= 0) {
			return new DataResponse(['message' => 'The receipt is empty'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		if ($size > self::MAX_BYTES) {
			return new DataResponse(['message' => 'The receipt exceeds the 4 MB limit'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$mime = $this->mimeTypeDetector->detectContent($tmpName);
		if (!isset(self::ALLOWED_MIME[$mime])) {
			return new DataResponse(['message' => 'Unsupported image type'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		try {
			$scan = $this->scanService->scan($userId, $tmpName, $mime);
		} catch (NoActiveLlmProfileException) {
			return new DataResponse(['message' => 'No active LLM profile'], Http::STATUS_CONFLICT);
		} catch (\Exception $e) {
			$this->logger->error('Failed to scan receipt', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to scan the receipt'], Http::STATUS_BAD_GATEWAY);
		}

		return new DataResponse(['scan' => $scan], Http::STATUS_OK);
	}
}
