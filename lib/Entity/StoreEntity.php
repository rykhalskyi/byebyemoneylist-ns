<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Entity;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getId()
 * @method void setId(string $id)
 * @method ?string getAddress()
 * @method void setAddress(?string $address)
 * @psalm-suppress PropertyNotSetInConstructor
 */
class StoreEntity extends Entity {
	protected ?string $owner = null;
	protected ?string $name = null;
	protected ?string $address = null;

	public function __construct() {
		$this->addType('id', Types::STRING);
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

	/** @psalm-suppress PossiblyUnusedMethod */
	public function setName(string $name): void {
		$this->name = $name;
		$this->markFieldUpdated('name');
	}

	public function getAddress(): ?string {
		return $this->address;
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function setAddress(?string $address): void {
		$this->address = $address;
		$this->markFieldUpdated('address');
	}
}
