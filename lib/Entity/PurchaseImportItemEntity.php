<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Entity;

use DateTime;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * A raw line uploaded with a purchase import, plus its mapping state.
 *
 * @method string getId()
 * @method void setId(string $id)
 * @method ?string getImportId()
 * @method void setImportId(string $importId)
 * @method ?string getOwner()
 * @method void setOwner(string $owner)
 * @method ?int getPosition()
 * @method void setPosition(int $position)
 * @method ?string getLineId()
 * @method void setLineId(?string $lineId)
 * @method ?string getRawName()
 * @method void setRawName(string $rawName)
 * @method ?float getQuantity()
 * @method void setQuantity(float $quantity)
 * @method ?float getUnitPrice()
 * @method void setUnitPrice(?float $unitPrice)
 * @method ?float getDiscount()
 * @method void setDiscount(?float $discount)
 * @method ?bool getIsCoupon()
 * @method void setIsCoupon(bool $isCoupon)
 * @method ?string getBarcode()
 * @method void setBarcode(?string $barcode)
 * @method ?string getCategoryHint()
 * @method void setCategoryHint(?string $categoryHint)
 * @method ?string getAction()
 * @method void setAction(string $action)
 * @method ?string getMappedProductId()
 * @method void setMappedProductId(?string $mappedProductId)
 * @method ?string getMappedCategoryId()
 * @method void setMappedCategoryId(?string $mappedCategoryId)
 * @method ?string getNewProductName()
 * @method void setNewProductName(?string $newProductName)
 * @method ?string getSuggestedProductId()
 * @method void setSuggestedProductId(?string $suggestedProductId)
 * @method ?string getSuggestedProductName()
 * @method void setSuggestedProductName(?string $suggestedProductName)
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
class PurchaseImportItemEntity extends Entity {
	protected ?string $importId = null;
	protected ?string $owner = null;
	protected ?int $position = 0;
	protected ?string $lineId = null;
	protected ?string $rawName = null;
	protected ?float $quantity = 1.0;
	protected ?float $unitPrice = null;
	protected ?float $discount = null;
	protected ?bool $isCoupon = false;
	protected ?string $barcode = null;
	protected ?string $categoryHint = null;
	protected ?string $action = PurchaseImportEntity::ACTION_PENDING;
	protected ?string $mappedProductId = null;
	protected ?string $mappedCategoryId = null;
	protected ?string $newProductName = null;
	protected ?string $suggestedProductId = null;
	protected ?string $suggestedProductName = null;
	protected ?string $suggestedCategoryId = null;
	protected ?string $matchSource = null;
	protected ?float $confidence = null;
	protected ?DateTime $createdAt = null;
	protected ?DateTime $updatedAt = null;

	public function __construct() {
		$this->addType('id', Types::STRING);
		$this->addType('position', Types::INTEGER);
		$this->addType('quantity', Types::DECIMAL);
		$this->addType('unitPrice', Types::DECIMAL);
		$this->addType('discount', Types::DECIMAL);
		$this->addType('isCoupon', Types::BOOLEAN);
		$this->addType('confidence', Types::DECIMAL);
		$this->addType('createdAt', Types::DATETIME);
		$this->addType('updatedAt', Types::DATETIME);
	}
}
