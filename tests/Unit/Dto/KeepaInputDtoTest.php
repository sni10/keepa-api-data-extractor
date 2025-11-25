<?php

namespace App\Tests\Unit\Dto;

use App\Dto\KeepaInputDto;
use PHPUnit\Framework\TestCase;

class KeepaInputDtoTest extends TestCase
{
    public function testFromArrayNormalizesAndCastsFields(): void
    {
        $dto = KeepaInputDto::fromArray([
            'id' => '1',
            'domain_id' => '5',
            'brand' => ' adidas ',
            'time_from' => '2025-01-01 ',
            'time_to' => '2025-01-31',
            'version' => '2',
            'status' => ' PENDING ',
            'step' => '3',
        ]);

        self::assertSame(1, $dto->id);
        self::assertSame(5, $dto->domain_id);
        self::assertSame('adidas', $dto->brand);
        self::assertSame('2025-01-01', $dto->time_from);
        self::assertSame('2025-01-31', $dto->time_to);
        self::assertSame(2, $dto->version);
        self::assertSame('PENDING', $dto->status);
        self::assertSame(3, $dto->step);

        self::assertSame(
            '2025-01-01 - 2025-01-31',
            $dto->getTimeRange(),
        );

        self::assertSame([
            'id' => 1,
            'domain_id' => 5,
            'brand' => 'adidas',
            'time_from' => '2025-01-01',
            'time_to' => '2025-01-31',
            'version' => 2,
            'status' => 'PENDING',
            'step' => 3,
        ], $dto->toArray());
    }

    public function testGetBrandReturnsNormalizedBrand(): void
    {
        $dto = KeepaInputDto::fromArray([
            'id' => '10',
            'domain_id' => '3',
            'brand' => "  Nike  ",
            'time_from' => '2025-02-01',
            'time_to' => '2025-02-28',
            'version' => '1',
            'status' => ' IN_PROGRESS ',
            'step' => '1',
        ]);

        self::assertSame('Nike', $dto->getBrand());
    }
}
