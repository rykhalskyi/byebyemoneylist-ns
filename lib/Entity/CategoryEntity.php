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
class CategoryEntity extends Entity {
	protected ?string $owner = null;
	protected ?string $name = null;
	protected ?string $color = null;
	protected ?string $emoji = null;
	protected ?string $parentId = null;
	protected ?bool $income = false;
	protected ?string $status = 'confirmed';

	public function __construct() {
		$this->addType('id', Types::STRING);
		$this->addType('income', Types::BOOLEAN);
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

	public function getColor(): ?string {
		return $this->color;
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function setColor(?string $color): void {
		$this->color = $color;
		$this->markFieldUpdated('color');
	}

	public function getEmoji(): ?string {
		return $this->emoji;
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function setEmoji(?string $emoji): void {
		$this->emoji = $emoji;
		$this->markFieldUpdated('emoji');
	}

	public function getParentId(): ?string {
		return $this->parentId;
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function setParentId(?string $parentId): void {
		$this->parentId = $parentId;
		$this->markFieldUpdated('parentId');
	}

	public function getIncome(): ?bool {
		return $this->income;
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function setIncome(bool $income): void {
		$this->income = $income;
		$this->markFieldUpdated('income');
	}

	public function getStatus(): ?string {
		return $this->status;
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function setStatus(string $status): void {
		$this->status = $status;
		$this->markFieldUpdated('status');
	}
}
