<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Entity;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * A single list share (one list, one guest).
 *
 * @method string getId()
 * @method void setId(string $id)
 * @psalm-suppress PropertyNotSetInConstructor
 */
class ListShareEntity extends Entity {
	public const MODE_READONLY = 'readonly';
	public const MODE_READWRITE = 'readwrite';
	public const STATUS_ACTIVE = 'active';
	public const STATUS_REVOKED = 'revoked';

	protected ?string $listId = null;
	protected ?string $owner = null;
	protected ?string $sharedWith = null;
	protected ?string $mode = self::MODE_READONLY;
	protected ?string $status = self::STATUS_ACTIVE;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $updatedAt = null;

	public function __construct() {
		$this->addType('id', Types::STRING);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
	}

	public function getListId(): ?string {
		return $this->listId;
	}

	public function setListId(string $listId): void {
		$this->listId = $listId;
		$this->markFieldUpdated('listId');
	}

	public function getOwner(): ?string {
		return $this->owner;
	}

	public function setOwner(string $owner): void {
		$this->owner = $owner;
		$this->markFieldUpdated('owner');
	}

	public function getSharedWith(): ?string {
		return $this->sharedWith;
	}

	public function setSharedWith(string $sharedWith): void {
		$this->sharedWith = $sharedWith;
		$this->markFieldUpdated('sharedWith');
	}

	public function getMode(): ?string {
		return $this->mode;
	}

	public function setMode(string $mode): void {
		$this->mode = $mode;
		$this->markFieldUpdated('mode');
	}

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

	public function getUpdatedAt(): ?DateTime {
		return $this->updatedAt;
	}

	public function setUpdatedAt(DateTime $updatedAt): void {
		$this->updatedAt = $updatedAt;
		$this->markFieldUpdated('updatedAt');
	}
}
