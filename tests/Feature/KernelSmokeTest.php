<?php

namespace App\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KernelSmokeTest extends KernelTestCase
{
    public function testKernelBootsInTestEnvironment(): void
    {
        $kernel = self::bootKernel();

        self::assertSame('test', $kernel->getEnvironment());

        $container = self::getContainer();
        self::assertNotNull($container);

        // Базовая проверка доступности сервисов фреймворка
        self::assertTrue($container->has('validator'));
    }
}
