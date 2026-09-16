<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use DateTime;
use DateTimeInterface;
use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\LlmProfileMapper;
use OCA\ByeByeMoneyList\Entity\LlmProfileEntity;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class LlmProfileController extends OCSController {
	private LlmProfileMapper $mapper;
	private ICrypto $crypto;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		LlmProfileMapper $mapper,
		ICrypto $crypto,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->mapper = $mapper;
		$this->crypto = $crypto;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Get all LLM profiles for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED, array{profiles: list<array{id: string, name: string, provider: string, apiKeyMasked: string, model: ?string, connectTimeoutSeconds: int, readTimeoutSeconds: int, maxTokens: int, isActive: bool, createdAt: string, updatedAt: ?string}>}|array{message: string}, array{}>
	 *
	 * 200: Profiles returned
	 * 401: Current user is not logged in
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/llm-profiles')]
	public function index(): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$profiles = $this->mapper->findAllByOwner($userId);
		$serialized = array_values(array_map(
			fn (LlmProfileEntity $profile): array => $this->serializeProfile($profile),
			$profiles,
		));

		return new DataResponse(['profiles' => $serialized], Http::STATUS_OK);
	}

	/**
	 * Create a new LLM profile for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $name Profile name (required)
	 * @param string $provider LLM Provider (required)
	 * @param string $apiKey Plain API key (required)
	 * @param ?string $model Model name or identifier
	 * @param int $connectTimeoutSeconds Connect timeout in seconds (default 30)
	 * @param int $readTimeoutSeconds Read timeout in seconds (default 60)
	 * @param int $maxTokens Max tokens limit (default 2048)
	 * @param bool $isActive Whether this profile is active
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_UNAUTHORIZED|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{profile: array{id: string, name: string, provider: string, apiKeyMasked: string, model: ?string, connectTimeoutSeconds: int, readTimeoutSeconds: int, maxTokens: int, isActive: bool, createdAt: string, updatedAt: ?string}}|array{message: string}, array{}>
	 *
	 * 201: Profile created
	 * 401: Current user is not logged in
	 * 422: Validation failure
	 * 500: Failed to create profile
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/llm-profiles')]
	public function create(
		string $name,
		string $provider,
		string $apiKey,
		?string $model = null,
		int $connectTimeoutSeconds = 30,
		int $readTimeoutSeconds = 60,
		int $maxTokens = 2048,
		bool $isActive = false,
	): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$provider = trim($provider);
		if ($provider === '') {
			return new DataResponse(['message' => 'Provider is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$apiKey = trim($apiKey);
		if ($apiKey === '') {
			return new DataResponse(['message' => 'API Key is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		try {
			$encryptedKey = $this->crypto->encrypt($apiKey);
		} catch (\Exception $e) {
			$this->logger->error('Failed to encrypt LLM API key', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to encrypt API key'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$profile = new LlmProfileEntity();
		$profile->setId(Uuid::v4());
		$profile->setOwner($userId);
		$profile->setName($name);
		$profile->setProvider($provider);
		$profile->setApiKey($encryptedKey);
		$profile->setModel($this->normalizeBlankToNull($model));
		$profile->setConnectTimeout(max(1, $connectTimeoutSeconds));
		$profile->setReadTimeout(max(1, $readTimeoutSeconds));
		$profile->setMaxTokens(max(1, $maxTokens));
		$profile->setIsActive(false);
		$profile->setCreatedAt(new DateTime());

		try {
			$created = $this->mapper->insert($profile);
			if ($isActive) {
				$this->mapper->setActive($created->getId(), $userId, true);
				$created->setIsActive(true);
			}
		} catch (\Exception $e) {
			$this->logger->error('Failed to create LLM profile', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to create profile'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['profile' => $this->serializeProfile($created, $apiKey)], Http::STATUS_CREATED);
	}

	/**
	 * Update an LLM profile for the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Profile ID
	 * @param string $name Profile name (required)
	 * @param string $provider LLM Provider (required)
	 * @param ?string $apiKey New plain API key (if omitted or unchanged/masked, existing key is kept)
	 * @param ?string $model Model name or identifier
	 * @param int $connectTimeoutSeconds Connect timeout in seconds
	 * @param int $readTimeoutSeconds Read timeout in seconds
	 * @param int $maxTokens Max tokens limit
	 * @param ?bool $isActive Whether this profile is active
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{profile: array{id: string, name: string, provider: string, apiKeyMasked: string, model: ?string, connectTimeoutSeconds: int, readTimeoutSeconds: int, maxTokens: int, isActive: bool, createdAt: string, updatedAt: ?string}}|array{message: string}, array{}>
	 *
	 * 200: Profile updated
	 * 401: Current user is not logged in
	 * 404: Profile not found
	 * 422: Validation failure
	 * 500: Failed to update profile
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/llm-profiles/{id}')]
	public function update(
		string $id,
		string $name,
		string $provider,
		?string $apiKey = null,
		?string $model = null,
		int $connectTimeoutSeconds = 30,
		int $readTimeoutSeconds = 60,
		int $maxTokens = 2048,
		?bool $isActive = null,
	): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$profile = $this->mapper->findByIdAndOwner($id, $userId);
		if ($profile === null) {
			return new DataResponse(['message' => 'Profile not found'], Http::STATUS_NOT_FOUND);
		}

		$name = trim($name);
		if ($name === '') {
			return new DataResponse(['message' => 'Name is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$provider = trim($provider);
		if ($provider === '') {
			return new DataResponse(['message' => 'Provider is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$trimmedApiKey = $apiKey !== null ? trim($apiKey) : null;
		$rawKeyForMasking = null;

		if ($trimmedApiKey !== null && $trimmedApiKey !== '' && !str_contains($trimmedApiKey, '•')) {
			try {
				$encryptedKey = $this->crypto->encrypt($trimmedApiKey);
				$profile->setApiKey($encryptedKey);
				$rawKeyForMasking = $trimmedApiKey;
			} catch (\Exception $e) {
				$this->logger->error('Failed to encrypt new LLM API key', ['exception' => $e]);
				return new DataResponse(['message' => 'Failed to encrypt API key'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		}

		$profile->setName($name);
		$profile->setProvider($provider);
		$profile->setModel($this->normalizeBlankToNull($model));
		$profile->setConnectTimeout(max(1, $connectTimeoutSeconds));
		$profile->setReadTimeout(max(1, $readTimeoutSeconds));
		$profile->setMaxTokens(max(1, $maxTokens));
		$profile->setUpdatedAt(new DateTime());

		try {
			$updated = $this->mapper->update($profile);
			if ($isActive !== null) {
				$this->mapper->setActive($updated->getId(), $userId, $isActive);
				$updated->setIsActive($isActive);
			}
		} catch (\Exception $e) {
			$this->logger->error('Failed to update LLM profile', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to update profile'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['profile' => $this->serializeProfile($updated, $rawKeyForMasking)], Http::STATUS_OK);
	}

	/**
	 * Set active status for an LLM profile
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Profile ID
	 * @param bool $active Active status (default true)
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{}|array{message: string}, array{}>
	 *
	 * 200: Profile activation status changed
	 * 401: Current user is not logged in
	 * 404: Profile not found
	 * 500: Failed to activate profile
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/llm-profiles/{id}/activate')]
	public function activate(string $id, bool $active = true): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$profile = $this->mapper->findByIdAndOwner($id, $userId);
		if ($profile === null) {
			return new DataResponse(['message' => 'Profile not found'], Http::STATUS_NOT_FOUND);
		}

		try {
			$this->mapper->setActive($id, $userId, $active);
		} catch (\Exception $e) {
			$this->logger->error('Failed to change LLM profile activation', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to update profile'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * Delete an LLM profile
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id Profile ID
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{}|array{message: string}, array{}>
	 *
	 * 200: Profile deleted
	 * 401: Current user is not logged in
	 * 404: Profile not found
	 * 500: Failed to delete profile
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/llm-profiles/{id}')]
	public function destroy(string $id): DataResponse {
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$profile = $this->mapper->findByIdAndOwner($id, $userId);
		if ($profile === null) {
			return new DataResponse(['message' => 'Profile not found'], Http::STATUS_NOT_FOUND);
		}

		try {
			$this->mapper->delete($profile);
		} catch (\Exception $e) {
			$this->logger->error('Failed to delete LLM profile', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to delete profile'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	private function normalizeBlankToNull(?string $value): ?string {
		if ($value === null || trim($value) === '') {
			return null;
		}
		return trim($value);
	}

	/**
	 * @return array{id: string, name: string, provider: string, apiKeyMasked: string, model: ?string, connectTimeoutSeconds: int, readTimeoutSeconds: int, maxTokens: int, isActive: bool, createdAt: string, updatedAt: ?string}
	 */
	private function serializeProfile(LlmProfileEntity $profile, ?string $knownRawKey = null): array {
		$maskedKey = '••••••••';
		$encryptedKey = $profile->getApiKey();
		if ($knownRawKey !== null) {
			$maskedKey = $this->maskKey($knownRawKey);
		} elseif ($encryptedKey !== null) {
			try {
				$decrypted = $this->crypto->decrypt($encryptedKey);
				$maskedKey = $this->maskKey($decrypted);
			} catch (\Exception $e) {
				$this->logger->warning('Could not decrypt LLM API key for masking', ['exception' => $e]);
			}
		}

		return [
			'id' => $profile->getId(),
			'name' => $profile->getName() ?? '',
			'provider' => $profile->getProvider() ?? '',
			'apiKeyMasked' => $maskedKey,
			'model' => $profile->getModel(),
			'connectTimeoutSeconds' => $profile->getConnectTimeout() ?? 30,
			'readTimeoutSeconds' => $profile->getReadTimeout() ?? 60,
			'maxTokens' => $profile->getMaxTokens() ?? 2048,
			'isActive' => $profile->getIsActive() ?? false,
			'createdAt' => $profile->getCreatedAt()?->format(DateTimeInterface::ATOM) ?? '',
			'updatedAt' => $profile->getUpdatedAt()?->format(DateTimeInterface::ATOM),
		];
	}

	private function maskKey(string $rawKey): string {
		$len = strlen($rawKey);
		if ($len <= 4) {
			return str_repeat('•', 8);
		}
		$last4 = substr($rawKey, -4);
		return '••••••••' . $last4;
	}
}
