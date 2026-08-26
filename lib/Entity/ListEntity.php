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
class ListEntity extends Entity {
	protected ?string $owner = null;
	protected ?string $name = null;
	protected ?string $storeId = null;
	protected ?string $categoryId = null;
	protected ?string $status = null;
	protected ?float $finalTotal = null;
	protected ?DateTime $createdAt = null;

	public function __construct() {
		$this->addType('id', Types::STRING);
		$this->addType('finalTotal', Types::DECIMAL);
		$this->addType('createdAt', Types::DATETIME);
	}

	/** @psalm-suppress PossiblyUnusedMethod */
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

	public function getStoreId(): ?string {
		return $this->storeId;
	}

	public function setStoreId(?string $storeId): void {
		$this->storeId = $storeId;
		$this->markFieldUpdated('storeId');
	}

	public function getCategoryId(): ?string {
		return $this->categoryId;
	}

	public function setCategoryId(?string $categoryId): void {
		$this->categoryId = $categoryId;
		$this->markFieldUpdated('categoryId');
	}

	public function getStatus(): ?string {
		return $this->status;
	}

	public function setStatus(string $status): void {
		$this->status = $status;
		$this->markFieldUpdated('status');
	}

	public function getFinalTotal(): ?float {
		return $this->finalTotal;
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function setFinalTotal(?float $finalTotal): void {
		$this->finalTotal = $finalTotal;
		$this->markFieldUpdated('finalTotal');
	}

	public function getCreatedAt(): ?DateTime {
		return $this->createdAt;
	}

	public function setCreatedAt(DateTime $createdAt): void {
		$this->createdAt = $createdAt;
		$this->markFieldUpdated('createdAt');
	}
}
