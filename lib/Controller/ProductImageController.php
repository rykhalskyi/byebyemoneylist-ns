<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Service\ProductPictureService;
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
 * Product pictures: store one image per product in the app's private appdata and
 * serve it as a base64 data URL for the product info dialog.
 *
 * @psalm-suppress UnusedClass
 */
class ProductImageController extends OCSController {
	private const MAX_BYTES = 4 * 1024 * 1024;

	/** @var array<string, string> allowed MIME type => file extension */
	private const ALLOWED_MIME = [
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/webp' => 'webp',
		'image/gif' => 'gif',
	];

	private ProductMapper $productMapper;
	private ProductPictureService $pictureService;
	private IMimeTypeDetector $mimeTypeDetector;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		ProductMapper $productMapper,
		ProductPictureService $pictureService,
		IMimeTypeDetector $mimeTypeDetector,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->productMapper = $productMapper;
		$this->pictureService = $pictureService;
		$this->mimeTypeDetector = $mimeTypeDetector;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Upload (or replace) the picture of one of the current user's products
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement, RedundantConditionGivenDocblockType, DocblockTypeContradiction
	 *
	 * @param string $id Product id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{picture: array{dataUrl: string, mime: string}}|array{message: string}, array{}>
	 *
	 * 200: Picture stored
	 * 401: Current user is not logged in
	 * 404: Product not found or not owned by the current user
	 * 422: Missing file, too large, or unsupported image type
	 * 500: Failed to store the picture
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/products/{id}/picture')]
	public function upload(string $id): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$product = $this->productMapper->findByIdAndOwner($id, $userId);
		if ($product === null) {
			return new DataResponse(['message' => 'Product not found'], Http::STATUS_NOT_FOUND);
		}

		$uploaded = $this->request->getUploadedFile('picture');
		if (!is_array($uploaded)) {
			return new DataResponse(['message' => 'Picture file is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
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
			return new DataResponse(['message' => 'The picture is empty'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		if ($size > self::MAX_BYTES) {
			return new DataResponse(['message' => 'The picture exceeds the 4 MB limit'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$mime = $this->mimeTypeDetector->detectContent($tmpName);
		if (!isset(self::ALLOWED_MIME[$mime])) {
			return new DataResponse(['message' => 'Unsupported image type'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		try {
			$path = $this->pictureService->store($userId, $id, $tmpName, self::ALLOWED_MIME[$mime]);
		} catch (\Exception $e) {
			$this->logger->error('Failed to store product picture', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to store the picture'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$previousPath = $product->getPicturePath();
		if ($previousPath !== null && $previousPath !== $path) {
			try {
				$this->pictureService->delete($userId, $previousPath);
			} catch (\Exception $e) {
				$this->logger->warning('Failed to remove replaced product picture', ['exception' => $e]);
			}
		}

		$product->setPicturePath($path);
		$this->productMapper->update($product);

		$data = $this->pictureService->getData($userId, $path);
		if ($data === null) {
			return new DataResponse(['message' => 'Failed to store the picture'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['picture' => $data], Http::STATUS_OK);
	}

	/**
	 * Delete the picture of one of the current user's products
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Product id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{}|array{message: string}, array{}>
	 *
	 * 200: Picture deleted (or the product had none)
	 * 401: Current user is not logged in
	 * 404: Product not found or not owned by the current user
	 * 500: Failed to delete the picture
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/products/{id}/picture')]
	public function destroy(string $id): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$product = $this->productMapper->findByIdAndOwner($id, $userId);
		if ($product === null) {
			return new DataResponse(['message' => 'Product not found'], Http::STATUS_NOT_FOUND);
		}

		try {
			$this->pictureService->delete($userId, $product->getPicturePath());
		} catch (\Exception $e) {
			$this->logger->error('Failed to delete product picture', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to delete the picture'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$product->setPicturePath(null);
		$this->productMapper->update($product);

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * Get the picture of one of the current user's products as a base64 data URL
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Product id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{picture: array{dataUrl: string, mime: string}|null}|array{message: string}, array{}>
	 *
	 * 200: Picture returned (may be null when the product has no picture)
	 * 401: Current user is not logged in
	 * 404: Product not found or not owned by the current user
	 * 500: Failed to read the picture
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/products/{id}/picture')]
	public function show(string $id): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$product = $this->productMapper->findByIdAndOwner($id, $userId);
		if ($product === null) {
			return new DataResponse(['message' => 'Product not found'], Http::STATUS_NOT_FOUND);
		}

		$path = $product->getPicturePath();
		if ($path === null || $path === '') {
			return new DataResponse(['picture' => null], Http::STATUS_OK);
		}

		try {
			$data = $this->pictureService->getData($userId, $path);
		} catch (\Exception $e) {
			$this->logger->error('Failed to read product picture', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to read the picture'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['picture' => $data], Http::STATUS_OK);
	}
}
