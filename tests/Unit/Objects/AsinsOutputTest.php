<?php

namespace App\Tests\Unit\Objects;

use App\Dto\KeepaInputDto;
use App\Objects\AsinsOutput;
use Keepa\API\Response;
use Keepa\objects\Product;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AsinsOutputTest extends TestCase
{
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var KeepaInputDto */
    private KeepaInputDto $inputDto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);

        $this->inputDto = KeepaInputDto::fromArray([
            'id' => 1,
            'domain_id' => 1,
            'brand' => 'Nike',
            'time_from' => '2025-01-01',
            'time_to' => '2025-01-31',
            'version' => 2,
            'status' => 'PENDING',
            'step' => 0,
        ]);
    }

    public function testFromResponseReturnsEmptyArrayWhenProductsIsNull(): void
    {
        $response = $this->createMock(Response::class);
        $response->products = null;

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Kafka: No products found in response.');

        $result = AsinsOutput::fromResponse($response, $this->inputDto, $this->logger);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testFromResponseReturnsFormattedProductsArray(): void
    {
        $product1 = $this->createMock(Product::class);
        $product1->asin = 'B08N5WRWNW';
        $product1->brand = 'Nike';
        $product1->title = 'Nike Air Max';
        $product1->upcList = ['123456789012'];
        $product1->eanList = ['1234567890123'];

        $product2 = $this->createMock(Product::class);
        $product2->asin = 'B08N5WRWNY';
        $product2->brand = 'Nike';
        $product2->title = 'Nike Air Force';
        $product2->upcList = ['987654321098'];
        $product2->eanList = ['9876543210987'];

        $response = $this->createMock(Response::class);
        $response->products = [$product1, $product2];

        $result = AsinsOutput::fromResponse($response, $this->inputDto, $this->logger);

        self::assertIsArray($result);
        self::assertCount(2, $result);

        // Check first product
        self::assertSame('B08N5WRWNW', $result[0]['asin']);
        self::assertSame('Nike', $result[0]['brand']);
        self::assertSame('Nike Air Max', $result[0]['title']);
        self::assertSame(['123456789012'], $result[0]['upc_list']);
        self::assertSame(['1234567890123'], $result[0]['ean_list']);
        self::assertSame(1, $result[0]['search_request_id']);
        self::assertSame(1, $result[0]['domain_id']);
        self::assertSame('2025-01-01', $result[0]['time_from']);
        self::assertSame('2025-01-31', $result[0]['time_to']);
        self::assertSame(2, $result[0]['version']);
        self::assertIsString($result[0]['json_data']);

        // Check second product
        self::assertSame('B08N5WRWNY', $result[1]['asin']);
        self::assertSame('Nike Air Force', $result[1]['title']);
    }

    public function testFromResponseHandlesNonArrayUpcList(): void
    {
        $product = $this->createMock(Product::class);
        $product->asin = 'B08N5WRWNW';
        $product->brand = 'Adidas';
        $product->title = 'Adidas Ultraboost';
        $product->upcList = null; // Not an array
        $product->eanList = ['1234567890123'];

        $response = $this->createMock(Response::class);
        $response->products = [$product];

        $result = AsinsOutput::fromResponse($response, $this->inputDto, $this->logger);

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertSame([], $result[0]['upc_list']); // Should default to empty array
    }

    public function testFromResponseHandlesNonArrayEanList(): void
    {
        $product = $this->createMock(Product::class);
        $product->asin = 'B08N5WRWNW';
        $product->brand = 'Puma';
        $product->title = 'Puma Suede';
        $product->upcList = ['123456789012'];
        $product->eanList = null; // Not an array

        $response = $this->createMock(Response::class);
        $response->products = [$product];

        $result = AsinsOutput::fromResponse($response, $this->inputDto, $this->logger);

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertSame([], $result[0]['ean_list']); // Should default to empty array
    }

    public function testFromResponseIncludesSerializedJsonData(): void
    {
        $product = $this->createMock(Product::class);
        $product->asin = 'B08N5WRWNW';
        $product->brand = 'Reebok';
        $product->title = 'Reebok Classic';
        $product->upcList = ['111111111111'];
        $product->eanList = ['2222222222222'];

        $response = $this->createMock(Response::class);
        $response->products = [$product];

        $result = AsinsOutput::fromResponse($response, $this->inputDto, $this->logger);

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertArrayHasKey('json_data', $result[0]);
        self::assertIsString($result[0]['json_data']);

        // Verify it's valid JSON
        $decoded = json_decode($result[0]['json_data']);
        self::assertNotNull($decoded, 'json_data should be valid JSON');
    }

    public function testFromResponsePreservesInputDtoFields(): void
    {
        $inputDto = KeepaInputDto::fromArray([
            'id' => 999,
            'domain_id' => 5,
            'brand' => 'Asics',
            'time_from' => '2025-06-01',
            'time_to' => '2025-06-30',
            'version' => 10,
            'status' => 'COMPLETED',
            'step' => 5,
        ]);

        $product = $this->createMock(Product::class);
        $product->asin = 'B000TEST00';
        $product->brand = 'Asics';
        $product->title = 'Asics Gel-Kayano';
        $product->upcList = ['333333333333'];
        $product->eanList = ['4444444444444'];

        $response = $this->createMock(Response::class);
        $response->products = [$product];

        $result = AsinsOutput::fromResponse($response, $inputDto, $this->logger);

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertSame(999, $result[0]['search_request_id']);
        self::assertSame(5, $result[0]['domain_id']);
        self::assertSame('2025-06-01', $result[0]['time_from']);
        self::assertSame('2025-06-30', $result[0]['time_to']);
        self::assertSame(10, $result[0]['version']);
    }

    public function testFromResponseHandlesEmptyProductsArray(): void
    {
        $response = $this->createMock(Response::class);
        $response->products = [];

        $result = AsinsOutput::fromResponse($response, $this->inputDto, $this->logger);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testFromResponseFiltersOutFalsyValues(): void
    {
        // The array_filter call should remove any false/null values if callback returns false
        // In this case, all products should be included as the code doesn't filter them
        $product = $this->createMock(Product::class);
        $product->asin = 'B08N5WRWNW';
        $product->brand = 'TestBrand';
        $product->title = 'Test Title';
        $product->upcList = ['000000000000'];
        $product->eanList = ['0000000000000'];

        $response = $this->createMock(Response::class);
        $response->products = [$product];

        $result = AsinsOutput::fromResponse($response, $this->inputDto, $this->logger);

        self::assertCount(1, $result);
        self::assertNotEmpty($result[0]);
    }
}
