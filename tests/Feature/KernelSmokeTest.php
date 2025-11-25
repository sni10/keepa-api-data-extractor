<?php

namespace App\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KernelSmokeTest extends KernelTestCase
{
    public function testKernelBootsInTestEnvironment(): void
    {
        fwrite(STDERR, sprintf(
            "\nDEBUG ENV:\ngetenv: %s\n_ENV: %s\n_SERVER: %s\n",
            getenv('APP_ENV'),
            $_ENV['APP_ENV'] ?? 'NULL',
            $_SERVER['APP_ENV'] ?? 'NULL'
        ));

        $kernel = self::bootKernel();

        self::assertSame('test', $kernel->getEnvironment());

        $container = self::getContainer();
        self::assertNotNull($container);

        // Базовая проверка доступности сервисов фреймворка
        self::assertTrue($container->has('validator'));
    }
}
