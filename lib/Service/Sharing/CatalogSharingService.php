<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service\Sharing;

use DateTime;
use DateTimeZone;
use OCA\ByeByeMoneyList\Db\CatalogShareMapper;
use OCA\ByeByeMoneyList\Entity\CatalogShareEntity;
use OCA\ByeByeMoneyList\Util\Uuid;

/**
 * Manages per-item catalog grants published from one user to another.
 *
 * @psalm-suppress UnusedClass, PossiblyUnusedMethod
 */
class CatalogSharingService {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private CatalogShareMapper $mapper,
	) {
	}

	/**
	 * Publish an item to another user, idempotently. Re-activates a previously
	 * revoked grant instead of inserting a duplicate.
	 */
	public function publish(string $itemType, string $itemId, string $fromUid, string $toUid): void {
		$now = new DateTime('now', new DateTimeZone('UTC'));
		$existing = $this->mapper->findByItemAndUser($itemType, $itemId, $toUid);

		if ($existing === null) {
			$share = new CatalogShareEntity();
			$share->setId(Uuid::v4());
			$share->setItemType($itemType);
			$share->setItemId($itemId);
			$share->setOwner($fromUid);
			$share->setSharedWith($toUid);
			$share->setStatus(CatalogShareEntity::STATUS_ACTIVE);
			$share->setCreatedAt($now);
			$share->setUpdatedAt($now);
			$this->mapper->insert($share);
			return;
		}

		if ($existing->getStatus() !== CatalogShareEntity::STATUS_ACTIVE) {
			$existing->setStatus(CatalogShareEntity::STATUS_ACTIVE);
			$existing->setUpdatedAt($now);
			$this->mapper->update($existing);
		}
	}

	/**
	 * Revoke every active grant of an item (called when the item is deleted).
	 */
	public function revokeItem(string $itemType, string $itemId): void {
		$this->mapper->revokeByItem($itemType, $itemId);
	}

	/**
	 * Owners who published items of the given type to the user.
	 *
	 * @return list<string>
	 */
	public function visibleOwnersForCatalog(string $userId, string $itemType): array {
		return $this->mapper->findActiveOwnerIdsByRecipient($userId, $itemType);
	}
}
