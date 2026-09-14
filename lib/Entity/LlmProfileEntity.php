<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Entity;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getId()
 * @method void setId(string $id)
 * @psalm-suppress PropertyNotSetInConstructor
 */
class LlmProfileEntity extends Entity {
	protected ?string $owner = null;
	protected ?string $name = null;
	protected ?string $provider = null;
	protected ?string $apiKey = null;
	protected ?string $model = null;
	protected ?int $connectTimeout = 30;
	protected ?int $readTimeout = 60;
	protected ?int $maxTokens = 2048;
	protected ?bool $isActive = false;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $updatedAt = null;

	public function __construct() {
		$this->addType('id', Types::STRING);
		$this->addType('connectTimeout', Types::INTEGER);
		$this->addType('readTimeout', Types::INTEGER);
		$this->addType('maxTokens', Types::INTEGER);
		$this->addType('isActive', Types::BOOLEAN);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
	}

	public function getOwner(): ?string {
		return $this->owner;
	}

	public function setOwner(string $owner): void {
		$this->owner = $owner;
		$this->markFieldUpdated('owner');
	}

	public function getName(): ?string {
		return $this->name;
	}

	public function setName(string $name): void {
		$this->name = $name;
		$this->markFieldUpdated('name');
	}

	public function getProvider(): ?string {
		return $this->provider;
	}

	public function setProvider(string $provider): void {
		$this->provider = $provider;
		$this->markFieldUpdated('provider');
	}

	public function getApiKey(): ?string {
		return $this->apiKey;
	}

	public function setApiKey(string $apiKey): void {
		$this->apiKey = $apiKey;
		$this->markFieldUpdated('apiKey');
	}

	public function getModel(): ?string {
		return $this->model;
	}

	public function setModel(?string $model): void {
		$this->model = $model;
		$this->markFieldUpdated('model');
	}

	public function getConnectTimeout(): ?int {
		return $this->connectTimeout;
	}

	public function setConnectTimeout(int $connectTimeout): void {
		$this->connectTimeout = $connectTimeout;
		$this->markFieldUpdated('connectTimeout');
	}

	public function getReadTimeout(): ?int {
		return $this->readTimeout;
	}

	public function setReadTimeout(int $readTimeout): void {
		$this->readTimeout = $readTimeout;
		$this->markFieldUpdated('readTimeout');
	}

	public function getMaxTokens(): ?int {
		return $this->maxTokens;
	}

	public function setMaxTokens(int $maxTokens): void {
		$this->maxTokens = $maxTokens;
		$this->markFieldUpdated('maxTokens');
	}

	public function getIsActive(): ?bool {
		return $this->isActive;
	}

	public function setIsActive(bool $isActive): void {
		$this->isActive = $isActive;
		$this->markFieldUpdated('isActive');
	}

	public function getCreatedAt(): ?DateTime {
		return $this->createdAt;
	}

	public function setCreatedAt(DateTime $createdAt): void {
		$this->createdAt = $createdAt;
		$this->markFieldUpdated('createdAt');
	}

	public function getUpdatedAt(): ?DateTime {
		return $this->updatedAt;
	}

	public function setUpdatedAt(?DateTime $updatedAt): void {
		$this->updatedAt = $updatedAt;
		$this->markFieldUpdated('updatedAt');
	}
}
