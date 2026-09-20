<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service;

use DateTime;
use OCA\ByeByeMoneyList\Config\EnvLoader;
use OCA\ByeByeMoneyList\Db\LlmProfileMapper;
use OCA\ByeByeMoneyList\Entity\LlmProfileEntity;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\DB\Exception as DbException;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/**
 * Ensures a default SiliconFlow LLM profile exists for a user.
 *
 * The API token is read from the app-local, git-ignored ".env" file
 * (SILICONFLOW_API_KEY) and seeded once, encrypted, into the profile.
 *
 * @psalm-suppress UnusedClass
 */
class DefaultLlmProfileService {
	public const PROVIDER = 'siliconflow';
	public const PROFILE_NAME = 'SiliconFlow';
	public const DEFAULT_MODEL = 'Qwen/Qwen3-VL-8B-Instruct';
	public const API_KEY_ENV = 'SILICONFLOW_API_KEY';

	private LlmProfileMapper $mapper;
	private ICrypto $crypto;
	private EnvLoader $env;
	private LoggerInterface $logger;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		LlmProfileMapper $mapper,
		ICrypto $crypto,
		EnvLoader $env,
		LoggerInterface $logger,
	) {
		$this->mapper = $mapper;
		$this->crypto = $crypto;
		$this->env = $env;
		$this->logger = $logger;
	}

	/**
	 * Create and activate the default profile when the user has none.
	 *
	 * Returns null when a profile already exists, no token is configured, or
	 * the profile races with a concurrent request.
	 */
	public function ensureForUser(string $userId): ?LlmProfileEntity {
		if ($this->mapper->findAllByOwner($userId) !== []) {
			return null;
		}

		$apiKey = trim($this->env->get(self::API_KEY_ENV) ?? '');
		if ($apiKey === '') {
			$this->logger->warning('Skipping default LLM profile: ' . self::API_KEY_ENV . ' is not configured');

			return null;
		}

		$profile = new LlmProfileEntity();
		$profile->setId(Uuid::v5($userId . ':default-llm-profile'));
		$profile->setOwner($userId);
		$profile->setName(self::PROFILE_NAME);
		$profile->setProvider(self::PROVIDER);
		$profile->setModel(self::DEFAULT_MODEL);
		$profile->setConnectTimeout(30);
		$profile->setReadTimeout(60);
		$profile->setMaxTokens(2048);
		$profile->setIsActive(true);
		$profile->setCreatedAt(new DateTime());

		try {
			$profile->setApiKey($this->crypto->encrypt($apiKey));
		} catch (\Exception $e) {
			$this->logger->error('Failed to encrypt default LLM API key', ['exception' => $e]);

			return null;
		}

		try {
			$created = $this->mapper->insert($profile);
			$this->mapper->setActive($created->getId(), $userId, true);

			return $created;
		} catch (DbException $e) {
			if (in_array($e->getReason(), [
				DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION,
				DbException::REASON_CONSTRAINT_VIOLATION,
			], true)) {
				// A concurrent request created the default profile first.
				return null;
			}

			$this->logger->error('Failed to create default LLM profile', ['exception' => $e]);

			return null;
		} catch (\Exception $e) {
			$this->logger->error('Failed to create default LLM profile', ['exception' => $e]);

			return null;
		}
	}
}
