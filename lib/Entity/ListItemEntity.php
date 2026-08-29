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
class ListItemEntity extends Entity {
	protected ?string $owner = null;
	protected ?string $listId = null;
	protected ?string $productId = null;
	protected ?float $price = null;
	protected ?float $quantity = null;
	protected ?bool $isChecked = false;
	protected ?string $status = null;
	protected ?DateTime $createdAt = null;

	public function __construct() {
		$this->addType('id', Types::STRING);
		$this->addType('price', Types::DECIMAL);
		$this->addType('quantity', Types::DECIMAL);
		$this->addType('isChecked', Types::BOOLEAN);
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

	public function getListId(): ?string {
		return $this->listId;
	}

	public function setListId(string $listId): void {
		$this->listId = $listId;
		$this->markFieldUpdated('listId');
	}

	public function getProductId(): ?string {
		return $this->productId;
	}

	public function setProductId(string $productId): void {
		$this->productId = $productId;
		$this->markFieldUpdated('productId');
	}

	public function getPrice(): ?float {
		return $this->price;
	}

	public function setPrice(?float $price): void {
		$this->price = $price;
		$this->markFieldUpdated('price');
	}

	public function getQuantity(): ?float {
		return $this->quantity;
	}

	public function setQuantity(float $quantity): void {
		$this->quantity = $quantity;
		$this->markFieldUpdated('quantity');
	}

	public function getIsChecked(): ?bool {
		return $this->isChecked;
	}

	public function setIsChecked(bool $isChecked): void {
		$this->isChecked = $isChecked;
		$this->markFieldUpdated('isChecked');
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getStatus(): ?string {
		return $this->status;
	}

	public function setStatus(string $status): void {
		$this->status = $status;
		$this->markFieldUpdated('status');
	}

	public function getCreatedAt(): ?DateTime {
		return $this->createdAt;
	}

	public function setCreatedAt(DateTime $createdAt): void {
		$this->createdAt = $createdAt;
		$this->markFieldUpdated('createdAt');
	}
}
