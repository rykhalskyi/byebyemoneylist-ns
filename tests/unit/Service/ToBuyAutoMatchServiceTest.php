<?php

declare(strict_types=1);

namespace Service;

use OCA\ByeByeMoneyList\Db\ListItemMapper;
use OCA\ByeByeMoneyList\Db\ListMapper;
use OCA\ByeByeMoneyList\Db\LlmProfileMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Entity\ListEntity;
use OCA\ByeByeMoneyList\Entity\ListItemEntity;
use OCA\ByeByeMoneyList\Entity\LlmProfileEntity;
use OCA\ByeByeMoneyList\Service\Receipt\OpenAiCompatibleScanner;
use OCA\ByeByeMoneyList\Service\ToBuy\ToBuyAutoMatchService;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class ToBuyAutoMatchServiceTest extends TestCase {
	private LlmProfileMapper $profileMapper;
	private ICrypto $crypto;
	private OpenAiCompatibleScanner $scanner;
	private ListMapper $listMapper;
	private ListItemMapper $itemMapper;
	private ToBuyAutoMatchService $service;

	protected function setUp(): void {
		$this->profileMapper = $this->createMock(LlmProfileMapper::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->scanner = $this->createMock(OpenAiCompatibleScanner::class);
		$this->listMapper = $this->createMock(ListMapper::class);
		$this->itemMapper = $this->createMock(ListItemMapper::class);
		$productMapper = $this->createMock(ProductMapper::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->crypto->method('decrypt')->willReturn('secret');
		$this->service = new ToBuyAutoMatchService(
			$this->profileMapper,
			$this->crypto,
			$this->scanner,
			$this->listMapper,
			$this->itemMapper,
			$productMapper,
			$logger,
		);
	}

	private function list(string $id = 'tb'): ListEntity {
		$list = new ListEntity();
		$list->setId($id);
		$list->setOwner('alice');
		$list->setKind(ListEntity::KIND_NEED_TO_BUY);
		$list->setIsActive(true);
		return $list;
	}

	private function item(string $id, string $name, bool $checked = false, float $quantity = 1.0): ListItemEntity {
		$item = new ListItemEntity();
		$item->setId($id);
		$item->setOwner('alice');
		$item->setListId('tb');
		$item->setProductId(null);
		$item->setCustomName($name);
		$item->setQuantity($quantity);
		$item->setIsChecked($checked);
		return $item;
	}

	private function profile(): LlmProfileEntity {
		$profile = new LlmProfileEntity();
		$profile->setId('p1');
		$profile->setOwner('alice');
		$profile->setProvider('siliconflow');
		$profile->setModel(null);
		$profile->setApiKey('encrypted');
		$profile->setIsActive(true);
		return $profile;
	}

	public function testDoesNothingWithoutAnActiveProfile(): void {
		$this->listMapper->method('findActiveToBuyList')->willReturn($this->list());
		$this->itemMapper->method('findByListId')->willReturn([$this->item('i1', 'Milk')]);
		$this->profileMapper->method('findActiveByOwner')->willReturn(null);
		$this->scanner->expects($this->never())->method('complete');

		$this->service->matchAfterPurchase('alice', ['Milk']);
	}

	public function testDoesNothingWithoutAnActiveToBuyList(): void {
		$this->listMapper->method('findActiveToBuyList')->willReturn(null);
		$this->profileMapper->method('findActiveByOwner')->willReturn($this->profile());
		$this->scanner->expects($this->never())->method('complete');

		$this->service->matchAfterPurchase('alice', ['Milk']);
	}

	public function testChecksOnlyKnownPendingItems(): void {
		$milk = $this->item('i1', 'Milk');
		$bread = $this->item('i2', 'Bread', true);
		$this->listMapper->method('findActiveToBuyList')->willReturn($this->list());
		$this->itemMapper->method('findByListId')->willReturn([$milk, $bread]);
		$this->profileMapper->method('findActiveByOwner')->willReturn($this->profile());
		$this->scanner->method('complete')->willReturn('{"matchedIds":["i1","unknown","i2"]}');

		$updated = [];
		$this->itemMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function (ListItemEntity $item) use (&$updated): ListItemEntity {
				$updated[] = $item;
				return $item;
			});

		$this->service->matchAfterPurchase('alice', ['Milk']);

		$this->assertCount(1, $updated);
		$this->assertSame('i1', $updated[0]->getId());
		$this->assertTrue($updated[0]->getIsChecked());
	}

	public function testSwallowsProviderFailures(): void {
		$this->listMapper->method('findActiveToBuyList')->willReturn($this->list());
		$this->itemMapper->method('findByListId')->willReturn([$this->item('i1', 'Milk')]);
		$this->profileMapper->method('findActiveByOwner')->willReturn($this->profile());
		$this->scanner->method('complete')->willThrowException(new RuntimeException('down'));
		$this->itemMapper->expects($this->never())->method('update');

		$this->service->matchAfterPurchase('alice', ['Milk']);
	}

	public function testResolvesNamesFromAFinishedListCheckedItems(): void {
		$finishedItem = $this->item('f1', 'Milk');
		$finishedItem->setIsChecked(true);
		$pending = $this->item('i1', 'Milk');
		$this->listMapper->method('findActiveToBuyList')->willReturn($this->list());
		$this->itemMapper->method('findByListId')->willReturnCallback(
			static fn (string $listId): array => $listId === 'finished' ? [$finishedItem] : [$pending],
		);
		$this->profileMapper->method('findActiveByOwner')->willReturn($this->profile());
		$this->scanner->method('complete')->willReturn('{"matchedIds":["i1"]}');
		$this->itemMapper->expects($this->once())->method('update')->willReturnArgument(0);

		$this->service->matchAfterPurchaseList('alice', 'finished');
	}
}
