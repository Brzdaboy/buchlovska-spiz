<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\AppFactory;

final class PublicPagesTest extends TestCase
{
    private const PAGES = [
        '/', '/o-projektu', '/jak-ziskat-pristup', '/kontakt',
        '/ochrana-osobnich-udaju', '/podminky-pouzivani', '/prihlaseni',
    ];

    /** @return iterable<string, array{string}> */
    public static function pages(): iterable
    {
        foreach (self::PAGES as $path) {
            yield $path => [$path];
        }
    }

    /** @dataProvider pages */
    public function testPageRendersWithoutTouchingDatabase(string $path): void
    {
        $app = AppFactory::create();
        $response = $app->handle(new Request('GET', $path));

        self::assertSame(200, $response->status);
        self::assertFalse($app->db->isConnected(), 'veřejná stránka se nesmí připojit k databázi');
        self::assertSame(1, substr_count($response->body, '<h1'), 'právě jeden nadpis h1');
        self::assertStringContainsString('<html lang="cs">', $response->body);
    }

    /** @dataProvider pages */
    public function testNoSelfRegistrationOrMarketplaceData(string $path): void
    {
        $body = AppFactory::create()->handle(new Request('GET', $path))->body;

        self::assertStringNotContainsString('<form', $body, 'veřejná část nemá registrační ani jiný formulář');
        self::assertDoesNotMatchRegularExpression('/registrovat se|zaregistrujte/iu', $body);
        self::assertDoesNotMatchRegularExpression('/\d+\s*Kč/u', $body, 'žádné ceny z tržiště');
        self::assertStringNotContainsString('mailto:', $path === '/kontakt' ? '' : $body, 'žádné kontakty členů');
    }

    public function testTitlesAreUnique(): void
    {
        $titles = [];
        foreach (self::PAGES as $path) {
            preg_match('#<title>(.*?)</title>#s', AppFactory::create()->handle(new Request('GET', $path))->body, $m);
            $titles[$path] = $m[1] ?? '';
        }
        self::assertSame(count($titles), count(array_unique($titles)), print_r($titles, true));
    }

    public function testAllInternalLinksResolve(): void
    {
        $seen = [];
        $queue = ['/'];
        while ($queue !== []) {
            $path = array_shift($queue);
            if (isset($seen[$path])) {
                continue;
            }
            $response = AppFactory::create()->handle(new Request('GET', $path));
            $seen[$path] = $response->status;
            preg_match_all('#href="(/[^"\#?]*)#', $response->body, $m);
            foreach ($m[1] as $link) {
                if (!str_starts_with($link, '/assets/') && !isset($seen[$link])) {
                    $queue[] = $link;
                }
            }
        }
        self::assertSame(array_fill_keys(array_keys($seen), 200), $seen);
        self::assertGreaterThanOrEqual(7, count($seen));
    }

    public function testStaticAssetsReferencedByPagesExist(): void
    {
        $body = AppFactory::create()->handle(new Request('GET', '/'))->body;
        preg_match_all('#(?:src|href)="(/assets/[^"]+)"#', $body, $m);
        preg_match_all('#(/assets/[^\s,"]+)\s+\d+w#', $body, $srcset);
        $assets = array_unique(array_merge($m[1], $srcset[1]));
        self::assertNotEmpty($assets);
        foreach ($assets as $asset) {
            self::assertFileExists(dirname(__DIR__, 2) . '/public' . $asset);
        }
    }

    public function testImagesHaveAltText(): void
    {
        foreach (self::PAGES as $path) {
            $body = AppFactory::create()->handle(new Request('GET', $path))->body;
            preg_match_all('#<img\b[^>]*>#', $body, $m);
            foreach ($m[0] as $img) {
                self::assertMatchesRegularExpression('/\balt="[^"]+"/', $img, $path);
            }
        }
    }

    public function testCurrentPageIsMarkedInNavigation(): void
    {
        $body = AppFactory::create()->handle(new Request('GET', '/kontakt'))->body;
        self::assertStringContainsString('<a href="/kontakt" aria-current="page">', $body);
    }

    public function testContactShowsPlaceholderUntilConfigured(): void
    {
        $body = AppFactory::create()->handle(new Request('GET', '/kontakt'))->body;
        self::assertStringContainsString('doplníme před spuštěním', $body);
        self::assertStringNotContainsString('mailto:', $body);
    }

    public function testConfiguredContactIsRenderedAndEscaped(): void
    {
        $body = AppFactory::create(['site' => [
            'operator_name' => 'Mikroregion <Buchlov>',
            'contact_email' => 'info@example.cz',
            'contact_phone' => '+420 572 000 000',
        ]])->handle(new Request('GET', '/kontakt'))->body;

        self::assertStringContainsString('Mikroregion &lt;Buchlov&gt;', $body);
        self::assertStringContainsString('href="mailto:info@example.cz"', $body);
        self::assertStringContainsString('href="tel:+420572000000"', $body);
        self::assertStringNotContainsString('doplníme před spuštěním', $body);
    }
}
