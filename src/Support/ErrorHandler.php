<?php

declare(strict_types=1);

namespace App\Support;

use ErrorException;

/**
 * Převádí PHP varování na výjimky a zachytí fatální chyby.
 * Uživatel nikdy neuvidí detail chyby; ten jde jen do logu.
 */
final class ErrorHandler
{
    public static function register(Logger $logger, bool $debug): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(static function () use ($logger): void {
            $error = error_get_last();
            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            $logger->error('Fatální chyba', [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ]);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
            }
            echo self::fallbackPage();
        });
    }

    /** Statická stránka pro případ, kdy selže i vykreslení šablony. */
    public static function fallbackPage(int $status = 500): string
    {
        $title = $status === 503 ? 'Služba je dočasně nedostupná' : 'Něco se pokazilo';
        return '<!doctype html><html lang="cs"><head><meta charset="utf-8"><title>' . $title
            . ' · Buchlovská spíž</title></head><body style="font-family:Georgia,serif;background:#f6f1e5;color:#173c2a;padding:10vh 24px;text-align:center">'
            . '<h1>' . $title . '</h1><p>Zkuste to prosím za chvíli znovu.</p></body></html>';
    }
}
