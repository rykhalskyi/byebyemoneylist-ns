<?php

declare(strict_types=1);

namespace Controller;

use DateTime;
use OCA\ByeByeMoneyList\Controller\LlmProfileController;
use OCA\ByeByeMoneyList\Db\LlmProfileMapper;
use OCA\ByeByeMoneyList\Entity\LlmProfileEntity;
use OCA\ByeByeMoneyList\Service\DefaultLlmProfileService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LlmProfileControllerTest extends TestCase {
	private LlmProfileController $controller;
	private DefaultLlmProfileService $defaultLlmProfileService;
	private ICrypto $crypto;
	private IUserSession $userSession;

	protected function setUp(): void {
		$request = $this->createMock(IRequest::class);
		$mapper = $this->createMock(LlmProfileMapper::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$logger = $this->createMock(LoggerInterface::class);
		$this->defaultLlmProfileService = $this->createMock(DefaultLlmProfileService::class);

		$this->controller = new LlmProfileController(
			$request,
			$mapper,
			$this->crypto,
			$this->userSession,
			$logger,
			$this->defaultLlmProfileService,
		);
	}

	private function mockUser(string $uid): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$this->userSession->method('getUser')->willReturn($user);
	}

	private function profile(): LlmProfileEntity {
		$profile = new LlmProfileEntity();
		$profile->setId('11111111-2222-4333-8444-555555555555');
		$profile->setOwner('alice');
		$profile->setName('SiliconFlow');
		$profile->setProvider('siliconflow');
		$profile->setApiKey('encrypted-token');
		$profile->setModel('Qwen/Qwen3-VL-8B-Instruct');
		$profile->setIsActive(true);
		$profile->setCreatedAt(new DateTime());
		return $profile;
	}

	public function testEnsureDefaultReturnsCreatedProfile(): void {
		$this->mockUser('alice');
		$profile = $this->profile();

		$this->defaultLlmProfileService->expects($this->once())
			->method('ensureForUser')
			->with('alice')
			->willReturn($profile);
		$this->crypto->method('decrypt')->with('encrypted-token')->willReturn('secret-token');

		$response = $this->controller->ensureDefault();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertTrue($data['created']);
		$this->assertSame('siliconflow', $data['profile']['provider']);
		$this->assertSame('••••••••oken', $data['profile']['apiKeyMasked']);
	}

	public function testEnsureDefaultReportsNoopWhenProfileExists(): void {
		$this->mockUser('alice');

		$this->defaultLlmProfileService->expects($this->once())
			->method('ensureForUser')
			->with('alice')
			->willReturn(null);

		$response = $this->controller->ensureDefault();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['created']);
		$this->assertNull($data['profile']);
	}

	public function testEnsureDefaultReturnsUnauthorizedWhenNotLoggedIn(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->ensureDefault();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testEnsureDefaultReturnsInternalServerErrorOnFailure(): void {
		$this->mockUser('alice');

		$this->defaultLlmProfileService->expects($this->once())
			->method('ensureForUser')
			->willThrowException(new \RuntimeException('boom'));

		$response = $this->controller->ensureDefault();

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
	}
}
