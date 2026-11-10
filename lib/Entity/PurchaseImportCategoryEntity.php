<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Entity;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * A raw list category uploaded with a purchase import, plus its mapping state.
 *
 * @method string getId()
 * @method void setId(string $id)
 * @method ?string getImportId()
 * @method void setImportId(string $importId)
 * @method ?string getOwner()
 * @method void setOwner(string $owner)
 * @method ?string getRawName()
 * @method void setRawName(string $rawName)
 * @method ?bool getIsIncome()
 * @method void setIsIncome(bool $isIncome)
 * @method ?string getAction()
 * @method void setAction(string $action)
 * @method ?string getMappedCategoryId()
 * @method void setMappedCategoryId(?string $mappedCategoryId)
 * @method ?string getSuggestedCategoryId()
 * @method void setSuggestedCategoryId(?string $suggestedCategoryId)
 * @method ?string getMatchSource()
 * @method void setMatchSource(?string $matchSource)
 * @method ?float getConfidence()
 * @method void setConfidence(?float $confidence)
 * @method ?DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method ?DateTime getUpdatedAt()
 * @method void setUpdatedAt(DateTime $updatedAt)
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedProperty
 */
class PurchaseImportCategoryEntity extends Entity {
	protected ?string $importId = null;
	protected ?string $owner = null;
	protected ?string $rawName = null;
	protected ?bool $isIncome = false;
	protected ?string $action = PurchaseImportEntity::ACTION_PENDING;
	protected ?string $mappedCategoryId = null;
	protected ?string $suggestedCategoryId = null;
	protected ?string $matchSource = null;
	protected ?float $confidence = null;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $updatedAt = null;

	public function __construct() {
		$this->addType('id', Types::STRING);
		$this->addType('isIncome', Types::BOOLEAN);
		$this->addType('confidence', Types::DECIMAL);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
	}
}
