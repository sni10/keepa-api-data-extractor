<?php

namespace App\MessageHandler;

use App\Dto\KeepaInputDto;
use App\Service\FinderProductsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsMessageHandler]
final readonly class KeepaInputMessageHandler
{
    public function __construct(
        private FinderProductsService $finderProductsService,
        private LoggerInterface       $logger
    )
    {
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(KeepaInputDto $message): void
    {
        $startTime = new \DateTime();
        $initialTokens = $this->finderProductsService->getKeepaService()->getRemainingTokens();

        $this->logger->info(sprintf(
            'Starting search for brand: %s, date range: %s, initial tokens: %d, start time: %s',
            $message->getBrand(),
            $message->getTimeRange(),
            $initialTokens,
            $startTime->format('Y-m-d H:i:s')
        ));

        $result = $this->finderProductsService->execute($message);
        $endTime = new \DateTime();
        $timeSpent = $endTime->getTimestamp() - $startTime->getTimestamp();
        $tokensUsed = ($result['tokensUsed'] ?? 0);
        $asinsFound = ($result['asinsFound'] ?? 0);
        $pagesProcessed = ($result['pages'] ?? 0);
        $sleepSeconds = ($result['sleepSeconds'] ?? 0);
        $tokensLeft = ($result['tokensLeft'] ?? 0);
        $tokensPerSecond = $timeSpent > 0
            ? ($tokensUsed / $timeSpent)
            : 0;


        $this->logger->info(sprintf(
            'Search completed - Brand: %s, date range: %s, Time: %d s (idle: %d s), Pages: %d, ASINs: %d, Tokens used: %d, Tokens left: %d, Tokens/min: %.2f',
            $message->getBrand(),
            $message->getTimeRange(),
            $timeSpent,
            $sleepSeconds,
            $pagesProcessed,
            $asinsFound,
            $tokensUsed,
            $tokensLeft,
            $tokensPerSecond
        ));
    }
}
