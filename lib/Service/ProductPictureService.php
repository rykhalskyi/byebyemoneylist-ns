<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service;

use OCA\ByeByeMoneyList\AppInfo\Application;
use OCP\Files\AppData\IAppDataFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use RuntimeException;

/**
 * Stores and serves product pictures in the app's private appdata folder:
 * `appdata_<instance>/byebyemoneylist/product-pictures/<owner>/<productId>.<ext>`.
 *
 * The database only keeps the appdata-relative path
 * (`product-pictures/<owner>/<productId>.<ext>`) in `bbml_products.picture_path`.
 *
 * @psalm-suppress UnusedClass
 */
class ProductPictureService {
	private const FOLDER = 'product-pictures';

	private IAppData $appData;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IAppDataFactory $appDataFactory) {
		$this->appData = $appDataFactory->get(Application::APP_ID);
	}

	/**
	 * Store the uploaded bytes as the product's picture, replacing any existing file.
	 *
	 * @return string the appdata-relative path of the stored file
	 */
	public function store(string $userId, string $productId, string $tmpPath, string $extension): string {
		$folder = $this->getOrCreateUserFolder($userId);
		$filename = $productId . '.' . $extension;
		if ($folder->fileExists($filename)) {
			$folder->getFile($filename)->delete();
		}

		$content = file_get_contents($tmpPath);
		if ($content === false) {
			throw new RuntimeException('Failed to read uploaded file');
		}

		$folder->newFile($filename, $content);

		return self::FOLDER . '/' . $userId . '/' . $filename;
	}

	/**
	 * Delete the picture file if it exists.
	 */
	public function delete(string $userId, ?string $picturePath): void {
		if ($picturePath === null || $picturePath === '') {
			return;
		}
		$file = $this->getFile($userId, $picturePath);
		if ($file !== null) {
			$file->delete();
		}
	}

	/**
	 * Read the picture as a base64 data URL.
	 *
	 * @return array{dataUrl: string, mime: string}|null
	 */
	public function getData(string $userId, string $picturePath): ?array {
		$file = $this->getFile($userId, $picturePath);
		if ($file === null) {
			return null;
		}
		$mime = $file->getMimeType();
		return [
			'dataUrl' => 'data:' . $mime . ';base64,' . base64_encode($file->getContent()),
			'mime' => $mime,
		];
	}

	private function getFile(string $userId, string $picturePath): ?ISimpleFile {
		$filename = basename($picturePath);
		if ($filename === '') {
			return null;
		}
		$folder = $this->getUserFolder($userId);
		if ($folder === null || !$folder->fileExists($filename)) {
			return null;
		}
		return $folder->getFile($filename);
	}

	private function getUserFolder(string $userId): ?ISimpleFolder {
		try {
			$base = $this->appData->getFolder(self::FOLDER);
		} catch (NotFoundException) {
			return null;
		}
		try {
			return $base->getFolder($userId);
		} catch (NotFoundException) {
			return null;
		}
	}

	private function getOrCreateUserFolder(string $userId): ISimpleFolder {
		try {
			$base = $this->appData->getFolder(self::FOLDER);
		} catch (NotFoundException) {
			$base = $this->appData->newFolder(self::FOLDER);
		}
		try {
			return $base->getFolder($userId);
		} catch (NotFoundException) {
			return $base->newFolder($userId);
		}
	}
}
