<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Entity;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * A catalog item grant published from one user to another (guest → list owner).
 *
 * @method string getId()
 * @method void setId(string $id)
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedMethod
 */
class CatalogShareEntity extends Entity {
	public const TYPE_PRODUCT = 'product';
	public const TYPE_CATEGORY = 'category';
	public const TYPE_STORE = 'store';
	public const STATUS_ACTIVE = 'active';
	public const STATUS_REVOKED = 'revoked';

	protected ?string $itemType = null;
	protected ?string $itemId = null;
	protected ?string $owner = null;
	protected ?string $sharedWith = null;
	protected ?string $status = self::STATUS_ACTIVE;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $updatedAt = null;

	public function __construct() {
		$this->addType('id', Types::STRING);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getItemType(): ?string {
		return $this->itemType;
	}

	public function setItemType(string $itemType): void {
		$this->itemType = $itemType;
		$this->markFieldUpdated('itemType');
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getItemId(): ?string {
		return $this->itemId;
	}

	public function setItemId(string $itemId): void {
		$this->itemId = $itemId;
		$this->markFieldUpdated('itemId');
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
	public function getSharedWith(): ?string {
		return $this->sharedWith;
	}

	public function setSharedWith(string $sharedWith): void {
		$this->sharedWith = $sharedWith;
		$this->markFieldUpdated('sharedWith');
	}

	public function getStatus(): ?string {
		return $this->status;
	}

	public function setStatus(string $status): void {
		$this->status = $status;
		$this->markFieldUpdated('status');
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getCreatedAt(): ?DateTime {
		return $this->createdAt;
	}

	public function setCreatedAt(DateTime $createdAt): void {
		$this->createdAt = $createdAt;
		$this->markFieldUpdated('createdAt');
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getUpdatedAt(): ?DateTime {
		return $this->updatedAt;
	}

	public function setUpdatedAt(DateTime $updatedAt): void {
		$this->updatedAt = $updatedAt;
		$this->markFieldUpdated('updatedAt');
	}
}
