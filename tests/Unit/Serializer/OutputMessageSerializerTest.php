<?php

namespace App\Tests\Unit\Serializer;

use App\Dto\KeepaOutputDto;
use App\Serializer\OutputMessageSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

class OutputMessageSerializerTest extends TestCase
{
    private OutputMessageSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new OutputMessageSerializer();
    }

    public function testDecodeWithValidJson(): void
    {
        $data = [
            'asin' => 'B08N5WRWNW',
            'brand' => 'Nike',
            'title' => 'Nike Air Max',
            'upc_list' => ['123456789012'],
            'ean_list' => ['1234567890123'],
            'search_request_id' => 42,
            'domain_id' => 1,
            'json_data' => ['key' => 'value'],
            'time_from' => '2025-01-01',
            'time_to' => '2025-01-31',
            'version' => 2,
        ];

        $encodedEnvelope = [
            'body' => json_encode($data),
        ];

        $envelope = $this->serializer->decode($encodedEnvelope);

        self::assertInstanceOf(Envelope::class, $envelope);
        $message = $envelope->getMessage();
        self::assertInstanceOf(KeepaOutputDto::class, $message);
        self::assertSame('B08N5WRWNW', $message->asin);
        self::assertSame('Nike', $message->brand);
        self::assertSame('Nike Air Max', $message->title);
        self::assertSame(['123456789012'], $message->upc_list);
        self::assertSame(['1234567890123'], $message->ean_list);
        self::assertSame(42, $message->search_request_id);
        self::assertSame(1, $message->domain_id);
        self::assertSame(['key' => 'value'], $message->json_data);
        self::assertSame('2025-01-01', $message->time_from);
        self::assertSame('2025-01-31', $message->time_to);
        self::assertSame(2, $message->version);
    }

    public function testDecodeThrowsExceptionWhenBodyIsMissing(): void
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Missing body');

        $encodedEnvelope = [];

        $this->serializer->decode($encodedEnvelope);
    }

    public function testDecodeThrowsExceptionWithInvalidJson(): void
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $encodedEnvelope = [
            'body' => 'invalid json {{{',
        ];

        $this->serializer->decode($encodedEnvelope);
    }

    public function testDecodeHandlesPartialData(): void
    {
        $data = [
            'asin' => 'B08N5WRWNW',
            'brand' => 'Adidas',
        ];

        $encodedEnvelope = [
            'body' => json_encode($data),
        ];

        $envelope = $this->serializer->decode($encodedEnvelope);
        $message = $envelope->getMessage();

        self::assertInstanceOf(KeepaOutputDto::class, $message);
        self::assertSame('B08N5WRWNW', $message->asin);
        self::assertSame('Adidas', $message->brand);
        self::assertNull($message->title);
        self::assertNull($message->upc_list);
        self::assertNull($message->ean_list);
        self::assertNull($message->search_request_id);
        self::assertNull($message->domain_id);
        self::assertNull($message->json_data);
        self::assertNull($message->time_from);
        self::assertNull($message->time_to);
        self::assertNull($message->version);
    }

    public function testEncodeWithValidDto(): void
    {
        $dto = new KeepaOutputDto();
        $dto->asin = 'B000TEST00';
        $dto->brand = 'Puma';
        $dto->title = 'Puma Suede';
        $dto->upc_list = ['999999999999'];
        $dto->ean_list = ['8888888888888'];
        $dto->search_request_id = 100;
        $dto->domain_id = 3;
        $dto->json_data = ['test' => 'data'];
        $dto->time_from = '2025-03-01';
        $dto->time_to = '2025-03-31';
        $dto->version = 5;

        $envelope = new Envelope($dto);
        $encoded = $this->serializer->encode($envelope);

        self::assertArrayHasKey('body', $encoded);
        self::assertArrayHasKey('headers', $encoded);
        self::assertSame('application/json; charset=utf-8', $encoded['headers']['Content-Type']);

        $decodedBody = json_decode($encoded['body'], true);
        self::assertSame('B000TEST00', $decodedBody['asin']);
        self::assertSame('Puma', $decodedBody['brand']);
        self::assertSame('Puma Suede', $decodedBody['title']);
        self::assertSame(['999999999999'], $decodedBody['upc_list']);
        self::assertSame(['8888888888888'], $decodedBody['ean_list']);
        self::assertSame(100, $decodedBody['search_request_id']);
        self::assertSame(3, $decodedBody['domain_id']);
        self::assertSame(['test' => 'data'], $decodedBody['json_data']);
        self::assertSame('2025-03-01', $decodedBody['time_from']);
        self::assertSame('2025-03-31', $decodedBody['time_to']);
        self::assertSame(5, $decodedBody['version']);
    }

    public function testEncodeWithNullFields(): void
    {
        $dto = new KeepaOutputDto();
        $dto->asin = 'B000NULL00';

        $envelope = new Envelope($dto);
        $encoded = $this->serializer->encode($envelope);

        $decodedBody = json_decode($encoded['body'], true);
        self::assertSame('B000NULL00', $decodedBody['asin']);
        self::assertNull($decodedBody['brand']);
        self::assertNull($decodedBody['title']);
        self::assertNull($decodedBody['upc_list']);
        self::assertNull($decodedBody['ean_list']);
        self::assertNull($decodedBody['search_request_id']);
        self::assertNull($decodedBody['domain_id']);
        self::assertNull($decodedBody['json_data']);
        self::assertNull($decodedBody['time_from']);
        self::assertNull($decodedBody['time_to']);
        self::assertNull($decodedBody['version']);
    }

    public function testEncodePreservesUnicodeCharacters(): void
    {
        $dto = new KeepaOutputDto();
        $dto->asin = 'B000TEST00';
        $dto->brand = 'Тест';
        $dto->title = 'Test Title with ™ and © symbols';

        $envelope = new Envelope($dto);
        $encoded = $this->serializer->encode($envelope);

        $decodedBody = json_decode($encoded['body'], true);
        self::assertSame('Тест', $decodedBody['brand']);
        self::assertSame('Test Title with ™ and © symbols', $decodedBody['title']);

        // Verify that body doesn't contain escaped unicode
        self::assertStringContainsString('Тест', $encoded['body']);
    }

    public function testRoundTripEncodeAndDecode(): void
    {
        $originalDto = new KeepaOutputDto();
        $originalDto->asin = 'B000ROUND0';
        $originalDto->brand = 'RoundTrip';
        $originalDto->title = 'Round Trip Test';
        $originalDto->upc_list = ['111111111111', '222222222222'];
        $originalDto->ean_list = ['3333333333333'];
        $originalDto->search_request_id = 999;
        $originalDto->domain_id = 5;
        $originalDto->json_data = ['nested' => ['data' => 'here']];
        $originalDto->time_from = '2025-06-01';
        $originalDto->time_to = '2025-06-30';
        $originalDto->version = 7;

        $envelope = new Envelope($originalDto);
        $encoded = $this->serializer->encode($envelope);
        $decodedEnvelope = $this->serializer->decode($encoded);

        $decodedDto = $decodedEnvelope->getMessage();

        self::assertInstanceOf(KeepaOutputDto::class, $decodedDto);
        self::assertSame($originalDto->asin, $decodedDto->asin);
        self::assertSame($originalDto->brand, $decodedDto->brand);
        self::assertSame($originalDto->title, $decodedDto->title);
        self::assertSame($originalDto->upc_list, $decodedDto->upc_list);
        self::assertSame($originalDto->ean_list, $decodedDto->ean_list);
        self::assertSame($originalDto->search_request_id, $decodedDto->search_request_id);
        self::assertSame($originalDto->domain_id, $decodedDto->domain_id);
        self::assertSame($originalDto->json_data, $decodedDto->json_data);
        self::assertSame($originalDto->time_from, $decodedDto->time_from);
        self::assertSame($originalDto->time_to, $decodedDto->time_to);
        self::assertSame($originalDto->version, $decodedDto->version);
    }
}
