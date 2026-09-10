<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\ProductImageController;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Service\ProductPictureService;
use OCP\AppFramework\Http;
use OCP\Files\IMimeTypeDetector;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProductImageControllerTest extends TestCase {
	private ProductImageController $controller;
	private IRequest $request;
	private ProductMapper $productMapper;
	private ProductPictureService $pictureService;
	private IMimeTypeDetector $mimeTypeDetector;
	private IUserSession $userSession;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->productMapper = $this->createMock(ProductMapper::class);
		$this->pictureService = $this->createMock(ProductPictureService::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ProductImageController(
			$this->request,
			$this->productMapper,
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

	private function product(?string $picturePath = null): ProductEntity {
		$product = new ProductEntity();
		$product->setId('11111111-2222-4333-8444-555555555555');
		$product->setOwner('alice');
		$product->setName('Milk');
		$product->setStatus('reviewed');
		$product->setIsFavorite(false);
		$product->setIsSubscription(false);
		$product->setIsIncome(false);
		$product->setPicturePath($picturePath);
		return $product;
	}

	/**
	 * @return array{0: string, 1: string} [tmp file path, content]
	 */
	private function uploadedFileParse(string $content = 'png-bytes'): array {
		$tmp = tempnam(sys_get_temp_dir(), 'bbml-test-');
		if ($tmp === false) {
			self::fail('Could not create a temp file');
		}
		file_put_contents($tmp, $content);
		return [$tmp, $content];
	}

	public function testUploadStoresPictureAndReturnsDataUrl(): void {
		$this->mockUser('alice');

		$product = $this->product();
		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->with('11111111-2222-4333-8444-555555555555', 'alice')
			->willReturn($product);

		[$tmp] = $this->uploadedFileParse();
		$this->request->method('getUploadedFile')
			->with('picture')
			->willReturn([
				'name' => 'milk.png',
				'type' => 'image/png',
				'tmp_name' => $tmp,
				'error' => UPLOAD_ERR_OK,
				'size' => 9,
			]);

		$this->mimeTypeDetector->expects($this->once())
			->method('detectContent')
			->with($tmp)
			->willReturn('image/png');

		$path = 'product-pictures/alice/11111111-2222-4333-8444-555555555555.png';
		$this->pictureService->expects($this->once())
			->method('store')
			->with('alice', '11111111-2222-4333-8444-555555555555', $tmp, 'png')
			->willReturn($path);
		$this->pictureService->method('getData')
			->with('alice', $path)
			->willReturn(['dataUrl' => 'data:image/png;base64,cG5n', 'mime' => 'image/png']);

		$this->productMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn (ProductEntity $entity): bool => $entity->getPicturePath() === $path))
			->willReturnArgument(0);

		$response = $this->controller->upload('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('image/png', $response->getData()['picture']['mime']);

		@unlink($tmp);
	}

	public function testUploadRollsBackNewFileWhenPersistingFails(): void {
		$this->mockUser('alice');

		$previousPath = 'product-pictures/alice/11111111-2222-4333-8444-555555555555.jpg';
		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($this->product($previousPath));

		[$tmp] = $this->uploadedFileParse();
		$this->request->method('getUploadedFile')->willReturn([
			'name' => 'milk.png',
			'type' => 'image/png',
			'tmp_name' => $tmp,
			'error' => UPLOAD_ERR_OK,
			'size' => 9,
		]);

		$this->mimeTypeDetector->expects($this->once())
			->method('detectContent')
			->with($tmp)
			->willReturn('image/png');

		$path = 'product-pictures/alice/11111111-2222-4333-8444-555555555555.png';
		$this->pictureService->expects($this->once())
			->method('store')
			->with('alice', '11111111-2222-4333-8444-555555555555', $tmp, 'png')
			->willReturn($path);

		$this->pictureService->expects($this->once())
			->method('delete')
			->with('alice', $path);

		$this->productMapper->expects($this->once())
			->method('update')
			->willThrowException(new \RuntimeException('db down'));

		$response = $this->controller->upload('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());

		@unlink($tmp);
	}

	public function testUploadRejectsUnsupportedMimeType(): void {
		$this->mockUser('alice');

		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($this->product());

		[$tmp] = $this->uploadedFileParse('%PDF-1.4');
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
		$this->productMapper->expects($this->never())->method('update');

		$response = $this->controller->upload('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());

		@unlink($tmp);
	}

	public function testUploadRejectsOversizedPicture(): void {
		$this->mockUser('alice');

		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($this->product());

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

		$response = $this->controller->upload('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());

		@unlink($tmp);
	}

	public function testUploadReturnsUnprocessableWhenFileMissing(): void {
		$this->mockUser('alice');

		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($this->product());

		$this->request->method('getUploadedFile')->willReturn(null);

		$response = $this->controller->upload('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testUploadReturnsNotFoundForForeignProduct(): void {
		$this->mockUser('alice');

		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn(null);

		$this->request->expects($this->never())->method('getUploadedFile');

		$response = $this->controller->upload('99999999-0000-4444-8555-777777777777');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUploadReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$this->productMapper->expects($this->never())->method('findByIdAndOwner');

		$response = $this->controller->upload('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testDestroyRemovesPicture(): void {
		$this->mockUser('alice');

		$path = 'product-pictures/alice/11111111-2222-4333-8444-555555555555.png';
		$product = $this->product($path);
		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($product);

		$this->pictureService->expects($this->once())
			->method('delete')
			->with('alice', $path);

		$this->productMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn (ProductEntity $entity): bool => $entity->getPicturePath() === null))
			->willReturnArgument(0);

		$response = $this->controller->destroy('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testDestroyReturnsNotFoundForForeignProduct(): void {
		$this->mockUser('alice');

		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn(null);

		$this->pictureService->expects($this->never())->method('delete');

		$response = $this->controller->destroy('99999999-0000-4444-8555-777777777777');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testShowReturnsPicture(): void {
		$this->mockUser('alice');

		$path = 'product-pictures/alice/11111111-2222-4333-8444-555555555555.png';
		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($this->product($path));

		$this->pictureService->expects($this->once())
			->method('getData')
			->with('alice', $path)
			->willReturn(['dataUrl' => 'data:image/png;base64,cG5n', 'mime' => 'image/png']);

		$response = $this->controller->show('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('data:image/png;base64,cG5n', $response->getData()['picture']['dataUrl']);
	}

	public function testShowReturnsNullWhenProductHasNoPicture(): void {
		$this->mockUser('alice');

		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn($this->product());

		$this->pictureService->expects($this->never())->method('getData');

		$response = $this->controller->show('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertNull($response->getData()['picture']);
	}

	public function testShowReturnsNotFoundForForeignProduct(): void {
		$this->mockUser('alice');

		$this->productMapper->expects($this->once())
			->method('findByIdAndOwner')
			->willReturn(null);

		$response = $this->controller->show('99999999-0000-4444-8555-777777777777');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testShowReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->show('11111111-2222-4333-8444-555555555555');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}
}
