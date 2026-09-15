<?php

declare(strict_types=1);

namespace Service;

use OCA\ByeByeMoneyList\Db\CategoryMapper;
use OCA\ByeByeMoneyList\Db\LlmProfileMapper;
use OCA\ByeByeMoneyList\Db\ProductAliasMapper;
use OCA\ByeByeMoneyList\Db\ProductMapper;
use OCA\ByeByeMoneyList\Db\StoreMapper;
use OCA\ByeByeMoneyList\Entity\CategoryEntity;
use OCA\ByeByeMoneyList\Entity\LlmProfileEntity;
use OCA\ByeByeMoneyList\Entity\ProductEntity;
use OCA\ByeByeMoneyList\Service\Receipt\NoActiveLlmProfileException;
use OCA\ByeByeMoneyList\Service\Receipt\OpenAiCompatibleScanner;
use OCA\ByeByeMoneyList\Service\Receipt\ReceiptProductMatcher;
use OCA\ByeByeMoneyList\Service\Receipt\ReceiptScanService;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ReceiptScanServiceTest extends TestCase {
	private LlmProfileMapper $profileMapper;
	private ICrypto $crypto;
	private OpenAiCompatibleScanner $scanner;
	private CategoryMapper $categoryMapper;
	private StoreMapper $storeMapper;
	private ProductMapper $productMapper;
	private ProductAliasMapper $productAliasMapper;
	private ReceiptProductMatcher $matcher;
	private ReceiptScanService $service;

	protected function setUp(): void {
		$this->profileMapper = $this->createMock(LlmProfileMapper::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->scanner = $this->createMock(OpenAiCompatibleScanner::class);
		$this->categoryMapper = $this->createMock(CategoryMapper::class);
		$this->storeMapper = $this->createMock(StoreMapper::class);
		$this->productMapper = $this->createMock(ProductMapper::class);
		$this->productAliasMapper = $this->createMock(ProductAliasMapper::class);
		$this->matcher = $this->createMock(ReceiptProductMatcher::class);
		$logger = $this->createMock(LoggerInterface::class);

		$this->service = new ReceiptScanService(
			$this->profileMapper,
			$this->crypto,
			$this->scanner,
			$this->categoryMapper,
			$this->storeMapper,
			$this->productMapper,
			$this->productAliasMapper,
			$this->matcher,
			$logger,
		);
	}

	private function profile(): LlmProfileEntity {
		$profile = new LlmProfileEntity();
		$profile->setId('11111111-2222-4333-8444-555555555555');
		$profile->setOwner('alice');
		$profile->setName('DeepSeek');
		$profile->setProvider('deepseek');
		$profile->setApiKey('encrypted-key');
		$profile->setModel('deepseek-v4-flash-vision-exp');
		return $profile;
	}

	private function category(string $id, string $name): CategoryEntity {
		$category = new CategoryEntity();
		$category->setId($id);
		$category->setOwner('alice');
		$category->setName($name);
		$category->setIncome(false);
		return $category;
	}

	private function product(string $id, string $name): ProductEntity {
		$product = new ProductEntity();
		$product->setId($id);
		$product->setOwner('alice');
		$product->setName($name);
		$product->setStatus('reviewed');
		return $product;
	}

	private function tempFile(string $content = 'jpeg-bytes'): string {
		$tmp = tempnam(sys_get_temp_dir(), 'bbml-test-');
		self::assertNotFalse($tmp);
		file_put_contents($tmp, $content);
		return $tmp;
	}

	public function testScanThrowsWhenNoActiveProfile(): void {
		$this->profileMapper->expects($this->once())
			->method('findActiveByOwner')
			->with('alice')
			->willReturn(null);

		$this->expectException(NoActiveLlmProfileException::class);

		$this->service->scan('alice', '/tmp/receipt.jpg', 'image/jpeg');
	}

	public function testScanReturnsMatchedItemsAndStore(): void {
		$profile = $this->profile();
		$this->profileMapper->expects($this->once())
			->method('findActiveByOwner')
			->willReturn($profile);

		$this->crypto->expects($this->once())
			->method('decrypt')
			->with('encrypted-key')
			->willReturn('sk-123');

		$dairy = $this->category('22222222-3333-4444-8555-666666666666', 'Dairy');
		$this->categoryMapper->expects($this->once())
			->method('findAllByOwner')
			->with('alice')
			->willReturn([$dairy]);

		$this->storeMapper->expects($this->once())
			->method('findTopByOwner')
			->with('alice', 5)
			->willReturn([]);

		$milk = $this->product('33333333-4444-4555-8666-777777777777', 'Milk');
		$this->productMapper->expects($this->once())
			->method('findAllIncludingSpecialByOwner')
			->with('alice')
			->willReturn([$milk]);
		$this->productAliasMapper->expects($this->once())
			->method('findByProductIds')
			->willReturn([]);

		$this->matcher->expects($this->once())
			->method('match')
			->with('Milk', $this->isType('array'), $this->isType('array'))
			->willReturn($milk);

		$this->scanner->expects($this->once())
			->method('scan')
			->willReturn('{"store_name":"Aldi","store_address":"Main St 1","items":[{"name":"Milk","quantity":2,"price":1.5,"discount":0,"isCoupon":false,"category":"Dairy"}],"total_sum":3.0}');

		$tmp = $this->tempFile();
		$result = $this->service->scan('alice', $tmp, 'image/jpeg');
		@unlink($tmp);

		$this->assertSame('Aldi', $result['storeName']);
		$this->assertSame('Main St 1', $result['storeAddress']);
		$this->assertSame(3.0, $result['totalSum']);
		$this->assertCount(1, $result['items']);
		$this->assertSame('33333333-4444-4555-8666-777777777777', $result['items'][0]['productId']);
		$this->assertSame('22222222-3333-4444-8555-666666666666', $result['items'][0]['categoryId']);
		$this->assertSame('deepseek', $result['profile']['provider']);
	}

	public function testScanRejectsProviderContentThatIsNotJson(): void {
		$this->profileMapper->method('findActiveByOwner')->willReturn($this->profile());
		$this->crypto->method('decrypt')->willReturn('sk-123');
		$this->categoryMapper->method('findAllByOwner')->willReturn([]);
		$this->storeMapper->method('findTopByOwner')->willReturn([]);
		$this->productMapper->method('findAllIncludingSpecialByOwner')->willReturn([]);
		$this->productAliasMapper->method('findByProductIds')->willReturn([]);

		$this->scanner->expects($this->once())
			->method('scan')
			->willReturn('this is not json');

		$this->expectException(\RuntimeException::class);

		$tmp = $this->tempFile();
		try {
			$this->service->scan('alice', $tmp, 'image/jpeg');
		} finally {
			@unlink($tmp);
		}
	}
}
