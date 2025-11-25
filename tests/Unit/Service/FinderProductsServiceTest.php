<?php

namespace App\Tests\Unit\Service;

use App\Dto\KeepaInputDto;
use App\Dto\KeepaOutputDto;
use App\Service\FinderProductsService;
use App\Service\KeepaService;
use App\Service\Validator\MessageValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;

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

    public function testExecuteDispatchesValidOutputsAndReturnsMappedStats(): void
    {
        $inputDto = KeepaInputDto::fromArray([
            'id' => 2,
            'domain_id' => 3,
            'brand' => 'nike',
            'time_from' => '2025-02-01',
            'time_to' => '2025-02-28',
            'version' => 1,
            'status' => 'IN_PROGRESS',
            'step' => 1,
        ]);

        $validatedInput = clone $inputDto;

        $this->messageValidator
            ->expects(self::once())
            ->method('validateInput')
            ->with($inputDto)
            ->willReturn($validatedInput);

        $product = new KeepaOutputDto();
        $product->asin = 'B000TEST00';

        $generator = (function () use ($product) {
            yield $product;
        })();

        $this->keepaService
            ->expects(self::once())
            ->method('execute')
            ->with($validatedInput)
            ->willReturn($generator);

        $validatedOutput = clone $product;

        $this->messageValidator
            ->expects(self::once())
            ->method('validateOutput')
            ->with($product)
            ->willReturn($validatedOutput);

        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with($validatedOutput)
            ->willReturn(new Envelope($validatedOutput));

        $this->keepaService
            ->expects(self::once())
            ->method('getStats')
            ->willReturn([
                'asinsFound' => 5,
                'tokensUsed' => 42,
                'pagesProcessed' => 3,
                'sleepSeconds' => 7,
                'tokensLeft' => 100,
            ]);

        $service = new FinderProductsService(
            $this->logger,
            $this->keepaService,
            $this->messageValidator,
            $this->bus,
        );

        $result = $service->execute($inputDto);

        self::assertSame(
            [
                'asinsFound' => 5,
                'tokensUsed' => 42,
                'pages' => 3,
                'sleepSeconds' => 7,
                'tokensLeft' => 100,
            ],
            $result,
        );
    }

    public function testGetKeepaServiceReturnsInjectedInstance(): void
    {
        $service = new FinderProductsService(
            $this->logger,
            $this->keepaService,
            $this->messageValidator,
            $this->bus,
        );

        self::assertSame($this->keepaService, $service->getKeepaService());
    }

    public function testExecuteSkipsInvalidOutputsAndContinues(): void
    {
        $inputDto = KeepaInputDto::fromArray([
            'id' => 3,
            'domain_id' => 1,
            'brand' => 'Reebok',
            'time_from' => '2025-03-01',
            'time_to' => '2025-03-31',
            'version' => 1,
            'status' => 'IN_PROGRESS',
            'step' => 2,
        ]);

        $validatedInput = clone $inputDto;

        $this->messageValidator
            ->expects(self::once())
            ->method('validateInput')
            ->with($inputDto)
            ->willReturn($validatedInput);

        $product1 = new KeepaOutputDto();
        $product1->asin = 'B000INVALID';

        $product2 = new KeepaOutputDto();
        $product2->asin = 'B000VALID00';

        $generator = (function () use ($product1, $product2) {
            yield $product1;
            yield $product2;
        })();

        $this->keepaService
            ->expects(self::once())
            ->method('execute')
            ->with($validatedInput)
            ->willReturn($generator);

        $this->messageValidator
            ->expects(self::exactly(2))
            ->method('validateOutput')
            ->willReturnCallback(function ($product) use ($product1, $product2) {
                if ($product === $product1) {
                    return null; // invalid
                }
                return clone $product2; // valid
            });

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('Invalid Output Kafka message'),
                self::anything()
            );

        $this->bus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(KeepaOutputDto::class))
            ->willReturn(new Envelope($product2));

        $this->keepaService
            ->expects(self::once())
            ->method('getStats')
            ->willReturn([
                'asinsFound' => 2,
                'tokensUsed' => 20,
                'pagesProcessed' => 1,
                'sleepSeconds' => 0,
                'tokensLeft' => 80,
            ]);

        $service = new FinderProductsService(
            $this->logger,
            $this->keepaService,
            $this->messageValidator,
            $this->bus,
        );

        $result = $service->execute($inputDto);

        self::assertSame(
            [
                'asinsFound' => 2,
                'tokensUsed' => 20,
                'pages' => 1,
                'sleepSeconds' => 0,
                'tokensLeft' => 80,
            ],
            $result,
        );
    }
}
