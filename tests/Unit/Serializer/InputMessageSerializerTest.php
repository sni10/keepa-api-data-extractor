<?php

namespace App\Tests\Unit\Serializer;

use App\Dto\KeepaInputDto;
use App\Serializer\InputMessageSerializer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;

class InputMessageSerializerTest extends TestCase
{
    private InputMessageSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new InputMessageSerializer();
    }

    public function testDecodeWithValidJson(): void
    {
        $data = [
            'id' => 1,
            'domain_id' => 5,
            'brand' => 'Nike',
            'time_from' => '2025-01-01',
            'time_to' => '2025-01-31',
            'version' => 2,
            'status' => 'PENDING',
            'step' => 0,
        ];

        $encodedEnvelope = [
            'body' => json_encode($data),
        ];

        $envelope = $this->serializer->decode($encodedEnvelope);

        self::assertInstanceOf(Envelope::class, $envelope);
        $message = $envelope->getMessage();
        self::assertInstanceOf(KeepaInputDto::class, $message);
        self::assertSame(1, $message->id);
        self::assertSame(5, $message->domain_id);
        self::assertSame('Nike', $message->brand);
        self::assertSame('2025-01-01', $message->time_from);
        self::assertSame('2025-01-31', $message->time_to);
        self::assertSame(2, $message->version);
        self::assertSame('PENDING', $message->status);
        self::assertSame(0, $message->step);
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

    public function testDecodeHandlesNullVersion(): void
    {
        $data = [
            'id' => 10,
            'domain_id' => 3,
            'brand' => 'Adidas',
            'time_from' => '2025-02-01',
            'time_to' => '2025-02-28',
            'status' => 'IN_PROGRESS',
            'step' => 1,
        ];

        $encodedEnvelope = [
            'body' => json_encode($data),
        ];

        $envelope = $this->serializer->decode($encodedEnvelope);
        $message = $envelope->getMessage();

        self::assertInstanceOf(KeepaInputDto::class, $message);
        self::assertNull($message->version);
    }

    public function testEncodeWithValidDto(): void
    {
        $dto = KeepaInputDto::fromArray([
            'id' => 42,
            'domain_id' => 1,
            'brand' => 'Puma',
            'time_from' => '2025-03-01',
            'time_to' => '2025-03-31',
            'version' => 5,
            'status' => 'COMPLETED',
            'step' => 3,
        ]);

        $envelope = new Envelope($dto);
        $encoded = $this->serializer->encode($envelope);

        self::assertArrayHasKey('body', $encoded);
        self::assertArrayHasKey('headers', $encoded);
        self::assertSame('application/json; charset=utf-8', $encoded['headers']['Content-Type']);

        $decodedBody = json_decode($encoded['body'], true);
        self::assertSame(42, $decodedBody['id']);
        self::assertSame(1, $decodedBody['domain_id']);
        self::assertSame('Puma', $decodedBody['brand']);
        self::assertSame('2025-03-01', $decodedBody['time_from']);
        self::assertSame('2025-03-31', $decodedBody['time_to']);
        self::assertSame(5, $decodedBody['version']);
        self::assertSame('COMPLETED', $decodedBody['status']);
        self::assertSame(3, $decodedBody['step']);
    }

    public function testEncodeWithNullVersion(): void
    {
        $dto = KeepaInputDto::fromArray([
            'id' => 99,
            'domain_id' => 2,
            'brand' => 'Reebok',
            'time_from' => '2025-04-01',
            'time_to' => '2025-04-30',
            'status' => 'FAILED',
            'step' => 5,
        ]);

        $envelope = new Envelope($dto);
        $encoded = $this->serializer->encode($envelope);

        $decodedBody = json_decode($encoded['body'], true);
        self::assertArrayHasKey('version', $decodedBody);
        self::assertNull($decodedBody['version']);
    }

    public function testRoundTripEncodeAndDecode(): void
    {
        $originalDto = KeepaInputDto::fromArray([
            'id' => 777,
            'domain_id' => 8,
            'brand' => 'Asics',
            'time_from' => '2025-05-01',
            'time_to' => '2025-05-31',
            'version' => 10,
            'status' => 'CANCELLED',
            'step' => 7,
        ]);

        $envelope = new Envelope($originalDto);
        $encoded = $this->serializer->encode($envelope);
        $decodedEnvelope = $this->serializer->decode($encoded);

        $decodedDto = $decodedEnvelope->getMessage();

        self::assertInstanceOf(KeepaInputDto::class, $decodedDto);
        self::assertSame($originalDto->id, $decodedDto->id);
        self::assertSame($originalDto->domain_id, $decodedDto->domain_id);
        self::assertSame($originalDto->brand, $decodedDto->brand);
        self::assertSame($originalDto->time_from, $decodedDto->time_from);
        self::assertSame($originalDto->time_to, $decodedDto->time_to);
        self::assertSame($originalDto->version, $decodedDto->version);
        self::assertSame($originalDto->status, $decodedDto->status);
        self::assertSame($originalDto->step, $decodedDto->step);
    }
}
