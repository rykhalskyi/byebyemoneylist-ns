<?php

declare(strict_types=1);

namespace Controller;

use OCA\ByeByeMoneyList\Controller\ReceiptCommitController;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Service\Receipt\ReceiptCommitService;
use OCP\AppFramework\Http;
use OCP\Files\IMimeTypeDetector;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ReceiptCommitControllerTest extends TestCase {
	private ReceiptCommitController $controller;
	private IRequest $request;
	private ReceiptCommitService $commitService;
	private ListMapper $listMapper;
	private IUserSession $userSession;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->commitService = $this->createMock(ReceiptCommitService::class);
		$this->listMapper = $this->createMock(ListMapper::class);
		$mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->controller = new ReceiptCommitController(
			$this->request,
			$this->commitService,
			$this->listMapper,
			$mimeTypeDetector,
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

	public function testCommitReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$this->commitService->expects($this->never())->method('commit');

		$response = $this->controller->commit('{}');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testCommitReturnsUnprocessableWhenPayloadMissing(): void {
		$this->mockUser('alice');

		$response = $this->controller->commit(null);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCommitReturnsUnprocessableWhenNameMissing(): void {
		$this->mockUser('alice');

		$response = $this->controller->commit('{"finalTotal":3,"items":[]}');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCommitCreatesListAndReturnsIt(): void {
		$this->mockUser('alice');

		$payload = '{"name":"Aldi 14.09.2026","storeName":"Aldi","finalTotal":3,"purchaseDate":"2026-09-14T10:00:00+00:00","saveReceipt":false,"items":[{"name":"Milk","quantity":2,"price":1.5}]}';

		$list = new ListEntity();
		$list->setId('99999999-aaaa-4bbb-8ccc-dddddddddddd');
		$list->setOwner('alice');
		$list->setName('Aldi 14.09.2026');
		$list->setStatus('finished');
		$list->setIsFinished(true);
		$list->setFinalTotal(3.0);

		$this->commitService->expects($this->once())
			->method('commit')
			->willReturn($list);

		$this->listMapper->expects($this->once())
			->method('findCategoryIdsByListIds')
			->with(['99999999-aaaa-4bbb-8ccc-dddddddddddd'])
			->willReturn(['99999999-aaaa-4bbb-8ccc-dddddddddddd' => ['c1']]);

		$response = $this->controller->commit($payload);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame('finished', $response->getData()['list']['status']);
		$this->assertSame(['c1'], $response->getData()['list']['categoryIds']);
	}
}
