<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class KeepaInputDto
{
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public int $id;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public int $domain_id;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(min: 1, max: 255)]
    #[Assert\NotEqualTo("\0", message: 'НОЛЬ БАЙТ В СООБЩЕНИИ')]
    public string $brand;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Date]
    public string $time_from;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Date]
    public string $time_to;

    #[Assert\Type('integer')]
    public ?int $version = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Choice(['PENDING', 'IN_PROGRESS', 'COMPLETED',  'FINISHED', 'FAILED', 'CANCELLED'])]
    public string $status;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public int $step;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->id = (int)($data['id']);
        $dto->domain_id = (int)($data['domain_id']);
        $dto->brand = trim((string)($data['brand'] ?? ''));
        $dto->time_from = trim((string)($data['time_from'] ?? ''));
        $dto->time_to = trim((string)($data['time_to'] ?? ''));
        $dto->version = isset($data['version']) ? (int)$data['version'] : null;
        $dto->status = trim((string)($data['status'] ?? ''));
        $dto->step = (int)($data['step'] ?? 0);
        return $dto;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'domain_id' => $this->domain_id,
            'brand' => $this->brand,
            'time_from' => $this->time_from,
            'time_to' => $this->time_to,
            'version' => $this->version,
            'status' => $this->status,
            'step' => $this->step,
        ];
    }

    public function getBrand(): string
    {
        return $this->brand;
    }

    public function getTimeRange(): string
    {
        return sprintf('%s - %s', $this->time_from, $this->time_to);
    }
}
