<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Entity;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getId()
 * @method void setId(string $id)
 * @method ?string getOwner()
 * @method void setOwner(string $owner)
 * @method ?string getName()
 * @method void setName(string $name)
 * @method ?string getStoreId()
 * @method void setStoreId(?string $storeId)
 * @method ?string getCategoryId()
 * @method void setCategoryId(?string $categoryId)
 * @method ?string getStatus()
 * @method void setStatus(string $status)
 * @method ?float getFinalTotal()
 * @method void setFinalTotal(?float $finalTotal)
 * @method ?DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method ?DateTime getPurchaseDate()
 * @method void setPurchaseDate(?DateTime $purchaseDate)
 * @method ?bool getIsFinished()
 * @method void setIsFinished(bool $isFinished)
 * @method ?int getPosition()
 * @method void setPosition(int $position)
 * @method ?DateTime getUpdatedAt()
 * @method void setUpdatedAt(DateTime $updatedAt)
 * @method ?bool getIsSubscription()
 * @method void setIsSubscription(bool $isSubscription)
 * @method ?bool getIsIncome()
 * @method void setIsIncome(bool $isIncome)
 * @method ?bool getIsRecurring()
 * @method void setIsRecurring(bool $isRecurring)
 * @method ?string getRecurringPeriod()
 * @method void setRecurringPeriod(string $recurringPeriod)
 * @method ?bool getIsForwardEmpty()
 * @method void setIsForwardEmpty(bool $isForwardEmpty)
 * @method ?string getReceiptPath()
 * @method void setReceiptPath(?string $receiptPath)
 * @method ?string getKind()
 * @method void setKind(?string $kind)
 * @method ?bool getIsActive()
 * @method void setIsActive(bool $isActive)
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedProperty
 */
class ListEntity extends Entity {
	public const KIND_NEED_TO_BUY = 'NEED_TO_BUY';
	public const KIND_PURCHASE = 'PURCHASE';
	public const KIND_INCOME = 'INCOME';
	public const KIND_SUBSCRIPTION = 'SUBSCRIPTION';

	protected ?string $owner = null;
	protected ?string $name = null;
	protected ?string $storeId = null;
	protected ?string $categoryId = null;
	protected ?string $status = null;
	protected ?float $finalTotal = null;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $purchaseDate = null;
	protected ?bool $isFinished = false;
	protected ?int $position = 0;
	protected ?DateTime $updatedAt = null;
	protected ?bool $isSubscription = false;
	protected ?bool $isIncome = false;
	protected ?bool $isRecurring = false;
	protected ?string $recurringPeriod = 'MONTH';
	protected ?bool $isForwardEmpty = true;
	protected ?string $receiptPath = null;
	protected ?string $kind = null;
	protected ?bool $isActive = false;

	public function __construct() {
		$this->addType('id', Types::STRING);
		$this->addType('finalTotal', Types::DECIMAL);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('purchaseDate', Types::DATETIME);
		$this->addType('isFinished', Types::BOOLEAN);
		$this->addType('position', Types::INTEGER);
		$this->addType('updatedAt', Types::DATETIME);
		$this->addType('isSubscription', Types::BOOLEAN);
		$this->addType('isIncome', Types::BOOLEAN);
		$this->addType('isRecurring', Types::BOOLEAN);
		$this->addType('recurringPeriod', Types::STRING);
		$this->addType('isForwardEmpty', Types::BOOLEAN);
		$this->addType('receiptPath', Types::STRING);
		$this->addType('kind', Types::STRING);
		$this->addType('isActive', Types::BOOLEAN);
	}

	/**
	 * Resolve the explicit kind, falling back to the legacy flags when it is unset.
	 *
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	public function resolveKind(): string {
		if ($this->kind !== null && $this->kind !== '') {
			return $this->kind;
		}
		if ($this->isIncome === true) {
			return self::KIND_INCOME;
		}
		if ($this->isSubscription === true) {
			return self::KIND_SUBSCRIPTION;
		}
		if ($this->isFinished === true) {
			return self::KIND_PURCHASE;
		}
		return self::KIND_NEED_TO_BUY;
	}
}
