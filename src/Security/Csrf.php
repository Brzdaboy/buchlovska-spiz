<?php

declare(strict_types=1);

namespace App\Security;

/** CSRF token vázaný na session (synchronizer token pattern). */
final class Csrf
{
    public const FIELD = '_csrf';
    private const KEY = '_csrf_token';

    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }
        return $token;
    }

    public function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . e($this->token()) . '">';
    }

    public function isValid(?string $submitted): bool
    {
        $expected = $this->session->get(self::KEY);
        return is_string($expected) && is_string($submitted) && $submitted !== ''
            && hash_equals($expected, $submitted);
    }

    /** Po přihlášení vydá nový token. */
    public function rotate(): void
    {
        $this->session->remove(self::KEY);
    }
}
