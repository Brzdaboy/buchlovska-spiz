<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Spustí aplikaci ve vestavěném serveru PHP a ověří ji přes skutečné HTTP:
 * front controller, statické soubory, cookies session, CSRF a chybové stavy.
 */
final class BuiltinServerTest extends TestCase
{
    /** @var resource|null */
    private static $process = null;
    private static string $base = '';
    private static string $configFile = '';

    public static function setUpBeforeClass(): void
    {
        self::$configFile = tempnam(sys_get_temp_dir(), 'bspiz-config') . '.php';
        file_put_contents(self::$configFile, "<?php return ['app' => ['url' => 'http://127.0.0.1', 'env' => 'test', 'debug' => false]];");
        self::$process = self::startServer(self::$configFile, $port);
        self::$base = 'http://127.0.0.1:' . $port;
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }
        @unlink(self::$configFile);
    }

    /** @return resource */
    private static function startServer(string $configFile, ?int &$port)
    {
        $root = dirname(__DIR__, 2);
        $port = random_int(20000, 40000);
        $env = array_merge(getenv(), ['BSPIZ_CONFIG' => $configFile]);
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            $root,
            $env
        );
        self::assertIsResource($process);
        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', $port);
            if ($socket) {
                fclose($socket);
                return $process;
            }
            usleep(100_000);
        }
        self::fail('Vestavěný server se nespustil.');
    }

    /**
     * @param array<string, string> $form
     * @return array{status: int, headers: list<string>, body: string}
     */
    private static function request(string $method, string $path, array $form = [], string $cookie = ''): array
    {
        $headers = $cookie !== '' ? "Cookie: {$cookie}\r\n" : '';
        $content = '';
        if ($form !== []) {
            $content = http_build_query($form);
            $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $content,
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 5,
        ]]);
        $body = (string) file_get_contents(self::$base . $path, false, $context);
        /** @var list<string> $responseHeaders */
        $responseHeaders = $http_response_header ?? [];
        preg_match('#HTTP/\S+ (\d{3})#', $responseHeaders[0] ?? '', $m);
        return ['status' => (int) ($m[1] ?? 0), 'headers' => $responseHeaders, 'body' => $body];
    }

    /** @param list<string> $headers */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $line) {
            if (stripos($line, $name . ':') === 0) {
                return trim(substr($line, strlen($name) + 1));
            }
        }
        return null;
    }

    public function testHomepage(): void
    {
        $r = self::request('GET', '/');
        self::assertSame(200, $r['status']);
        self::assertStringContainsString('<html lang="cs">', $r['body']);
        self::assertNotNull(self::header($r['headers'], 'Content-Security-Policy'));
        self::assertNull(self::header($r['headers'], 'X-Powered-By'), 'verze PHP se neprozrazuje');
    }

    public function testSessionCookieFlags(): void
    {
        $cookie = self::header(self::request('GET', '/')['headers'], 'Set-Cookie');
        self::assertNotNull($cookie);
        self::assertStringContainsStringIgnoringCase('HttpOnly', $cookie);
        self::assertStringContainsStringIgnoringCase('SameSite=Lax', $cookie);
    }

    public function testStaticAssetIsServed(): void
    {
        $r = self::request('GET', '/assets/css/app.css');
        self::assertSame(200, $r['status']);
        self::assertStringContainsString('--green', $r['body']);
    }

    public function testNotFound(): void
    {
        self::assertSame(404, self::request('GET', '/neexistuje')['status']);
    }

    public function testCsrfOverRealHttp(): void
    {
        self::assertSame(419, self::request('POST', '/', ['x' => '1'])['status'], 'bez tokenu');

        $first = self::request('GET', '/');
        $cookie = explode(';', (string) self::header($first['headers'], 'Set-Cookie'))[0];
        // Token si vezmeme ze session přes druhý požadavek se stejnou cookie:
        // šablona ho zatím nevypisuje, proto ověříme jen, že cizí token neprojde.
        self::assertSame(419, self::request('POST', '/', ['_csrf' => str_repeat('a', 64)], $cookie)['status']);
    }

    public function testMissingConfigShowsGeneric503(): void
    {
        $process = self::startServer('/neexistuje/config.php', $port);
        try {
            $body = (string) @file_get_contents('http://127.0.0.1:' . $port . '/', false, stream_context_create(['http' => ['ignore_errors' => true]]));
            /** @var list<string> $headers */
            $headers = $http_response_header ?? [];
            self::assertStringContainsString('503', $headers[0] ?? '');
            self::assertStringNotContainsString('neexistuje', $body, 'cesta ke konfiguraci neunikne');
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }
}
