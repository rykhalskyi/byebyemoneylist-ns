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
class ProductAliasEntity extends Entity {
	protected ?string $owner = null;
	protected ?string $productId = null;
	protected ?string $aliasName = null;
	protected ?string $storeId = null;

	public function __construct() {
		$this->addType('id', Types::STRING);
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getOwner(): ?string {
		return $this->owner;
	}

	public function setOwner(string $owner): void {
		$this->owner = $owner;
		$this->markFieldUpdated('owner');
	}

	public function getProductId(): ?string {
		return $this->productId;
	}

	public function setProductId(string $productId): void {
		$this->productId = $productId;
		$this->markFieldUpdated('productId');
	}

	public function getAliasName(): ?string {
		return $this->aliasName;
	}

	public function setAliasName(string $aliasName): void {
		$this->aliasName = $aliasName;
		$this->markFieldUpdated('aliasName');
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getStoreId(): ?string {
		return $this->storeId;
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function setStoreId(?string $storeId): void {
		$this->storeId = $storeId;
		$this->markFieldUpdated('storeId');
	}
}
