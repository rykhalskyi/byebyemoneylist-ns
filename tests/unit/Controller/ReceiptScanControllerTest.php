<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\ReceiptScanController;
use OCA\ByeByeMoneyList\Service\Receipt\NoActiveLlmProfileException;
use OCA\ByeByeMoneyList\Service\Receipt\ReceiptScanService;
use OCP\AppFramework\Http;
use OCP\Files\IMimeTypeDetector;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ReceiptScanControllerTest extends TestCase {
	private ReceiptScanController $controller;
	private IRequest $request;
	private ReceiptScanService $scanService;
	private IMimeTypeDetector $mimeTypeDetector;
	private IUserSession $userSession;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->scanService = $this->createMock(ReceiptScanService::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ReceiptScanController(
			$this->request,
			$this->scanService,
			$this->mimeTypeDetector,
			$this->userSession,
			$logger,
		);
	}

	private function mockUser(string $uid): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
		return $user;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function uploadedFile(string $content = 'jpeg-bytes'): array {
		$tmp = tempnam(sys_get_temp_dir(), 'bbml-test-');
		self::assertNotFalse($tmp);
		file_put_contents($tmp, $content);

		return [
			'name' => 'receipt.jpg',
			'type' => 'image/jpeg',
			'tmp_name' => $tmp,
			'error' => UPLOAD_ERR_OK,
			'size' => 11,
		];
	}

	public function testScanReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$this->request->expects($this->never())->method('getUploadedFile');

		$response = $this->controller->scan();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testScanReturnsConflictWhenNoActiveProfile(): void {
		$this->mockUser('alice');

		$upload = $this->uploadedFile();
		$this->request->method('getUploadedFile')->with('receipt')->willReturn($upload);
		$this->mimeTypeDetector->method('detectContent')->willReturn('image/jpeg');

		$this->scanService->expects($this->once())
			->method('scan')
			->willThrowException(new NoActiveLlmProfileException('No active LLM profile'));

		$response = $this->controller->scan();

		$this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());

		@unlink($upload['tmp_name']);
	}

	public function testScanReturnsScanResult(): void {
		$this->mockUser('alice');

		$upload = $this->uploadedFile();
		$this->request->method('getUploadedFile')->with('receipt')->willReturn($upload);
		$this->mimeTypeDetector->method('detectContent')->with($upload['tmp_name'])->willReturn('image/jpeg');

		$this->scanService->expects($this->once())
			->method('scan')
			->with('alice', $upload['tmp_name'], 'image/jpeg')
			->willReturn([
				'storeName' => 'Aldi',
				'storeAddress' => null,
				'storeId' => null,
				'totalSum' => 3.0,
				'items' => [],
				'profile' => ['id' => 'p1', 'name' => 'DeepSeek', 'provider' => 'deepseek'],
			]);

		$response = $this->controller->scan();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('Aldi', $response->getData()['scan']['storeName']);

		@unlink($upload['tmp_name']);
	}

	public function testScanRejectsUnsupportedMimeType(): void {
		$this->mockUser('alice');

		$upload = $this->uploadedFile('%PDF-1.4');
		$this->request->method('getUploadedFile')->with('receipt')->willReturn($upload);
		$this->mimeTypeDetector->method('detectContent')->willReturn('application/pdf');

		$this->scanService->expects($this->never())->method('scan');

		$response = $this->controller->scan();

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());

		@unlink($upload['tmp_name']);
	}

	public function testScanReturnsUnprocessableWhenFileMissing(): void {
		$this->mockUser('alice');

		$this->request->method('getUploadedFile')->with('receipt')->willReturn(null);

		$response = $this->controller->scan();

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}
}
