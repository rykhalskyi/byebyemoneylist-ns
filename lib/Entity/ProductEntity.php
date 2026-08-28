<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Entity;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getId()
 * @method void setId(string $id)
 * @psalm-suppress PropertyNotSetInConstructor
 */
class ProductEntity extends Entity {
	protected ?string $owner = null;
	protected ?string $name = null;
	protected ?string $barcode = null;
	protected ?string $categoryId = null;
	protected ?string $status = null;
	protected ?string $picturePath = null;
	protected ?bool $isSubscription = false;
	protected ?bool $isFavorite = false;
	protected ?bool $isIncome = false;

	public function __construct() {
		$this->addType('id', Types::STRING);
		$this->addType('isSubscription', Types::BOOLEAN);
		$this->addType('isFavorite', Types::BOOLEAN);
		$this->addType('isIncome', Types::BOOLEAN);
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getOwner(): ?string {
		return $this->owner;
	}

	/** @psalm-suppress PossiblyUnusedMethod */
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

	public function getBarcode(): ?string {
		return $this->barcode;
	}

	public function setBarcode(?string $barcode): void {
		$this->barcode = $barcode;
		$this->markFieldUpdated('barcode');
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

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getPicturePath(): ?string {
		return $this->picturePath;
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function setPicturePath(?string $picturePath): void {
		$this->picturePath = $picturePath;
		$this->markFieldUpdated('picturePath');
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getIsSubscription(): ?bool {
		return $this->isSubscription;
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function setIsSubscription(bool $isSubscription): void {
		$this->isSubscription = $isSubscription;
		$this->markFieldUpdated('isSubscription');
	}

	public function getIsFavorite(): ?bool {
		return $this->isFavorite;
	}

	public function setIsFavorite(bool $isFavorite): void {
		$this->isFavorite = $isFavorite;
		$this->markFieldUpdated('isFavorite');
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getIsIncome(): ?bool {
		return $this->isIncome;
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function setIsIncome(bool $isIncome): void {
		$this->isIncome = $isIncome;
		$this->markFieldUpdated('isIncome');
	}
}
