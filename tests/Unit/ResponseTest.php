<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testLocalRedirect(): void
    {
        $r = Response::redirect('/portal?tab=1');
        self::assertSame(303, $r->status);
        self::assertSame('/portal?tab=1', $r->headers['Location']);
    }

    /** @return iterable<string, array{string}> */
    public static function unsafeTargets(): iterable
    {
        yield 'protokol-relativní' => ['//zlo.example/'];
        yield 'absolutní' => ['https://zlo.example/'];
        yield 'zpětné lomítko' => ['/\\zlo.example'];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'relativní' => ['portal'];
    }

    /** @dataProvider unsafeTargets */
    public function testOpenRedirectIsRejected(string $target): void
    {
        $this->expectException(InvalidArgumentException::class);
        Response::redirect($target);
    }

    public function testExternalRedirectOnlyHttp(): void
    {
        self::assertSame('https://tenant.logto.app/oidc/auth', Response::redirectExternal('https://tenant.logto.app/oidc/auth')->headers['Location']);
        $this->expectException(InvalidArgumentException::class);
        Response::redirectExternal('javascript:alert(1)');
    }
}
