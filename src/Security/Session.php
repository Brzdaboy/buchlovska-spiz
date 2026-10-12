<?php

declare(strict_types=1);

namespace App\Security;

use App\Support\Config;

/**
 * Obal nad PHP session. V testech pracuje nad obyčejným polem.
 */
final class Session
{
    private const LAST_ACTIVITY = '_last_activity';

    /** @param array<string, mixed> $data */
    public function __construct(private array &$data, private bool $native = false)
    {
    }

    /** Spustí nativní session s bezpečnými cookies a vypršením po nečinnosti. */
    public static function startNative(Config $config): self
    {
        $https = $config->isHttps();
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        // Prefix __Host- prohlížeč přijme jen se Secure, bez Domain a s Path=/.
        session_name($https ? '__Host-bspiz' : 'bspiz');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $session = new self($_SESSION, true);
        $session->enforceIdleTimeout((int) $config->get('session.idle_minutes', 120));
        return $session;
    }

    public function enforceIdleTimeout(int $minutes, ?int $now = null): void
    {
        $now ??= time();
        $last = $this->data[self::LAST_ACTIVITY] ?? null;
        if (is_int($last) && $now - $last > $minutes * 60) {
            $this->clear();
            $this->regenerate();
        }
        $this->data[self::LAST_ACTIVITY] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    /** Vrátí hodnotu a smaže ji (např. flash zprávy, OIDC state). */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    /** Nové ID session — povinně po přihlášení a odhlášení (session fixation). */
    public function regenerate(): void
    {
        if ($this->native && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function clear(): void
    {
        foreach (array_keys($this->data) as $key) {
            unset($this->data[$key]);
        }
    }

    public function flash(string $type, string $message): void
    {
        $messages = $this->get('_flash', []);
        $messages[] = ['type' => $type, 'message' => $message];
        $this->set('_flash', $messages);
    }

    /** @return list<array{type: string, message: string}> */
    public function pullFlashes(): array
    {
        $messages = $this->pull('_flash', []);
        return is_array($messages) ? $messages : [];
    }
}
