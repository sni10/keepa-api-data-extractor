<?php

namespace App\Service\Validator;

use App\Dto\KeepaInputDto;
use App\Dto\KeepaOutputDto;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

class MessageValidator
{
    public function __construct(
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {}

    public function validateInput(KeepaInputDto $dto): ?KeepaInputDto
    {
        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $this->logger->warning("Invalid Kafka message", [
                'errors' => (string)$errors,
                'payload' => $dto
            ]);
            return null;
        }

        return $dto;
    }

    public function validateOutput(KeepaOutputDto $message): ?KeepaOutputDto
    {
        $errors = $this->validator->validate($message);
        if (count($errors) > 0) {
            $this->logger->warning("Invalid message", [
                'errors' => (string)$errors,
                'payload' => $message
            ]);
            return null;
        }

        return $message;
    }


}
