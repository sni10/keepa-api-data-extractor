<?php
// src/Serializer/InputMessageSerializer.php

namespace App\Serializer;

use App\Dto\KeepaInputDto;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class InputMessageSerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        if (!isset($encodedEnvelope['body'])) {
            throw new MessageDecodingFailedException('Missing body');
        }

        $data = json_decode($encodedEnvelope['body'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new MessageDecodingFailedException('Invalid JSON: ' . json_last_error_msg());
        }

        return new Envelope(KeepaInputDto::fromArray($data));
    }

    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();
        return [
            'body' => json_encode([
                'id' => $message->id,
                'domain_id' => $message->domain_id,
                'brand' => $message->brand,
                'time_from' => $message->time_from,
                'time_to' => $message->time_to,
                'version' => $message->version,
                'status' => $message->status,
                'step' => $message->step,
            ]),
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8'
            ],
        ];
    }
}