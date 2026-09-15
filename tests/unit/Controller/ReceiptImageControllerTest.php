<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\ReceiptImageController;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Service\ReceiptPictureService;
use OCP\AppFramework\Http;
use OCP\Files\IMimeTypeDetector;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ReceiptImageControllerTest extends TestCase {
	private const LIST_ID = '11111111-2222-4333-8444-555555555555';

	private ReceiptImageController $controller;
	private IRequest $request;
	private ListMapper $listMapper;
	private ReceiptPictureService $pictureService;
	private IMimeTypeDetector $mimeTypeDetector;
	private IUserSession $userSession;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->listMapper = $this->createMock(ListMapper::class);
		$this->pictureService = $this->createMock(ReceiptPictureService::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ReceiptImageController(
			$this->request,
			$this->listMapper,
			$this->pictureService,
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

	private function list(?string $receiptPath = null): ListEntity {
		$list = new ListEntity();
		$list->setId(self::LIST_ID);
		$list->setOwner('alice');
		$list->setName('Groceries');
		$list->setStatus('new');
		$list->setReceiptPath($receiptPath);
		return $list;
	}

	/**
	 * @return string temporary file path
	 */
	private function uploadedFile(string $content = 'png-bytes'): string {
		$tmp = tempnam(sys_get_temp_dir(), 'bbml-test-');
		if ($tmp === false) {
			self::fail('Could not create a temp file');
		}
		file_put_contents($tmp, $content);
		return $tmp;
	}

	public function testUploadStoresReceiptAndReturnsDataUrl(): void {
		$this->mockUser('alice');

		$list = $this->list();
		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with(self::LIST_ID, 'alice')
			->willReturn($list);

		$tmp = $this->uploadedFile();
		$this->request->method('getUploadedFile')->willReturn([
			'name' => 'receipt.png',
			'type' => 'image/png',
			'tmp_name' => $tmp,
			'error' => UPLOAD_ERR_OK,
			'size' => 9,
		]);

		$this->mimeTypeDetector->expects($this->once())
			->method('detectContent')
			->with($tmp)
			->willReturn('image/png');

		$path = 'receipts/alice/' . self::LIST_ID . '.png';
		$this->pictureService->expects($this->once())
			->method('store')
			->with('alice', self::LIST_ID, $tmp, 'png')
			->willReturn($path);
		$this->pictureService->method('getData')
			->with('alice', $path)
			->willReturn(['dataUrl' => 'data:image/png;base64,cG5n', 'mime' => 'image/png']);

		$this->listMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn (ListEntity $entity): bool => $entity->getReceiptPath() === $path))
			->willReturnArgument(0);

		$response = $this->controller->upload(self::LIST_ID);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('image/png', $response->getData()['receipt']['mime']);

		@unlink($tmp);
	}

	public function testUploadRejectsUnsupportedMimeType(): void {
		$this->mockUser('alice');

		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($this->list());

		$tmp = $this->uploadedFile('%PDF-1.4');
		$this->request->method('getUploadedFile')->willReturn([
			'name' => 'doc.pdf',
			'type' => 'application/pdf',
			'tmp_name' => $tmp,
			'error' => UPLOAD_ERR_OK,
			'size' => 8,
		]);

		$this->mimeTypeDetector->expects($this->once())
			->method('detectContent')
			->willReturn('application/pdf');

		$this->pictureService->expects($this->never())->method('store');
		$this->listMapper->expects($this->never())->method('update');

		$response = $this->controller->upload(self::LIST_ID);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());

		@unlink($tmp);
	}

	public function testUploadRejectsOversizedReceipt(): void {
		$this->mockUser('alice');

		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($this->list());

		$tmp = tempnam(sys_get_temp_dir(), 'bbml-test-');
		self::assertNotFalse($tmp);
		$handle = fopen($tmp, 'w');
		self::assertNotFalse($handle);
		fseek($handle, 4 * 1024 * 1024 + 1);
		fwrite($handle, '0');
		fclose($handle);

		$this->request->method('getUploadedFile')->willReturn([
			'name' => 'big.png',
			'type' => 'image/png',
			'tmp_name' => $tmp,
			'error' => UPLOAD_ERR_OK,
			'size' => 4 * 1024 * 1024 + 1,
		]);

		$this->mimeTypeDetector->expects($this->never())->method('detectContent');
		$this->pictureService->expects($this->never())->method('store');

		$response = $this->controller->upload(self::LIST_ID);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());

		@unlink($tmp);
	}

	public function testUploadReturnsUnprocessableWhenFileMissing(): void {
		$this->mockUser('alice');

		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($this->list());

		$this->request->method('getUploadedFile')->willReturn(null);

		$response = $this->controller->upload(self::LIST_ID);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testUploadReturnsNotFoundForForeignList(): void {
		$this->mockUser('alice');

		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn(null);

		$this->request->expects($this->never())->method('getUploadedFile');

		$response = $this->controller->upload('99999999-0000-4444-8555-777777777777');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUploadReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$this->listMapper->expects($this->never())->method('findByIdAndOwner');

		$response = $this->controller->upload(self::LIST_ID);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testDestroyRemovesReceipt(): void {
		$this->mockUser('alice');

		$path = 'receipts/alice/' . self::LIST_ID . '.png';
		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($this->list($path));

		$this->listMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn (ListEntity $entity): bool => $entity->getReceiptPath() === null))
			->willReturnArgument(0);
		$this->pictureService->expects($this->once())
			->method('delete')
			->with('alice', $path);

		$response = $this->controller->destroy(self::LIST_ID);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testShowReturnsReceipt(): void {
		$this->mockUser('alice');

		$path = 'receipts/alice/' . self::LIST_ID . '.png';
		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($this->list($path));

		$this->pictureService->expects($this->once())
			->method('getData')
			->with('alice', $path)
			->willReturn(['dataUrl' => 'data:image/png;base64,cG5n', 'mime' => 'image/png']);

		$response = $this->controller->show(self::LIST_ID);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('data:image/png;base64,cG5n', $response->getData()['receipt']['dataUrl']);
	}

	public function testShowReturnsNullWhenListHasNoReceipt(): void {
		$this->mockUser('alice');

		$this->listMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($this->list());

		$this->pictureService->expects($this->never())->method('getData');

		$response = $this->controller->show(self::LIST_ID);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertNull($response->getData()['receipt']);
	}
}
