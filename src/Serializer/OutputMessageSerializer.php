<?php
// src/Serializer/OutputMessageSerializer.php

namespace App\Serializer;

use App\Dto\KeepaOutputDto;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class OutputMessageSerializer implements SerializerInterface
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

        return new Envelope(KeepaOutputDto::fromArray($data));
    }

    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();
        return [
            'body' => json_encode($message->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8'
            ],
        ];
    }
}