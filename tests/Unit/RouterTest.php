<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/', static fn (): Response => new Response('home'));
        $this->router->get('/nabidky/{id}', static fn (Request $r, array $p): Response => new Response('offer ' . $p['id']));
        $this->router->post('/nabidky/{id}/ukoncit', static fn (Request $r, array $p): Response => new Response('end ' . $p['id']));
    }

    private function dispatch(string $method, string $path): Response
    {
        return $this->router->dispatch(new Request($method, Request::normalizePath($path)));
    }

    public function testStaticRoute(): void
    {
        self::assertSame('home', $this->dispatch('GET', '/')->body);
    }

    public function testParameterRoute(): void
    {
        self::assertSame('offer 42', $this->dispatch('GET', '/nabidky/42')->body);
        self::assertSame('offer 42', $this->dispatch('GET', '/nabidky/42/')->body, 'koncové lomítko');
    }

    public function testHeadIsServedByGetHandler(): void
    {
        self::assertSame('home', $this->dispatch('HEAD', '/')->body);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidIds(): iterable
    {
        yield 'text' => ['/nabidky/abc'];
        yield 'nula' => ['/nabidky/0'];
        yield 'úvodní nula' => ['/nabidky/007'];
        yield 'záporné' => ['/nabidky/-1'];
        yield 'SQL' => ["/nabidky/1 OR 1=1"];
        yield 'příliš dlouhé' => ['/nabidky/12345678901'];
        yield 'cesta navíc' => ['/nabidky/1/x'];
    }

    /** @dataProvider invalidIds */
    public function testInvalidParameterIsNotFound(string $path): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $this->dispatch('GET', $path);
    }

    public function testWrongMethodIs405WithAllowHeader(): void
    {
        try {
            $this->dispatch('GET', '/nabidky/5/ukoncit');
            self::fail('Očekávána výjimka 405');
        } catch (HttpException $e) {
            self::assertSame(405, $e->status);
            self::assertSame('POST', $e->headers['Allow']);
        }
    }
}
