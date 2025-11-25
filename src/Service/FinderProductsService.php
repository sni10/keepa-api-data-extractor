<?php

namespace App\Service;

use AllowDynamicProperties;
use App\Dto\KeepaInputDto;
use App\Service\Validator\MessageValidator;
use Symfony\Component\Messenger\MessageBusInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AllowDynamicProperties]
class FinderProductsService
{

    private KeepaService $keepaService;
    private LoggerInterface $logger;
    private MessageBusInterface $bus;
    private MessageValidator $messageValidator;

    public function __construct(
        LoggerInterface $logger,
        KeepaService    $keepaService,
        MessageValidator    $messageValidator,
        MessageBusInterface    $bus
    )
    {
        $this->logger = $logger;
        $this->messageValidator = $messageValidator;
        $this->bus = $bus;
        $this->keepaService = $keepaService;
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function execute(KeepaInputDto $message): array
    {
        $dtoMessage = $this->messageValidator->validateInput($message);
        if (!$dtoMessage) {
            $this->logger->warning("Invalid Input Kafka message. SKIP Input/", [$dtoMessage]);
            return ['asinsFound' => 0, 'tokensUsed' => 0, 'pages' => 0, 'sleepSeconds' => 0];
        }

        foreach ($this->keepaService->execute($dtoMessage) as $product) {
            $dtoOutput = $this->messageValidator->validateOutput($product);
            if (!$dtoOutput) {
                $this->logger->warning("Invalid Output Kafka message. SKIP Output/", [$dtoMessage]);
                continue;
            }
            $this->bus->dispatch($dtoOutput);
        }

        $stats = $this->keepaService->getStats();

        return [
            'asinsFound'   => (int)($stats['asinsFound'] ?? 0),
            'tokensUsed'   => (int)($stats['tokensUsed'] ?? 0),
            'pages'        => (int)($stats['pagesProcessed'] ?? 0),
            'sleepSeconds' => (int)($stats['sleepSeconds'] ?? 0),
            'tokensLeft'   => (int)($stats['tokensLeft'] ?? 0),
        ];
    }

    public function getKeepaService(): KeepaService
    {
        return $this->keepaService;
    }
}