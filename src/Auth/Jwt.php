<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Ověření podpisu JWT (ID token z Logto) proti veřejným klíčům JWKS.
 * Podporuje jen asymetrické algoritmy ES256/ES384 a RS256; "none" a HS* odmítá.
 */
final class Jwt
{
    private const ALGORITHMS = [
        'ES256' => ['EC', OPENSSL_ALGO_SHA256, 32, 'P-256'],
        'ES384' => ['EC', OPENSSL_ALGO_SHA384, 48, 'P-384'],
        'RS256' => ['RSA', OPENSSL_ALGO_SHA256, 0, null],
    ];

    /**
     * Rozloží token a ověří podpis. Vrací hlavičku a payload.
     *
     * @param list<array<string, mixed>> $jwks klíče z jwks_uri (pole "keys")
     * @return array{header: array<string, mixed>, claims: array<string, mixed>}
     * @throws AuthException
     */
    public static function verify(string $token, array $jwks): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new AuthException('Neplatný formát tokenu.');
        }
        [$h, $p, $s] = $parts;
        $header = self::decodeJson($h);
        $claims = self::decodeJson($p);
        $signature = self::base64UrlDecode($s);

        $alg = $header['alg'] ?? null;
        if (!is_string($alg) || !isset(self::ALGORITHMS[$alg])) {
            throw new AuthException('Nepodporovaný algoritmus podpisu.');
        }
        [$kty, $hash, $partLength, $curve] = self::ALGORITHMS[$alg];

        $jwk = self::findKey($jwks, $header['kid'] ?? null, $kty, $curve);
        if ($jwk === null) {
            throw new AuthException('Podpisový klíč nebyl nalezen.', 'unknown_kid');
        }
        $pem = $kty === 'EC' ? self::ecJwkToPem($jwk) : self::rsaJwkToPem($jwk);
        if ($kty === 'EC') {
            if (strlen($signature) !== 2 * $partLength) {
                throw new AuthException('Neplatná délka podpisu.');
            }
            $signature = self::rawToDer($signature, $partLength);
        }
        $key = openssl_pkey_get_public($pem);
        if ($key === false || openssl_verify($h . '.' . $p, $signature, $key, $hash) !== 1) {
            throw new AuthException('Podpis tokenu není platný.');
        }
        return ['header' => $header, 'claims' => $claims];
    }

    /**
     * @param list<array<string, mixed>> $jwks
     * @return array<string, mixed>|null
     */
    private static function findKey(array $jwks, mixed $kid, string $kty, ?string $curve): ?array
    {
        foreach ($jwks as $jwk) {
            if (($jwk['kty'] ?? null) !== $kty) {
                continue;
            }
            if ($curve !== null && ($jwk['crv'] ?? null) !== $curve) {
                continue;
            }
            if (isset($jwk['use']) && $jwk['use'] !== 'sig') {
                continue;
            }
            if ($kid === null || ($jwk['kid'] ?? null) === $kid) {
                return $jwk;
            }
        }
        return null;
    }

    /** @return array<string, mixed> */
    private static function decodeJson(string $segment): array
    {
        try {
            $value = json_decode(self::base64UrlDecode($segment), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new AuthException('Neplatný obsah tokenu.');
        }
        if (!is_array($value)) {
            throw new AuthException('Neplatný obsah tokenu.');
        }
        return $value;
    }

    public static function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new AuthException('Neplatné kódování tokenu.');
        }
        return $decoded;
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    // ---- převody klíčů a podpisů (ASN.1 DER) --------------------------------

    /** @param array<string, mixed> $jwk */
    private static function ecJwkToPem(array $jwk): string
    {
        $x = self::base64UrlDecode((string) ($jwk['x'] ?? ''));
        $y = self::base64UrlDecode((string) ($jwk['y'] ?? ''));
        $oids = [
            'P-256' => '06082a8648ce3d030107',   // prime256v1
            'P-384' => '06052b81040022',         // secp384r1
        ];
        $curveOid = $oids[$jwk['crv'] ?? ''] ?? throw new AuthException('Nepodporovaná křivka.');
        $algorithm = self::der(0x30, hex2bin('06072a8648ce3d0201') . hex2bin($curveOid)); // id-ecPublicKey
        $publicKey = self::der(0x03, "\x00\x04" . $x . $y);
        return self::pem(self::der(0x30, $algorithm . $publicKey));
    }

    /** @param array<string, mixed> $jwk */
    private static function rsaJwkToPem(array $jwk): string
    {
        $n = self::base64UrlDecode((string) ($jwk['n'] ?? ''));
        $e = self::base64UrlDecode((string) ($jwk['e'] ?? ''));
        $rsaKey = self::der(0x30, self::derInteger($n) . self::derInteger($e));
        $algorithm = self::der(0x30, hex2bin('06092a864886f70d010101') . "\x05\x00"); // rsaEncryption, NULL
        return self::pem(self::der(0x30, $algorithm . self::der(0x03, "\x00" . $rsaKey)));
    }

    /** JWS podpis ECDSA (r||s) -> DER, jak ho očekává OpenSSL. */
    public static function rawToDer(string $raw, int $partLength): string
    {
        $r = substr($raw, 0, $partLength);
        $s = substr($raw, $partLength);
        return self::der(0x30, self::derInteger($r) . self::derInteger($s));
    }

    /** DER podpis z OpenSSL -> JWS formát r||s (používají testy). */
    public static function derToRaw(string $der, int $partLength): string
    {
        $offset = 2;
        if ((ord($der[1]) & 0x80) !== 0) {
            $offset += ord($der[1]) & 0x7f;
        }
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $value = ltrim(substr($der, $offset + 2, $length), "\x00");
            $parts[] = str_pad($value, $partLength, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }
        return $parts[0] . $parts[1];
    }

    private static function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }
        return self::der(0x02, $bytes);
    }

    private static function der(int $tag, string $content): string
    {
        $length = strlen($content);
        if ($length < 0x80) {
            return chr($tag) . chr($length) . $content;
        }
        $lengthBytes = ltrim(pack('N', $length), "\x00");
        return chr($tag) . chr(0x80 | strlen($lengthBytes)) . $lengthBytes . $content;
    }

    private static function pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }
}
