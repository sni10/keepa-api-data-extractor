<?php

namespace App\Tests\Unit\Service;

use App\Dto\KeepaInputDto;
use App\Service\FinderProductsService;
use App\Service\KeepaService;
use App\Service\Validator\MessageValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class FinderProductsServiceTest extends TestCase
{
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var KeepaService&MockObject */
    private KeepaService $keepaService;

    /** @var MessageValidator&MockObject */
    private MessageValidator $messageValidator;

    /** @var MessageBusInterface&MockObject */
    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->keepaService = $this->createMock(KeepaService::class);
        $this->messageValidator = $this->createMock(MessageValidator::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
    }

    public function testExecuteReturnsDefaultStatsWhenInputIsInvalid(): void
    {
        $dto = KeepaInputDto::fromArray([
            'id' => 1,
            'domain_id' => 1,
            'brand' => 'adidas',
            'time_from' => '2025-01-01',
            'time_to' => '2025-01-31',
            'version' => 1,
            'status' => 'PENDING',
            'step' => 0,
        ]);

        $this->messageValidator
            ->expects(self::once())
            ->method('validateInput')
            ->with($dto)
            ->willReturn(null);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('Invalid Input Kafka message'),
                self::anything(),
            );

        $this->keepaService
            ->expects(self::never())
            ->method('execute');

        $this->bus
            ->expects(self::never())
            ->method('dispatch');

        $service = new FinderProductsService(
            $this->logger,
            $this->keepaService,
            $this->messageValidator,
            $this->bus,
        );

        $result = $service->execute($dto);

        self::assertSame(
            [
                'asinsFound' => 0,
                'tokensUsed' => 0,
                'pages' => 0,
                'sleepSeconds' => 0,
            ],
            $result,
        );
    }
}
