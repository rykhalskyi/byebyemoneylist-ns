<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Controller;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use OCA\ByeByeMoneyList\AppInfo\Application;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\ListShareMapper;
use OCA\ByeByeMoneyList\Entity\ListShareEntity;
use OCA\ByeByeMoneyList\Service\Sharing\ListAccessService;
use OCA\ByeByeMoneyList\Util\Uuid;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class ShareController extends OCSController {
	private ListMapper $listMapper;
	private ListShareMapper $shareMapper;
	private ListAccessService $listAccess;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	public function __construct(
		IRequest $request,
		ListMapper $listMapper,
		ListShareMapper $shareMapper,
		ListAccessService $listAccess,
		IUserSession $userSession,
		LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->listMapper = $listMapper;
		$this->shareMapper = $shareMapper;
		$this->listAccess = $listAccess;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Lists currently shared with the current user (including revoked, name-only)
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED, array{shares: list<array{id: string, listId: string, owner: string, sharedWith: string, mode: string, status: string, revoked: bool, listName: ?string, createdAt: ?string, updatedAt: ?string}>}|array{message: string}, array{}>
	 *
	 * 200: Shares returned
	 * 401: Current user is not logged in
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/shares/incoming')]
	public function incoming(): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$shares = $this->shareMapper->findByRecipient($userId);
		$serialized = array_map(
			fn (ListShareEntity $share): array => $this->serializeShare(
				$share,
				$this->listName($share->getListId()),
			),
			$shares,
		);

		return new DataResponse(['shares' => array_values($serialized)], Http::STATUS_OK);
	}

	/**
	 * Shares created for a list owned by the current user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id List id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, array{shares: list<array{id: string, listId: string, owner: string, sharedWith: string, mode: string, status: string, revoked: bool, listName: ?string, createdAt: ?string, updatedAt: ?string}>}|array{message: string}, array{}>
	 *
	 * 200: Shares returned
	 * 401: Current user is not logged in
	 * 404: List not found or not owned by the current user
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/lists/{id}/shares')]
	public function index(string $id): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$list = $this->listMapper->findByIdAndOwner($id, $userId);
		if ($list === null) {
			return new DataResponse(['message' => 'List not found'], Http::STATUS_NOT_FOUND);
		}

		$shares = $this->shareMapper->findByListId($id);
		$serialized = array_map(
			fn (ListShareEntity $share): array => $this->serializeShare($share, $list->getName()),
			$shares,
		);

		return new DataResponse(['shares' => array_values($serialized)], Http::STATUS_OK);
	}

	/**
	 * Share a list with another user
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id List id
	 * @param string $sharedWith User id to share with
	 * @param string $mode Share mode (readonly, readwrite)
	 *
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY|Http::STATUS_INTERNAL_SERVER_ERROR, array{share: array{id: string, listId: string, owner: string, sharedWith: string, mode: string, status: string, revoked: bool, listName: ?string, createdAt: ?string, updatedAt: ?string}}|array{message: string}, array{}>
	 *
	 * 201: Share created
	 * 200: Share updated
	 * 401: Current user is not logged in
	 * 404: List not found or not owned by the current user
	 * 422: Missing/blank recipient or invalid mode
	 * 500: Failed to create the share
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/lists/{id}/shares')]
	public function create(string $id, string $sharedWith, string $mode = ListShareEntity::MODE_READONLY): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$list = $this->listMapper->findByIdAndOwner($id, $userId);
		if ($list === null) {
			return new DataResponse(['message' => 'List not found'], Http::STATUS_NOT_FOUND);
		}

		$sharedWith = trim($sharedWith);
		if ($sharedWith === '') {
			return new DataResponse(['message' => 'Recipient is required'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		if ($sharedWith === $userId) {
			return new DataResponse(['message' => 'Cannot share a list with yourself'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		if (!in_array($mode, [ListShareEntity::MODE_READONLY, ListShareEntity::MODE_READWRITE], true)) {
			return new DataResponse(['message' => 'Invalid share mode'], Http::STATUS_UNPROCESSABLE_ENTITY);
		}

		$now = new DateTime('now', new DateTimeZone('UTC'));
		$existing = $this->shareMapper->findByListAndUser($id, $sharedWith);

		if ($existing !== null) {
			$existing->setOwner($userId);
			$existing->setMode($mode);
			$existing->setStatus(ListShareEntity::STATUS_ACTIVE);
			$existing->setUpdatedAt($now);
			try {
				$updated = $this->shareMapper->update($existing);
			} catch (\Exception $e) {
				$this->logger->error('Failed to update list share', ['exception' => $e]);
				return new DataResponse(['message' => 'Failed to share list'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}

			return new DataResponse(['share' => $this->serializeShare($updated, $list->getName())], Http::STATUS_OK);
		}

		$share = new ListShareEntity();
		$share->setId(Uuid::v4());
		$share->setListId($id);
		$share->setOwner($userId);
		$share->setSharedWith($sharedWith);
		$share->setMode($mode);
		$share->setStatus(ListShareEntity::STATUS_ACTIVE);
		$share->setCreatedAt($now);
		$share->setUpdatedAt($now);

		try {
			$created = $this->shareMapper->insert($share);
		} catch (\Exception $e) {
			$this->logger->error('Failed to create list share', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to share list'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['share' => $this->serializeShare($created, $list->getName())], Http::STATUS_CREATED);
	}

	/**
	 * Revoke a list share (kept as a name-only placeholder for the guest)
	 *
	 * @psalm-suppress InvalidReturnType, InvalidReturnStatement
	 *
	 * @param string $id List id
	 * @param string $shareId Share id
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{share: array{id: string, listId: string, owner: string, sharedWith: string, mode: string, status: string, revoked: bool, listName: ?string, createdAt: ?string, updatedAt: ?string}}|array{message: string}, array{}>
	 *
	 * 200: Share revoked
	 * 401: Current user is not logged in
	 * 404: List or share not found or not owned by the current user
	 * 500: Failed to revoke the share
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/lists/{id}/shares/{shareId}')]
	public function destroy(string $id, string $shareId): DataResponse {
		$userId = $this->getCurrentUserId();
		if ($userId === null) {
			return new DataResponse(['message' => 'Not logged in'], Http::STATUS_UNAUTHORIZED);
		}

		$list = $this->listMapper->findByIdAndOwner($id, $userId);
		if ($list === null) {
			return new DataResponse(['message' => 'List not found'], Http::STATUS_NOT_FOUND);
		}

		$share = $this->shareMapper->findById($shareId);
		if ($share === null || $share->getListId() !== $id || $share->getOwner() !== $userId) {
			return new DataResponse(['message' => 'Share not found'], Http::STATUS_NOT_FOUND);
		}

		$share->setStatus(ListShareEntity::STATUS_REVOKED);
		$share->setUpdatedAt(new DateTime('now', new DateTimeZone('UTC')));

		try {
			$updated = $this->shareMapper->update($share);
		} catch (\Exception $e) {
			$this->logger->error('Failed to revoke list share', ['exception' => $e]);
			return new DataResponse(['message' => 'Failed to revoke share'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		return new DataResponse(['share' => $this->serializeShare($updated, $list->getName())], Http::STATUS_OK);
	}

	private function getCurrentUserId(): ?string {
		return $this->userSession->getUser()?->getUID();
	}

	private function listName(?string $listId): ?string {
		if ($listId === null) {
			return null;
		}
		return $this->listMapper->findById($listId)?->getName();
	}

	/**
	 * @return array{id: string, listId: string, owner: string, sharedWith: string, mode: string, status: string, revoked: bool, listName: ?string, createdAt: ?string, updatedAt: ?string}
	 */
	private function serializeShare(ListShareEntity $share, ?string $listName = null): array {
		$createdAt = $share->getCreatedAt();
		$updatedAt = $share->getUpdatedAt();

		return [
			'id' => $share->getId(),
			'listId' => $share->getListId() ?? '',
			'owner' => $share->getOwner() ?? '',
			'sharedWith' => $share->getSharedWith() ?? '',
			'mode' => $share->getMode() ?? ListShareEntity::MODE_READONLY,
			'status' => $share->getStatus() ?? ListShareEntity::STATUS_ACTIVE,
			'revoked' => ($share->getStatus() ?? '') === ListShareEntity::STATUS_REVOKED,
			'listName' => $listName,
			'createdAt' => $createdAt?->format(DateTimeInterface::ATOM),
			'updatedAt' => $updatedAt?->format(DateTimeInterface::ATOM),
		];
	}
}
