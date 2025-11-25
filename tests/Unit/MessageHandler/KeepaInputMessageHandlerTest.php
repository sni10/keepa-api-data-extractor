<?php

namespace App\Tests\Unit\MessageHandler;

use App\Dto\KeepaInputDto;
use App\MessageHandler\KeepaInputMessageHandler;
use App\Service\FinderProductsService;
use App\Service\KeepaService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class KeepaInputMessageHandlerTest extends TestCase
{
    /** @var FinderProductsService&MockObject */
    private FinderProductsService $finderProductsService;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var KeepaService&MockObject */
    private KeepaService $keepaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->finderProductsService = $this->createMock(FinderProductsService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->keepaService = $this->createMock(KeepaService::class);
    }

    public function testInvokeLogsStartAndCompletionWithStats(): void
    {
        $message = KeepaInputDto::fromArray([
            'id' => 1,
            'domain_id' => 1,
            'brand' => 'Nike',
            'time_from' => '2025-01-01',
            'time_to' => '2025-01-31',
            'status' => 'PENDING',
            'step' => 0,
        ]);

        $this->finderProductsService
            ->expects(self::once())
            ->method('getKeepaService')
            ->willReturn($this->keepaService);

        $this->keepaService
            ->expects(self::once())
            ->method('getRemainingTokens')
            ->willReturn(500);

        $this->finderProductsService
            ->expects(self::once())
            ->method('execute')
            ->with($message)
            ->willReturn([
                'asinsFound' => 10,
                'tokensUsed' => 50,
                'pages' => 2,
                'sleepSeconds' => 5,
                'tokensLeft' => 450,
            ]);

        $logMessages = [];
        $this->logger
            ->expects(self::exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message) use (&$logMessages) {
                $logMessages[] = $message;
            });

        $handler = new KeepaInputMessageHandler(
            $this->finderProductsService,
            $this->logger
        );

        $handler->__invoke($message);

        self::assertStringContainsString('Starting search', $logMessages[0]);
        self::assertStringContainsString('Search completed', $logMessages[1]);
    }

    public function testInvokeHandlesZeroResults(): void
    {
        $message = KeepaInputDto::fromArray([
            'id' => 2,
            'domain_id' => 3,
            'brand' => 'NonExistentBrand',
            'time_from' => '2025-02-01',
            'time_to' => '2025-02-28',
            'status' => 'IN_PROGRESS',
            'step' => 1,
        ]);

        $this->finderProductsService
            ->expects(self::once())
            ->method('getKeepaService')
            ->willReturn($this->keepaService);

        $this->keepaService
            ->expects(self::once())
            ->method('getRemainingTokens')
            ->willReturn(300);

        $this->finderProductsService
            ->expects(self::once())
            ->method('execute')
            ->with($message)
            ->willReturn([
                'asinsFound' => 0,
                'tokensUsed' => 10,
                'pages' => 1,
                'sleepSeconds' => 0,
                'tokensLeft' => 290,
            ]);

        $this->logger
            ->expects(self::exactly(2))
            ->method('info');

        $handler = new KeepaInputMessageHandler(
            $this->finderProductsService,
            $this->logger
        );

        $handler->__invoke($message);
    }

    public function testInvokeCalculatesTokensPerSecond(): void
    {
        $message = KeepaInputDto::fromArray([
            'id' => 3,
            'domain_id' => 1,
            'brand' => 'Adidas',
            'time_from' => '2025-03-01',
            'time_to' => '2025-03-31',
            'status' => 'PENDING',
            'step' => 0,
        ]);

        $this->finderProductsService
            ->expects(self::once())
            ->method('getKeepaService')
            ->willReturn($this->keepaService);

        $this->keepaService
            ->expects(self::once())
            ->method('getRemainingTokens')
            ->willReturn(1000);

        $this->finderProductsService
            ->expects(self::once())
            ->method('execute')
            ->with($message)
            ->willReturn([
                'asinsFound' => 50,
                'tokensUsed' => 100,
                'pages' => 5,
                'sleepSeconds' => 10,
                'tokensLeft' => 900,
            ]);

        $logMessages = [];
        $this->logger
            ->expects(self::exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message) use (&$logMessages) {
                $logMessages[] = $message;
            });

        $handler = new KeepaInputMessageHandler(
            $this->finderProductsService,
            $this->logger
        );

        $handler->__invoke($message);

        // Check second log message contains tokens/min calculation
        self::assertStringContainsString('Tokens/min:', $logMessages[1]);
    }

    public function testInvokeHandlesMissingStatsFields(): void
    {
        $message = KeepaInputDto::fromArray([
            'id' => 4,
            'domain_id' => 2,
            'brand' => 'Puma',
            'time_from' => '2025-04-01',
            'time_to' => '2025-04-30',
            'status' => 'COMPLETED',
            'step' => 3,
        ]);

        $this->finderProductsService
            ->expects(self::once())
            ->method('getKeepaService')
            ->willReturn($this->keepaService);

        $this->keepaService
            ->expects(self::once())
            ->method('getRemainingTokens')
            ->willReturn(200);

        // Return stats with some missing fields
        $this->finderProductsService
            ->expects(self::once())
            ->method('execute')
            ->with($message)
            ->willReturn([
                'asinsFound' => 5,
                'tokensUsed' => 20,
                // missing 'pages', 'sleepSeconds', 'tokensLeft'
            ]);

        $logMessages = [];
        $this->logger
            ->expects(self::exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message) use (&$logMessages) {
                $logMessages[] = $message;
            });

        $handler = new KeepaInputMessageHandler(
            $this->finderProductsService,
            $this->logger
        );

        $handler->__invoke($message);

        // Check second log message handles missing stats gracefully (default to 0)
        $completionLog = $logMessages[1];
        self::assertStringContainsString('Pages: 0', $completionLog);
        self::assertStringContainsString('idle: 0 s', $completionLog);
        self::assertStringContainsString('Tokens left: 0', $completionLog);
    }

    public function testInvokeFormatsLogMessageCorrectly(): void
    {
        $message = KeepaInputDto::fromArray([
            'id' => 5,
            'domain_id' => 4,
            'brand' => 'Reebok',
            'time_from' => '2025-05-01',
            'time_to' => '2025-05-31',
            'status' => 'FAILED',
            'step' => 2,
        ]);

        $this->finderProductsService
            ->expects(self::once())
            ->method('getKeepaService')
            ->willReturn($this->keepaService);

        $this->keepaService
            ->expects(self::once())
            ->method('getRemainingTokens')
            ->willReturn(150);

        $this->finderProductsService
            ->expects(self::once())
            ->method('execute')
            ->with($message)
            ->willReturn([
                'asinsFound' => 3,
                'tokensUsed' => 15,
                'pages' => 1,
                'sleepSeconds' => 2,
                'tokensLeft' => 135,
            ]);

        $logMessages = [];
        $this->logger
            ->expects(self::exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message) use (&$logMessages) {
                $logMessages[] = $message;
            });

        $handler = new KeepaInputMessageHandler(
            $this->finderProductsService,
            $this->logger
        );

        $handler->__invoke($message);

        // Check first log message (start)
        $startLog = $logMessages[0];
        self::assertStringContainsString('Starting search for brand: Reebok', $startLog);
        self::assertStringContainsString('date range: 2025-05-01 - 2025-05-31', $startLog);
        self::assertStringContainsString('initial tokens: 150', $startLog);

        // Check second log message (completion)
        $completionLog = $logMessages[1];
        self::assertStringContainsString('Search completed - Brand: Reebok', $completionLog);
        self::assertStringContainsString('date range: 2025-05-01 - 2025-05-31', $completionLog);
        self::assertStringContainsString('Pages: 1', $completionLog);
        self::assertStringContainsString('ASINs: 3', $completionLog);
        self::assertStringContainsString('Tokens used: 15', $completionLog);
        self::assertStringContainsString('Tokens left: 135', $completionLog);
    }
}
