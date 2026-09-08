<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Entity;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * A price the user recorded for a product on a given date (optionally at a store).
 *
 * @method string getId()
 * @method void setId(string $id)
 * @psalm-suppress PropertyNotSetInConstructor
 */
class ProductPriceEntity extends Entity {
	protected ?string $owner = null;
	protected ?string $productId = null;
	protected ?string $storeId = null;
	protected ?float $value = null;
	protected ?DateTime $priceDate = null;
	protected ?DateTime $createdAt = null;

	public function __construct() {
		$this->addType('id', Types::STRING);
		$this->addType('value', Types::DECIMAL);
		$this->addType('priceDate', Types::DATETIME);
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

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getProductId(): ?string {
		return $this->productId;
	}

	public function setProductId(string $productId): void {
		$this->productId = $productId;
		$this->markFieldUpdated('productId');
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getStoreId(): ?string {
		return $this->storeId;
	}

	public function setStoreId(?string $storeId): void {
		$this->storeId = $storeId;
		$this->markFieldUpdated('storeId');
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getValue(): ?float {
		return $this->value;
	}

	public function setValue(float $value): void {
		$this->value = $value;
		$this->markFieldUpdated('value');
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getPriceDate(): ?DateTime {
		return $this->priceDate;
	}

	public function setPriceDate(DateTime $priceDate): void {
		$this->priceDate = $priceDate;
		$this->markFieldUpdated('priceDate');
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getCreatedAt(): ?DateTime {
		return $this->createdAt;
	}

	public function setCreatedAt(DateTime $createdAt): void {
		$this->createdAt = $createdAt;
		$this->markFieldUpdated('createdAt');
	}
}
