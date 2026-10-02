<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Service\Sharing;

use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\ListShareMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Entity\ListShareEntity;

/**
 * Resolves list access for a user: owned lists plus lists shared with them.
 */
class ListAccessService {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private ListMapper $listMapper,
		private ListShareMapper $shareMapper,
	) {
	}

	/**
	 * A list the user owns.
	 *
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	public function isOwner(string $listId, string $userId): bool {
		return $this->listMapper->findByIdAndOwner($listId, $userId) !== null;
	}

	/**
	 * A list the user may read: owned or actively shared (either mode).
	 *
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	public function findReadable(string $listId, string $userId): ?ListEntity {
		$list = $this->listMapper->findById($listId);
		if ($list === null) {
			return null;
		}
		if ($list->getOwner() === $userId) {
			return $list;
		}

		return $this->shareMapper->findActiveByListAndUser($listId, $userId) !== null ? $list : null;
	}

	/**
	 * A list the user may write to: owned or actively shared read/write.
	 *
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	public function findWritable(string $listId, string $userId): ?ListEntity {
		$list = $this->listMapper->findById($listId);
		if ($list === null) {
			return null;
		}
		if ($list->getOwner() === $userId) {
			return $list;
		}

		$share = $this->shareMapper->findActiveByListAndUser($listId, $userId);
		if ($share !== null && $share->getMode() === ListShareEntity::MODE_READWRITE) {
			return $list;
		}

		return null;
	}

	/**
	 * Ids of every list the user can read (owned + shared with them).
	 *
	 * @return list<string>
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	public function listIdsFor(string $userId): array {
		$owned = array_map(
			static fn (ListEntity $list): string => $list->getId(),
			$this->listMapper->findAllByOwner($userId),
		);
		$shared = $this->shareMapper->findActiveListIdsByRecipient($userId);

		return array_values(array_unique([...$owned, ...$shared]));
	}

	/**
	 * Catalog owner uids whose items are visible to the user: the user plus every
	 * user who has an active list share with them.
	 *
	 * @return list<string>
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	public function visibleCatalogOwners(string $userId): array {
		$owners = [$userId];
		foreach ($this->shareMapper->findByRecipient($userId) as $share) {
			$owner = $share->getOwner();
			if ($share->getStatus() === ListShareEntity::STATUS_ACTIVE && $owner !== null) {
				$owners[] = $owner;
			}
		}

		return array_values(array_unique($owners));
	}
}
