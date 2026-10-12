<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    public function testDottedAccessAndDefaults(): void
    {
        $config = new Config(['app' => ['url' => 'https://x.cz', 'env' => 'development', 'debug' => true]]);
        self::assertSame('https://x.cz', $config->get('app.url'));
        self::assertSame('výchozí', $config->get('neni.tu', 'výchozí'));
        self::assertTrue($config->isHttps());
        self::assertTrue($config->isDebug());
    }

    public function testDebugIsAlwaysOffInProduction(): void
    {
        $config = new Config(['app' => ['url' => 'https://x.cz', 'env' => 'production', 'debug' => true]]);
        self::assertFalse($config->isDebug());
    }

    public function testMissingRequiredKeyFails(): void
    {
        $this->expectException(RuntimeException::class);
        new Config(['app' => ['env' => 'production']]);
    }

    public function testUnknownEnvironmentFails(): void
    {
        $this->expectException(RuntimeException::class);
        new Config(['app' => ['url' => 'https://x.cz', 'env' => 'staging']]);
    }

    public function testExplicitConfigPathMustExist(): void
    {
        putenv('BSPIZ_CONFIG=/neexistuje/config.php');
        try {
            $this->expectException(RuntimeException::class);
            Config::locate(dirname(__DIR__, 2));
        } finally {
            putenv('BSPIZ_CONFIG');
        }
    }

    public function testExampleConfigIsValid(): void
    {
        $config = Config::fromFile(dirname(__DIR__, 2) . '/config/config.example.php');
        self::assertSame('development', $config->get('app.env'));
        self::assertSame('', $config->get('logto.app_secret'), 'vzor nesmí obsahovat tajemství');
    }
}
