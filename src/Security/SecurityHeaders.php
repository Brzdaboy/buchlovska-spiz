<?php

declare(strict_types=1);

namespace App\Security;

use App\Http\Response;

/** Bezpečnostní hlavičky pro každou odpověď aplikace. */
final class SecurityHeaders
{
    /**
     * @param string|null $authOrigin původ Logto (např. https://abc.logto.app). Chrome uplatňuje
     *        form-action i na přesměrování po odeslání formuláře, takže odhlášení přes POST
     *        a následné přesměrování na Logto musí být povolené.
     */
    public static function contentSecurityPolicy(?string $authOrigin = null): string
    {
        $formAction = "form-action 'self'" . ($authOrigin !== null ? ' ' . $authOrigin : '');
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            $formAction,
            "frame-ancestors 'none'",
        ]);
    }

    /** Z URL vytáhne jen původ (schéma + host + port); neplatné hodnoty ignoruje. */
    public static function originOf(?string $url): ?string
    {
        if ($url === null || !preg_match('#^https://[a-z0-9.-]+(:\d+)?(/|$)#i', $url)) {
            return null;
        }
        $parts = parse_url($url);
        return 'https://' . strtolower((string) $parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    public static function apply(Response $response, bool $https, ?string $authOrigin = null): Response
    {
        $headers = [
            'Content-Security-Policy' => self::contentSecurityPolicy($authOrigin),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            // Přihlášené stránky obsahují kontakty: nesmí zůstat v cizích cache.
            'Cache-Control' => 'no-store, private',
        ];
        if ($https) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach ($headers as $name => $value) {
            if (!isset($response->headers[$name])) {
                $response = $response->withHeader($name, $value);
            }
        }
        return $response;
    }
}
