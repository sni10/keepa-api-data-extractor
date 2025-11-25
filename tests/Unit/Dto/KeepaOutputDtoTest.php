<?php

namespace App\Tests\Unit\Dto;

use App\Dto\KeepaOutputDto;
use PHPUnit\Framework\TestCase;

class KeepaOutputDtoTest extends TestCase
{
    public function testFromArrayWithCompleteData(): void
    {
        $data = [
            'asin' => 'B08N5WRWNW',
            'brand' => 'Adidas',
            'title' => 'Adidas Ultraboost 21',
            'upc_list' => ['123456789012', '234567890123'],
            'ean_list' => ['1234567890123', '2345678901234'],
            'search_request_id' => 42,
            'domain_id' => 1,
            'json_data' => ['key' => 'value'],
            'time_from' => '2025-01-01',
            'time_to' => '2025-01-31',
            'version' => 1,
        ];

        $dto = KeepaOutputDto::fromArray($data);

        self::assertSame('B08N5WRWNW', $dto->asin);
        self::assertSame('Adidas', $dto->brand);
        self::assertSame('Adidas Ultraboost 21', $dto->title);
        self::assertSame(['123456789012', '234567890123'], $dto->upc_list);
        self::assertSame(['1234567890123', '2345678901234'], $dto->ean_list);
        self::assertSame(42, $dto->search_request_id);
        self::assertSame(1, $dto->domain_id);
        self::assertSame(['key' => 'value'], $dto->json_data);
        self::assertSame('2025-01-01', $dto->time_from);
        self::assertSame('2025-01-31', $dto->time_to);
        self::assertSame(1, $dto->version);
    }

    public function testFromArrayWithMissingFields(): void
    {
        $data = [
            'asin' => 'B08N5WRWNW',
            'brand' => 'Adidas',
        ];

        $dto = KeepaOutputDto::fromArray($data);

        self::assertSame('B08N5WRWNW', $dto->asin);
        self::assertSame('Adidas', $dto->brand);
        self::assertNull($dto->title);
        self::assertNull($dto->upc_list);
        self::assertNull($dto->ean_list);
        self::assertNull($dto->search_request_id);
        self::assertNull($dto->domain_id);
        self::assertNull($dto->json_data);
        self::assertNull($dto->time_from);
        self::assertNull($dto->time_to);
        self::assertNull($dto->version);
    }

    public function testFromArrayCastsIntegerFields(): void
    {
        $data = [
            'asin' => 'B08N5WRWNW',
            'search_request_id' => '42',
            'domain_id' => '1',
            'version' => '5',
        ];

        $dto = KeepaOutputDto::fromArray($data);

        self::assertSame(42, $dto->search_request_id);
        self::assertSame(1, $dto->domain_id);
        self::assertSame(5, $dto->version);
    }

    public function testToArrayReturnsAllProperties(): void
    {
        $dto = new KeepaOutputDto();
        $dto->asin = 'B08N5WRWNW';
        $dto->brand = 'Nike';
        $dto->title = 'Nike Air Max';
        $dto->upc_list = ['111111111111'];
        $dto->ean_list = ['2222222222222'];
        $dto->search_request_id = 100;
        $dto->domain_id = 2;
        $dto->json_data = ['test' => 'data'];
        $dto->time_from = '2025-02-01';
        $dto->time_to = '2025-02-28';
        $dto->version = 2;

        $array = $dto->toArray();

        self::assertSame('B08N5WRWNW', $array['asin']);
        self::assertSame('Nike', $array['brand']);
        self::assertSame('Nike Air Max', $array['title']);
        self::assertSame(['111111111111'], $array['upc_list']);
        self::assertSame(['2222222222222'], $array['ean_list']);
        self::assertSame(100, $array['search_request_id']);
        self::assertSame(2, $array['domain_id']);
        self::assertSame(['test' => 'data'], $array['json_data']);
        self::assertSame('2025-02-01', $array['time_from']);
        self::assertSame('2025-02-28', $array['time_to']);
        self::assertSame(2, $array['version']);
    }

    public function testToArrayWithNullProperties(): void
    {
        $dto = new KeepaOutputDto();

        $array = $dto->toArray();

        self::assertNull($array['asin']);
        self::assertNull($array['brand']);
        self::assertNull($array['title']);
        self::assertNull($array['upc_list']);
        self::assertNull($array['ean_list']);
        self::assertNull($array['search_request_id']);
        self::assertNull($array['domain_id']);
        self::assertNull($array['json_data']);
        self::assertNull($array['time_from']);
        self::assertNull($array['time_to']);
        self::assertNull($array['version']);
    }

    public function testRoundTripConversion(): void
    {
        $originalData = [
            'asin' => 'B08N5WRWNW',
            'brand' => 'Puma',
            'title' => 'Puma Suede Classic',
            'upc_list' => ['333333333333'],
            'ean_list' => ['4444444444444'],
            'search_request_id' => 999,
            'domain_id' => 3,
            'json_data' => ['nested' => ['data' => 'here']],
            'time_from' => '2025-03-01',
            'time_to' => '2025-03-31',
            'version' => 10,
        ];

        $dto = KeepaOutputDto::fromArray($originalData);
        $resultArray = $dto->toArray();

        self::assertSame($originalData, $resultArray);
    }
}
