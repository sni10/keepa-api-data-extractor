<?php

namespace App\Tests\Unit\Exception;

use App\Exception\KeepaRequestFailedException;
use App\Exception\KeepaTokenTimeoutException;
use App\Exception\NotEnoughTokenError;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{
    public function testKeepaRequestFailedExceptionCanBeCreated(): void
    {
        $exception = new KeepaRequestFailedException('Request failed');

        self::assertInstanceOf(\Exception::class, $exception);
        self::assertInstanceOf(KeepaRequestFailedException::class, $exception);
        self::assertSame('Request failed', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
    }

    public function testKeepaRequestFailedExceptionWithCodeAndPrevious(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new KeepaRequestFailedException('Request failed', 500, $previous);

        self::assertSame('Request failed', $exception->getMessage());
        self::assertSame(500, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testKeepaRequestFailedExceptionCanBeThrown(): void
    {
        $this->expectException(KeepaRequestFailedException::class);
        $this->expectExceptionMessage('API error occurred');

        throw new KeepaRequestFailedException('API error occurred');
    }

    public function testNotEnoughTokenErrorCanBeCreated(): void
    {
        $exception = new NotEnoughTokenError('Not enough tokens available');

        self::assertInstanceOf(\Exception::class, $exception);
        self::assertInstanceOf(NotEnoughTokenError::class, $exception);
        self::assertSame('Not enough tokens available', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
    }

    public function testNotEnoughTokenErrorWithCodeAndPrevious(): void
    {
        $previous = new \RuntimeException('Token limit reached');
        $exception = new NotEnoughTokenError('Not enough tokens', 429, $previous);

        self::assertSame('Not enough tokens', $exception->getMessage());
        self::assertSame(429, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testNotEnoughTokenErrorCanBeThrown(): void
    {
        $this->expectException(NotEnoughTokenError::class);
        $this->expectExceptionMessage('Tokens depleted');

        throw new NotEnoughTokenError('Tokens depleted');
    }

    public function testKeepaTokenTimeoutExceptionCanBeCreated(): void
    {
        $exception = new KeepaTokenTimeoutException('Token timeout');

        self::assertInstanceOf(\Exception::class, $exception);
        self::assertInstanceOf(KeepaTokenTimeoutException::class, $exception);
        self::assertSame('Token timeout', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
    }

    public function testKeepaTokenTimeoutExceptionWithCodeAndPrevious(): void
    {
        $previous = new \RuntimeException('Timeout occurred');
        $exception = new KeepaTokenTimeoutException('Token wait timeout', 408, $previous);

        self::assertSame('Token wait timeout', $exception->getMessage());
        self::assertSame(408, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    public function testKeepaTokenTimeoutExceptionCanBeThrown(): void
    {
        $this->expectException(KeepaTokenTimeoutException::class);
        $this->expectExceptionMessage('Waited too long for tokens');

        throw new KeepaTokenTimeoutException('Waited too long for tokens');
    }

    public function testExceptionsAreDistinct(): void
    {
        $exception1 = new KeepaRequestFailedException('Error 1');
        $exception2 = new NotEnoughTokenError('Error 2');
        $exception3 = new KeepaTokenTimeoutException('Error 3');

        self::assertNotInstanceOf(NotEnoughTokenError::class, $exception1);
        self::assertNotInstanceOf(KeepaTokenTimeoutException::class, $exception1);

        self::assertNotInstanceOf(KeepaRequestFailedException::class, $exception2);
        self::assertNotInstanceOf(KeepaTokenTimeoutException::class, $exception2);

        self::assertNotInstanceOf(KeepaRequestFailedException::class, $exception3);
        self::assertNotInstanceOf(NotEnoughTokenError::class, $exception3);
    }

    public function testExceptionMessagesCanBeEmpty(): void
    {
        $exception1 = new KeepaRequestFailedException('');
        $exception2 = new NotEnoughTokenError('');
        $exception3 = new KeepaTokenTimeoutException('');

        self::assertSame('', $exception1->getMessage());
        self::assertSame('', $exception2->getMessage());
        self::assertSame('', $exception3->getMessage());
    }

    public function testExceptionsCanHaveCustomCodes(): void
    {
        $exception1 = new KeepaRequestFailedException('Request failed', 1001);
        $exception2 = new NotEnoughTokenError('No tokens', 1002);
        $exception3 = new KeepaTokenTimeoutException('Timeout', 1003);

        self::assertSame(1001, $exception1->getCode());
        self::assertSame(1002, $exception2->getCode());
        self::assertSame(1003, $exception3->getCode());
    }

    public function testExceptionsCatchAsGenericException(): void
    {
        try {
            throw new KeepaRequestFailedException('Test');
        } catch (\Exception $e) {
            self::assertInstanceOf(KeepaRequestFailedException::class, $e);
        }

        try {
            throw new NotEnoughTokenError('Test');
        } catch (\Exception $e) {
            self::assertInstanceOf(NotEnoughTokenError::class, $e);
        }

        try {
            throw new KeepaTokenTimeoutException('Test');
        } catch (\Exception $e) {
            self::assertInstanceOf(KeepaTokenTimeoutException::class, $e);
        }
    }
}
