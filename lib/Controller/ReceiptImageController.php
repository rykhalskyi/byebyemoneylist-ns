<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Service\ReceiptPictureService;
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
 * Receipt pictures: store one image per shopping list in the app's private appdata and
 * serve it as a base64 data URL.
 *
 * @psalm-suppress UnusedClass
 */
class ReceiptImageController extends OCSController {
	private const MAX_BYTES = 4 * 1024 * 1024;

	/** @var array<string, string> allowed MIME type => file extension */
	private const ALLOWED_MIME = [
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/webp' => 'webp',
		'image/gif' => 'gif',
	];

	private ListMapper $listMapper;
	private ReceiptPictureService $pictureService;
	private IMimeTypeDetector $mimeTypeDetector;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		ListMapper $listMapper,
		ReceiptPictureService $pictureService,
		IMimeTypeDetector $mimeTypeDetector,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->listMapper = $listMapper;
		$this->pictureService = $pictureService;
		$this->mimeTypeDetector = $mimeTypeDetector;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Upload (or replace) the receipt picture of one of the current user's lists
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement, RedundantConditionGivenDocblockType, DocblockTypeContradiction
	 *
	 * @param string $id List id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{receipt: array{dataUrl: string, mime: string}}|array{message: string}, array{}>
	 *
	 * 200: Receipt stored
	 * 401: Current user is not logged in
	 * 404: List not found or not owned by the current user
	 * 422: Missing file, too large, or unsupported image type
	 * 500: Failed to store the receipt
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/lists/{id}/receipt')]
	public function upload(string $id): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$list = $this->listMapper->findByIdAndOwner($id, $userId);
		if ($list === null) {
			return new DataResponse(['message' => 'List not found'], Http::STATUS_NOT_FOUND);
		}

		$uploaded = $this->request->getUploadedFile('receipt');
		if (!is_array($uploaded)) {
			$uploaded = $this->request->getUploadedFile('picture');
		}
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
			$path = $this->pictureService->store($userId, $id, $tmpName, self::ALLOWED_MIME[$mime]);
		} catch (\Exception $e) {
			$this->logger->error('Failed to store receipt picture', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to store the receipt'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$previousPath = $list->getReceiptPath();
		$list->setReceiptPath($path);
		try {
			$this->listMapper->update($list);
		} catch (\Exception $e) {
			$this->logger->error('Failed to store receipt picture', ['exception' => $e]);
			if ($previousPath !== $path) {
				try {
					$this->pictureService->delete($userId, $path);
				} catch (\Exception $cleanupError) {
					$this->logger->warning('Failed to clean up the new receipt picture', ['exception' => $cleanupError]);
				}
			}
			return new DataResponse(['message' => 'Failed to store the receipt'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if ($previousPath !== null && $previousPath !== $path) {
			try {
				$this->pictureService->delete($userId, $previousPath);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to remove replaced receipt picture', ['exception' => $e]);
			}
		}

		$data = $this->pictureService->getData($userId, $path);
		if ($data === null) {
			return new DataResponse(['message' => 'Failed to store the receipt'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['receipt' => $data], Http::STATUS_OK);
	}

	/**
	 * Delete the receipt picture of one of the current user's lists
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id List id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{}|array{message: string}, array{}>
	 *
	 * 200: Receipt deleted (or the list had none)
	 * 401: Current user is not logged in
	 * 404: List not found or not owned by the current user
	 * 500: Failed to delete the receipt
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/lists/{id}/receipt')]
	public function destroy(string $id): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$list = $this->listMapper->findByIdAndOwner($id, $userId);
		if ($list === null) {
			return new DataResponse(['message' => 'List not found'], Http::STATUS_NOT_FOUND);
		}

		$path = $list->getReceiptPath();
		$list->setReceiptPath(null);
		try {
			$this->listMapper->update($list);
		} catch (\Exception $e) {
			$this->logger->error('Failed to delete receipt picture', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to delete the receipt'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		try {
			$this->pictureService->delete($userId, $path);
		} catch (\Exception $e) {
			$this->logger->warning('Failed to remove receipt picture file', ['exception' => $e]);
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * Get the receipt picture of one of the current user's lists as a base64 data URL
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id List id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{receipt: array{dataUrl: string, mime: string}|null}|array{message: string}, array{}>
	 *
	 * 200: Receipt returned (may be null when the list has no receipt)
	 * 401: Current user is not logged in
	 * 404: List not found or not owned by the current user
	 * 500: Failed to read the receipt
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/lists/{id}/receipt')]
	public function show(string $id): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$list = $this->listMapper->findByIdAndOwner($id, $userId);
		if ($list === null) {
			return new DataResponse(['message' => 'List not found'], Http::STATUS_NOT_FOUND);
		}

		$path = $list->getReceiptPath();
		if ($path === null || $path === '') {
			return new DataResponse(['receipt' => null], Http::STATUS_OK);
		}

		try {
			$data = $this->pictureService->getData($userId, $path);
		} catch (\Exception $e) {
			$this->logger->error('Failed to read receipt picture', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to read the receipt'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['receipt' => $data], Http::STATUS_OK);
	}
}
