<?php

namespace App\Tests\Unit\Service;

use App\Service\KeepaService;
use Keepa\API\Request;
use Keepa\API\Response;
use Keepa\API\ResponseStatus;
use Keepa\KeepaAPI;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

class KeepaServiceTest extends TestCase
{
    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $tokenLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->tokenLogger = $this->createMock(LoggerInterface::class);

        // Set environment variables for tests
        putenv('KEEPA_TOKEN_MIN_LIMIT=100');
        putenv('KEEPA_TOKEN_WAIT_TIMEOUT=60');
        putenv('KEEPA_TOKEN_MAX_RETRIES=5');
        putenv('KEEPA_REQUEST_MAX_RETRIES=5');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up environment variables
        putenv('KEEPA_TOKEN_MIN_LIMIT');
        putenv('KEEPA_TOKEN_WAIT_TIMEOUT');
        putenv('KEEPA_TOKEN_MAX_RETRIES');
        putenv('KEEPA_REQUEST_MAX_RETRIES');
    }

    public function testGetStatsReturnsInitialEmptyStats(): void
    {
        $service = new KeepaService(
            'test-api-key',
            $this->tokenLogger,
            $this->logger
        );

        $stats = $service->getStats();

        self::assertIsArray($stats);
        self::assertArrayHasKey('tokensUsed', $stats);
        self::assertArrayHasKey('pagesProcessed', $stats);
        self::assertArrayHasKey('asinsFound', $stats);
        self::assertArrayHasKey('sleepSeconds', $stats);
        self::assertArrayHasKey('tokensLeft', $stats);
        self::assertArrayHasKey('pageStats', $stats);

        self::assertSame(0, $stats['tokensUsed']);
        self::assertSame(0, $stats['pagesProcessed']);
        self::assertSame(0, $stats['asinsFound']);
        self::assertSame(0, $stats['sleepSeconds']);
        self::assertNull($stats['tokensLeft']);
        self::assertSame([], $stats['pageStats']);
    }

    public function testGetRemainingTokensReturnsZeroOnException(): void
    {
        $mockApi = $this->createMock(KeepaAPI::class);
        $mockApi
            ->expects(self::once())
            ->method('sendRequest')
            ->willThrowException(new \Exception('Network error'));

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('Исключение при получении статуса токенов'),
                self::anything()
            );

        $service = new KeepaService(
            'test-api-key',
            $this->tokenLogger,
            $this->logger
        );

        // Inject mock API using reflection
        $reflection = new ReflectionClass($service);
        $apiProperty = $reflection->getProperty('api');
        $apiProperty->setAccessible(true);
        $apiProperty->setValue($service, $mockApi);

        $tokens = $service->getRemainingTokens();

        self::assertSame(0, $tokens);
    }

    public function testGetRemainingTokensReturnsZeroWhenResponseIsNull(): void
    {
        $mockApi = $this->createMock(KeepaAPI::class);
        $mockApi
            ->expects(self::once())
            ->method('sendRequest')
            ->willReturn(null);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('Не удалось получить статус токенов');

        $service = new KeepaService(
            'test-api-key',
            $this->tokenLogger,
            $this->logger
        );

        // Inject mock API using reflection
        $reflection = new ReflectionClass($service);
        $apiProperty = $reflection->getProperty('api');
        $apiProperty->setAccessible(true);
        $apiProperty->setValue($service, $mockApi);

        $tokens = $service->getRemainingTokens();

        self::assertSame(0, $tokens);
    }

    public function testGetRemainingTokensReturnsZeroWhenStatusIsNotOk(): void
    {
        $mockResponse = $this->createMock(Response::class);
        $mockResponse->status = ResponseStatus::FAIL;
        $mockResponse->tokensLeft = 100; // Should be ignored

        $mockApi = $this->createMock(KeepaAPI::class);
        $mockApi
            ->expects(self::once())
            ->method('sendRequest')
            ->willReturn($mockResponse);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('Не удалось получить статус токенов');

        $service = new KeepaService(
            'test-api-key',
            $this->tokenLogger,
            $this->logger
        );

        // Inject mock API using reflection
        $reflection = new ReflectionClass($service);
        $apiProperty = $reflection->getProperty('api');
        $apiProperty->setAccessible(true);
        $apiProperty->setValue($service, $mockApi);

        $tokens = $service->getRemainingTokens();

        self::assertSame(0, $tokens);
    }

    public function testGetRemainingTokensReturnsCorrectValueWhenSuccessful(): void
    {
        $mockResponse = $this->createMock(Response::class);
        $mockResponse->status = ResponseStatus::OK;
        $mockResponse->tokensLeft = 500;

        $mockApi = $this->createMock(KeepaAPI::class);
        $mockApi
            ->expects(self::once())
            ->method('sendRequest')
            ->with(self::isInstanceOf(Request::class))
            ->willReturn($mockResponse);

        $service = new KeepaService(
            'test-api-key',
            $this->tokenLogger,
            $this->logger
        );

        // Inject mock API using reflection
        $reflection = new ReflectionClass($service);
        $apiProperty = $reflection->getProperty('api');
        $apiProperty->setAccessible(true);
        $apiProperty->setValue($service, $mockApi);

        $tokens = $service->getRemainingTokens();

        self::assertSame(500, $tokens);
    }

    public function testGetRemainingTokensHandlesNullTokensLeft(): void
    {
        $mockResponse = $this->createMock(Response::class);
        $mockResponse->status = ResponseStatus::OK;
        $mockResponse->tokensLeft = null;

        $mockApi = $this->createMock(KeepaAPI::class);
        $mockApi
            ->expects(self::once())
            ->method('sendRequest')
            ->willReturn($mockResponse);

        $service = new KeepaService(
            'test-api-key',
            $this->tokenLogger,
            $this->logger
        );

        // Inject mock API using reflection
        $reflection = new ReflectionClass($service);
        $apiProperty = $reflection->getProperty('api');
        $apiProperty->setAccessible(true);
        $apiProperty->setValue($service, $mockApi);

        $tokens = $service->getRemainingTokens();

        self::assertSame(0, $tokens);
    }

    public function testGetStatsContainsCorrectStructure(): void
    {
        $service = new KeepaService(
            'test-api-key',
            $this->tokenLogger,
            $this->logger
        );

        $stats = $service->getStats();

        // Verify all required keys are present
        $requiredKeys = [
            'tokensUsed',
            'pagesProcessed',
            'asinsFound',
            'sleepSeconds',
            'tokensLeft',
            'pageStats',
        ];

        foreach ($requiredKeys as $key) {
            self::assertArrayHasKey($key, $stats, "Stats array should contain key: {$key}");
        }
    }

    public function testGetRemainingTokensCastsToInteger(): void
    {
        $mockResponse = $this->createMock(Response::class);
        $mockResponse->status = ResponseStatus::OK;
        $mockResponse->tokensLeft = '750'; // String value

        $mockApi = $this->createMock(KeepaAPI::class);
        $mockApi
            ->expects(self::once())
            ->method('sendRequest')
            ->willReturn($mockResponse);

        $service = new KeepaService(
            'test-api-key',
            $this->tokenLogger,
            $this->logger
        );

        // Inject mock API using reflection
        $reflection = new ReflectionClass($service);
        $apiProperty = $reflection->getProperty('api');
        $apiProperty->setAccessible(true);
        $apiProperty->setValue($service, $mockApi);

        $tokens = $service->getRemainingTokens();

        self::assertIsInt($tokens);
        self::assertSame(750, $tokens);
    }
}
