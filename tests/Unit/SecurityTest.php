<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Security\Csrf;
use App\Security\Session;
use App\View\View;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecurityTest extends TestCase
{
    public function testCsrfTokenIsStableAndValidated(): void
    {
        $data = [];
        $csrf = new Csrf(new Session($data));
        $token = $csrf->token();

        self::assertSame(64, strlen($token));
        self::assertSame($token, $csrf->token());
        self::assertTrue($csrf->isValid($token));
        self::assertFalse($csrf->isValid(null));
        self::assertFalse($csrf->isValid(''));
        self::assertFalse($csrf->isValid(str_repeat('a', 64)));
    }

    public function testCsrfRejectsWhenSessionHasNoToken(): void
    {
        $data = [];
        self::assertFalse((new Csrf(new Session($data)))->isValid('cokoliv'));
    }

    public function testCsrfRotation(): void
    {
        $data = [];
        $csrf = new Csrf(new Session($data));
        $old = $csrf->token();
        $csrf->rotate();
        self::assertNotSame($old, $csrf->token());
        self::assertFalse($csrf->isValid($old));
    }

    public function testSessionIdleTimeoutClearsData(): void
    {
        $data = [];
        $session = new Session($data);
        $session->enforceIdleTimeout(120, 1_000);
        $session->set('user_id', 7);

        $session->enforceIdleTimeout(120, 1_000 + 119 * 60);
        self::assertSame(7, $session->get('user_id'), 'před vypršením zůstává');

        $session->enforceIdleTimeout(120, 1_000 + 119 * 60 + 121 * 60);
        self::assertNull($session->get('user_id'), 'po nečinnosti se smaže');
    }

    public function testFlashMessagesArePulledOnce(): void
    {
        $data = [];
        $session = new Session($data);
        $session->flash('success', 'Uloženo');
        self::assertSame([['type' => 'success', 'message' => 'Uloženo']], $session->pullFlashes());
        self::assertSame([], $session->pullFlashes());
    }

    public function testEscapeHelper(): void
    {
        self::assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
        self::assertSame('&apos; onmouseover=&apos;x', e("' onmouseover='x"));
        self::assertSame('', e(null));
    }

    public function testViewRejectsTemplateTraversal(): void
    {
        $view = new View(dirname(__DIR__, 2) . '/templates');
        $this->expectException(RuntimeException::class);
        $view->render('../config/config.example', [], null);
    }
}
