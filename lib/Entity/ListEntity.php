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
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedProperty
 */
class ListEntity extends Entity {
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
	}
}
