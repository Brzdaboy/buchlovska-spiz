<?php

declare(strict_types=1);

namespace Tests;

use App\App;
use App\Security\Session;
use App\Support\Config;
use App\Support\HttpClient;
use App\Support\Logger;

/** Sestaví aplikaci pro testy: session v poli, logy do dočasné složky. */
final class AppFactory
{
    /** @var array<string, mixed> */
    public static array $sessionData = [];

    /** @param array<string, mixed> $overrides */
    public static function config(array $overrides = []): Config
    {
        return new Config(array_replace_recursive([
            'app' => ['url' => 'https://spiz.test', 'env' => 'test', 'debug' => false],
            'db' => ['host' => '127.0.0.1', 'name' => 'test', 'user' => 'test', 'pass' => ''],
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    public static function create(array $overrides = [], ?HttpClient $http = null): App
    {
        self::$sessionData = [];
        $data = &self::$sessionData;
        return new App(
            self::config($overrides),
            new Logger(sys_get_temp_dir() . '/bspiz-test-logs'),
            static function () use (&$data): Session {
                return new Session($data);
            },
            dirname(__DIR__),
            $http,
        );
    }
}
