<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Request;
use App\Security\Csrf;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\AppFactory;

final class AppTest extends TestCase
{
    public function testHomepageRendersWithSecurityHeaders(): void
    {
        $response = AppFactory::create()->handle(new Request('GET', '/'));

        self::assertSame(200, $response->status);
        self::assertStringContainsString('Buchlovská spíž', $response->body);
        self::assertStringContainsString("frame-ancestors 'none'", $response->headers['Content-Security-Policy']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        self::assertSame('DENY', $response->headers['X-Frame-Options']);
        self::assertStringStartsWith('max-age=', $response->headers['Strict-Transport-Security']);
    }

    public function testLogtoOriginIsAllowedAsFormActionOnly(): void
    {
        $response = AppFactory::create(['logto' => ['endpoint' => 'https://abc123.logto.app/']])->handle(new Request('GET', '/'));
        $csp = $response->headers['Content-Security-Policy'];
        self::assertStringContainsString("form-action 'self' https://abc123.logto.app;", $csp);
        self::assertStringContainsString("script-src 'self';", $csp);
    }

    public function testPlaceholderLogtoEndpointIsIgnored(): void
    {
        $response = AppFactory::create(['logto' => ['endpoint' => 'https://<tenant-id>.logto.app']])->handle(new Request('GET', '/'));
        self::assertStringContainsString("form-action 'self';", $response->headers['Content-Security-Policy']);
    }

    public function testNoHstsWithoutHttps(): void
    {
        $response = AppFactory::create(['app' => ['url' => 'http://localhost:8080']])->handle(new Request('GET', '/'));
        self::assertArrayNotHasKey('Strict-Transport-Security', $response->headers);
    }

    public function testUnknownPageIs404(): void
    {
        $response = AppFactory::create()->handle(new Request('GET', '/neexistuje'));
        self::assertSame(404, $response->status);
        self::assertStringContainsString('Stránka nebyla nalezena', $response->body);
        self::assertArrayHasKey('Content-Security-Policy', $response->headers);
    }

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $response = AppFactory::create()->handle(new Request('POST', '/'));
        self::assertSame(419, $response->status);
    }

    public function testPostWithForeignCsrfTokenIsRejected(): void
    {
        $response = AppFactory::create()->handle(new Request('POST', '/', [], [Csrf::FIELD => str_repeat('f', 64)]));
        self::assertSame(419, $response->status);
    }

    public function testPostWithValidCsrfTokenPassesToRouter(): void
    {
        $app = AppFactory::create();
        $app->handle(new Request('GET', '/'));
        $token = $app->csrf()->token();

        $response = $app->handle(new Request('POST', '/', [], [Csrf::FIELD => $token]));
        self::assertSame(405, $response->status, 'CSRF prošlo, "/" ale POST nepodporuje');
    }

    public function testUnsupportedMethodIs405(): void
    {
        $response = AppFactory::create()->handle(new Request('DELETE', '/'));
        self::assertSame(405, $response->status);
    }

    public function testHeadHasNoBody(): void
    {
        $response = AppFactory::create()->handle(new Request('HEAD', '/'));
        self::assertSame(200, $response->status);
        self::assertSame('', $response->body);
    }

    public function testUnexpectedErrorDoesNotLeakDetails(): void
    {
        $app = AppFactory::create();
        $app->router->get('/boom', static function (): never {
            throw new RuntimeException('SQLSTATE[42S02] tajný detail tabulky users');
        });

        $response = $app->handle(new Request('GET', '/boom'));
        self::assertSame(500, $response->status);
        self::assertStringNotContainsString('SQLSTATE', $response->body);
        self::assertStringNotContainsString('users', $response->body);
    }

    public function testDebugModeShowsEscapedDetailOutsideProduction(): void
    {
        $app = AppFactory::create(['app' => ['env' => 'development', 'debug' => true]]);
        $app->router->get('/boom', static function (): never {
            throw new RuntimeException('<b>detail</b>');
        });

        $response = $app->handle(new Request('GET', '/boom'));
        self::assertStringContainsString('&lt;b&gt;detail&lt;/b&gt;', $response->body);
    }

    public function testFlashMessageIsEscaped(): void
    {
        $app = AppFactory::create();
        $app->handle(new Request('GET', '/'));
        $app->session()->flash('error', '<script>alert(1)</script>');

        $response = $app->handle(new Request('GET', '/'));
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->body);
        self::assertStringContainsString('&lt;script&gt;', $response->body);
    }
}
