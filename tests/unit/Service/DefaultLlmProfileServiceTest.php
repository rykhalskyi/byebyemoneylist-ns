<?php

declare(strict_types=1);

namespace Service;

use OCA\ByeByeMoneyList\Config\EnvLoader;
use OCA\ByeByeMoneyList\Db\LlmProfileMapper;
use OCA\ByeByeMoneyList\Entity\LlmProfileEntity;
use OCA\ByeByeMoneyList\Service\DefaultLlmProfileService;
use OCP\DB\Exception as DbException;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DefaultLlmProfileServiceTest extends TestCase {
	private LlmProfileMapper $mapper;
	private ICrypto $crypto;
	private EnvLoader $env;
	private DefaultLlmProfileService $service;

	protected function setUp(): void {
		$this->mapper = $this->createMock(LlmProfileMapper::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->env = $this->createMock(EnvLoader::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->service = new DefaultLlmProfileService(
			$this->mapper,
			$this->crypto,
			$this->env,
			$logger,
		);
	}

	public function testCreatesEncryptedActiveSiliconFlowProfile(): void {
		$inserted = null;

		$this->mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([]);
		$this->env->expects($this->once())
			->method('get')
			->with(DefaultLlmProfileService::API_KEY_ENV)
			->willReturn('secret-token');
		$this->crypto->expects($this->once())
			->method('encrypt')
			->with('secret-token')
			->willReturn('encrypted-token');
		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (LlmProfileEntity $profile) use (&$inserted): LlmProfileEntity {
				$inserted = $profile;
				return $profile;
			});
		$this->mapper->expects($this->once())
			->method('setActive')
			->with($this->isType('string'), 'alice', true);

		$result = $this->service->ensureForUser('alice');

		$this->assertNotNull($result);
		$this->assertSame($result, $inserted);
		$this->assertSame('alice', $inserted->getOwner());
		$this->assertSame(DefaultLlmProfileService::PROFILE_NAME, $inserted->getName());
		$this->assertSame(DefaultLlmProfileService::PROVIDER, $inserted->getProvider());
		$this->assertSame('encrypted-token', $inserted->getApiKey());
		$this->assertSame(DefaultLlmProfileService::DEFAULT_MODEL, $inserted->getModel());
		$this->assertTrue($inserted->getIsActive());
	}

	public function testReturnsNullWhenProfileAlreadyExists(): void {
		$profile = new LlmProfileEntity();
		$profile->setId('existing');

		$this->mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([$profile]);
		$this->env->expects($this->never())->method('get');
		$this->crypto->expects($this->never())->method('encrypt');
		$this->mapper->expects($this->never())->method('insert');

		$this->assertNull($this->service->ensureForUser('alice'));
	}

	public function testReturnsNullWhenTokenIsNotConfigured(): void {
		$this->mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([]);
		$this->env->expects($this->once())
			->method('get')
			->with(DefaultLlmProfileService::API_KEY_ENV)
			->willReturn(null);
		$this->crypto->expects($this->never())->method('encrypt');
		$this->mapper->expects($this->never())->method('insert');

		$this->assertNull($this->service->ensureForUser('alice'));
	}

	public function testDoesNotCreateProfileWhenEnvFileIsMissing(): void {
		$mapper = $this->createMock(LlmProfileMapper::class);
		$mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([]);
		$mapper->expects($this->never())->method('insert');
		$crypto = $this->createMock(ICrypto::class);
		$crypto->expects($this->never())->method('encrypt');
		$logger = $this->createMock(LoggerInterface::class);

		$service = new DefaultLlmProfileService(
			$mapper,
			$crypto,
			new EnvLoader('/nonexistent/bbml/.env'),
			$logger,
		);

		$this->assertNull($service->ensureForUser('alice'));
	}

	public function testReturnsNullWhenEncryptionFails(): void {
		$this->mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([]);
		$this->env->expects($this->once())
			->method('get')
			->willReturn('secret-token');
		$this->crypto->expects($this->once())
			->method('encrypt')
			->willThrowException(new \RuntimeException('encryption failed'));
		$this->mapper->expects($this->never())->method('insert');

		$this->assertNull($this->service->ensureForUser('alice'));
	}

	public function testReturnsNullOnConcurrentUniqueConstraintViolation(): void {
		$exception = $this->createMock(DbException::class);
		$exception->method('getReason')->willReturn(DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION);

		$this->mapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([]);
		$this->env->expects($this->once())
			->method('get')
			->willReturn('secret-token');
		$this->crypto->expects($this->once())
			->method('encrypt')
			->willReturn('encrypted-token');
		$this->mapper->expects($this->once())
			->method('insert')
			->willThrowException($exception);
		$this->mapper->expects($this->never())->method('setActive');

		$this->assertNull($this->service->ensureForUser('alice'));
	}
}
