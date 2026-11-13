<?php

declare(strict_types=1);

namespace Service;

use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\LlmProfileMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Entity\LlmProfileEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportCategoryEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportEntity;
use OCA\ByeByeMoneyList\Entity\PurchaseImportItemEntity;
use OCA\ByeByeMoneyList\Entity\StoreEntity;
use OCA\ByeByeMoneyList\Service\Import\PurchaseImportAiService;
use OCA\ByeByeMoneyList\Service\Receipt\NoActiveLlmProfileException;
use OCA\ByeByeMoneyList\Service\Receipt\OpenAiCompatibleScanner;
use OCA\ByeByeMoneyList\Service\Receipt\ReceiptProductMatcher;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PurchaseImportAiServiceTest extends TestCase {
	private PurchaseImportAiService $service;
	private LlmProfileMapper $profileMapper;
	private OpenAiCompatibleScanner $scanner;

	protected function setUp(): void {
		$this->profileMapper = $this->createMock(LlmProfileMapper::class);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('decrypt')->willReturn('key');
		$this->scanner = $this->createMock(OpenAiCompatibleScanner::class);

		$storeMapper = $this->createMock(StoreMapper::class);
		$store = new StoreEntity();
		$store->setId('store-1');
		$store->setOwner('alice');
		$store->setName('Lidl');
		$storeMapper->method('findAllByOwner')->willReturn([$store]);

		$categoryMapper = $this->createMock(CategoryMapper::class);
		$category = new CategoryEntity();
		$category->setId('cat-1');
		$category->setOwner('alice');
		$category->setName('Groceries');
		$categoryMapper->method('findAllByOwner')->willReturn([$category]);

		$productMapper = $this->createMock(ProductMapper::class);
		$product = new ProductEntity();
		$product->setId('product-1');
		$product->setOwner('alice');
		$product->setName('Milk');
		$productMapper->method('findAllIncludingSpecialByOwner')->willReturn([$product]);

		$this->service = new PurchaseImportAiService(
			$this->profileMapper,
			$crypto,
			$this->scanner,
			$storeMapper,
			$categoryMapper,
			$productMapper,
			new ReceiptProductMatcher(),
			$this->createMock(LoggerInterface::class),
		);
	}

	private function profile(): LlmProfileEntity {
		$profile = new LlmProfileEntity();
		$profile->setId('profile-1');
		$profile->setOwner('alice');
		$profile->setProvider('deepseek');
		$profile->setApiKey('encrypted');
		$profile->setModel('deepseek-v4');
		$profile->setConnectTimeout(5);
		$profile->setReadTimeout(5);
		$profile->setMaxTokens(512);
		return $profile;
	}

	private function import(): PurchaseImportEntity {
		$import = new PurchaseImportEntity();
		$import->setOwner('alice');
		$import->setName('Purchase');
		$import->setStoreName('Lidl');
		$import->setStoreAction(PurchaseImportEntity::ACTION_PENDING);
		return $import;
	}

	private function item(): PurchaseImportItemEntity {
		$item = new PurchaseImportItemEntity();
		$item->setId('item-1');
		$item->setOwner('alice');
		$item->setLineId('1');
		$item->setRawName('Milky');
		$item->setQuantity(1.0);
		$item->setPosition(0);
		$item->setAction(PurchaseImportEntity::ACTION_PENDING);
		return $item;
	}

	private function category(): PurchaseImportCategoryEntity {
		$category = new PurchaseImportCategoryEntity();
		$category->setId('row-cat-1');
		$category->setOwner('alice');
		$category->setRawName('Groceries');
		$category->setAction(PurchaseImportEntity::ACTION_PENDING);
		return $category;
	}

	public function testThrowsWithoutActiveProfile(): void {
		$this->profileMapper->method('findActiveByOwner')->willReturn(null);

		$this->expectException(NoActiveLlmProfileException::class);

		$this->service->suggest($this->import(), [$this->item()], [$this->category()], 'alice');
	}

	public function testUsesTheOnlyProfileWhenNoneIsActive(): void {
		$this->profileMapper->method('findActiveByOwner')->willReturn(null);
		$this->profileMapper->method('findAllByOwner')->willReturn([$this->profile()]);
		$this->scanner->expects($this->once())
			->method('complete')
			->with('deepseek', 'key', 'deepseek-v4', $this->isType('string'), 5, 5, 512)
			->willReturn('{"categories":[],"items":[]}');

		$this->service->suggest($this->import(), [$this->item()], [$this->category()], 'alice');
	}

	public function testAppliesValidatedSuggestions(): void {
		$this->profileMapper->method('findActiveByOwner')->willReturn($this->profile());
		$this->scanner->method('complete')->willReturn(json_encode([
			'storeId' => 'store-1',
			'newStoreName' => null,
			'categories' => [
				['rawName' => 'Groceries', 'categoryId' => 'cat-1', 'newCategoryName' => null],
			],
			'items' => [
				[
					'lineId' => '1',
					'productId' => 'product-1',
					'newProductName' => null,
					'categoryId' => 'cat-1',
					'confidence' => 0.92,
				],
			],
		]) ?: '');

		$import = $this->import();
		$item = $this->item();
		$category = $this->category();

		$this->service->suggest($import, [$item], [$category], 'alice');

		$this->assertSame('store-1', $import->getSuggestedStoreId());
		$this->assertSame('ai', $import->getStoreMatchSource());
		$this->assertSame('cat-1', $category->getSuggestedCategoryId());
		$this->assertSame('ai', $category->getMatchSource());
		$this->assertSame('product-1', $item->getSuggestedProductId());
		$this->assertSame('cat-1', $item->getSuggestedCategoryId());
		$this->assertSame('ai', $item->getMatchSource());
		$this->assertSame(0.92, $item->getConfidence());
	}

	public function testIgnoresUnknownIdsAndLowConfidence(): void {
		$this->profileMapper->method('findActiveByOwner')->willReturn($this->profile());
		$this->scanner->method('complete')->willReturn(json_encode([
			'storeId' => 'unknown-store',
			'items' => [
				[
					'lineId' => '1',
					'productId' => 'unknown-product',
					'newProductName' => null,
					'categoryId' => null,
					'confidence' => 0.1,
				],
			],
		]) ?: '');

		$import = $this->import();
		$item = $this->item();

		$this->service->suggest($import, [$item], [$this->category()], 'alice');

		$this->assertNull($import->getSuggestedStoreId());
		$this->assertNull($item->getSuggestedProductId());
		$this->assertNull($item->getMatchSource());
	}
}
