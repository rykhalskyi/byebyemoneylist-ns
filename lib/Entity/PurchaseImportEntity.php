<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Entity;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * One uploaded purchase list awaiting review.
 *
 * @method string getId()
 * @method void setId(string $id)
 * @method ?string getOwner()
 * @method void setOwner(string $owner)
 * @method ?string getClientListId()
 * @method void setClientListId(string $clientListId)
 * @method ?string getClientId()
 * @method void setClientId(?string $clientId)
 * @method ?string getSchemaVersion()
 * @method void setSchemaVersion(string $schemaVersion)
 * @method ?string getName()
 * @method void setName(string $name)
 * @method ?string getStatus()
 * @method void setStatus(string $status)
 * @method ?string getStoreName()
 * @method void setStoreName(?string $storeName)
 * @method ?string getStoreAddress()
 * @method void setStoreAddress(?string $storeAddress)
 * @method ?string getStoreReceiptName()
 * @method void setStoreReceiptName(?string $storeReceiptName)
 * @method ?string getCurrency()
 * @method void setCurrency(?string $currency)
 * @method ?float getFinalTotal()
 * @method void setFinalTotal(?float $finalTotal)
 * @method ?DateTime getListCreatedAt()
 * @method void setListCreatedAt(?DateTime $listCreatedAt)
 * @method ?DateTime getPurchaseDate()
 * @method void setPurchaseDate(?DateTime $purchaseDate)
 * @method ?string getRawJson()
 * @method void setRawJson(string $rawJson)
 * @method ?string getMappedStoreId()
 * @method void setMappedStoreId(?string $mappedStoreId)
 * @method ?string getStoreAction()
 * @method void setStoreAction(string $storeAction)
 * @method ?string getSuggestedStoreId()
 * @method void setSuggestedStoreId(?string $suggestedStoreId)
 * @method ?string getSuggestedStoreName()
 * @method void setSuggestedStoreName(?string $suggestedStoreName)
 * @method ?string getStoreMatchSource()
 * @method void setStoreMatchSource(?string $storeMatchSource)
 * @method ?float getStoreConfidence()
 * @method void setStoreConfidence(?float $storeConfidence)
 * @method ?string getApprovedListId()
 * @method void setApprovedListId(?string $approvedListId)
 * @method ?DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 * @method ?DateTime getUpdatedAt()
 * @method void setUpdatedAt(DateTime $updatedAt)
 * @method ?DateTime getApprovedAt()
 * @method void setApprovedAt(?DateTime $approvedAt)
 * @psalm-suppress PropertyNotSetInConstructor, PossiblyUnusedProperty
 */
class PurchaseImportEntity extends Entity {
	public const STATUS_PENDING = 'pending';
	public const STATUS_IN_REVIEW = 'in_review';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_DISCARDED = 'discarded';

	public const ACTION_PENDING = 'pending';
	public const ACTION_MAP_EXISTING = 'map_existing';
	public const ACTION_CREATE_NEW = 'create_new';
	public const ACTION_SKIP = 'skip';

	protected ?string $owner = null;
	protected ?string $clientListId = null;
	protected ?string $clientId = null;
	protected ?string $schemaVersion = null;
	protected ?string $name = null;
	protected ?string $status = self::STATUS_PENDING;
	protected ?string $storeName = null;
	protected ?string $storeAddress = null;
	protected ?string $storeReceiptName = null;
	protected ?string $currency = null;
	protected ?float $finalTotal = null;
	protected ?DateTime $listCreatedAt = null;
	protected ?DateTime $purchaseDate = null;
	protected ?string $rawJson = null;
	protected ?string $mappedStoreId = null;
	protected ?string $storeAction = self::ACTION_PENDING;
	protected ?string $suggestedStoreId = null;
	protected ?string $suggestedStoreName = null;
	protected ?string $storeMatchSource = null;
	protected ?float $storeConfidence = null;
	protected ?string $approvedListId = null;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $updatedAt = null;
	protected ?DateTime $approvedAt = null;

	public function __construct() {
		$this->addType('id', Types::STRING);
		$this->addType('finalTotal', Types::DECIMAL);
		$this->addType('listCreatedAt', Types::DATETIME);
		$this->addType('purchaseDate', Types::DATETIME);
		$this->addType('storeConfidence', Types::DECIMAL);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
		$this->addType('approvedAt', Types::DATETIME);
	}
}
