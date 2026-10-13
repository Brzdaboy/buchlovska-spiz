<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Denní úklid spouštěný plánovanou úlohou na WEDOS přes adresu
 *   https://<doména>/cron/denni?klic=<cron.key z config.php>
 * Bez nastaveného klíče (nebo se špatným klíčem) adresa „neexistuje“ (404).
 * Alternativa pro hosting s PHP v cronu: php bin/daily.php
 */
final class CronController
{
    private const MIN_KEY_LENGTH = 32;

    public function __construct(private App $app)
    {
    }

    public function register(Router $router): void
    {
        $router->get('/cron/denni', $this->daily(...));
    }

    public function daily(Request $request): Response
    {
        $key = (string) $this->app->config->get('cron.key', '');
        $given = (string) ($request->query('klic') ?? '');
        if (strlen($key) < self::MIN_KEY_LENGTH || !hash_equals($key, $given)) {
            throw HttpException::notFound();
        }
        $report = $this->app->maintenance()->runExclusive(new DateTimeImmutable('now', new DateTimeZone('UTC')), today());
        if ($report === null) {
            return Response::text("Úklid už běží.\n", 409);
        }
        $lines = array_map(static fn (string $k, int $v): string => "{$k}: {$v}", array_keys($report), $report);
        return Response::text("OK\n" . implode("\n", $lines) . "\n");
    }
}
