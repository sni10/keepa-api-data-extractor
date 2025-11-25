<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class KeepaOutputDto
{
    #[Assert\Type('string')]
    #[Assert\Length(max: 20)]
    public ?string $asin = null;

    #[Assert\Type('string')]
    #[Assert\Length(max: 255)]
    public ?string $brand = null;

    #[Assert\Type('string')]
    public ?string $title = null;

    #[Assert\Type('array')]
    public ?array $upc_list = null;

    #[Assert\Type('array')]
    public ?array $ean_list = null;

    #[Assert\Type('integer')]
    public ?int $search_request_id = null;

    #[Assert\Type('integer')]
    public ?int $domain_id = null;

    #[Assert\Type('array')]
    public ?array $json_data = null;

    #[Assert\Type('string')]
    #[Assert\Date]
    public ?string $time_from = null;

    #[Assert\Type('string')]
    #[Assert\Date]
    public ?string $time_to = null;

    #[Assert\Type('integer')]
    public ?int $version = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->asin = $data['asin'] ?? null;
        $dto->brand = $data['brand'] ?? null;
        $dto->title = $data['title'] ?? null;
        $dto->upc_list = $data['upc_list'] ?? null;
        $dto->ean_list = $data['ean_list'] ?? null;
        $dto->search_request_id = isset($data['search_request_id']) ? (int)$data['search_request_id'] : null;
        $dto->domain_id = isset($data['domain_id']) ? (int)$data['domain_id'] : null;
        $dto->json_data = $data['json_data'] ?? null;
        $dto->time_from = $data['time_from'] ?? null;
        $dto->time_to = $data['time_to'] ?? null;
        $dto->version = isset($data['version']) ? (int)$data['version'] : null;
        return $dto;
    }

    public function toArray(): array
    {
        return [
            'asin' => $this->asin,
            'brand' => $this->brand,
            'title' => $this->title,
            'upc_list' => $this->upc_list,
            'ean_list' => $this->ean_list,
            'search_request_id' => $this->search_request_id,
            'domain_id' => $this->domain_id,
            'json_data' => $this->json_data,
            'time_from' => $this->time_from,
            'time_to' => $this->time_to,
            'version' => $this->version,
        ];
    }
}