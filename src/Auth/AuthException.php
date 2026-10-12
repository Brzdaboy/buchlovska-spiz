<?php

declare(strict_types=1);

namespace App\Auth;

use RuntimeException;

/**
 * Selhání přihlášení. Zpráva je technická (jde do logu);
 * uživateli se ukazuje jen obecný text.
 */
final class AuthException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'invalid')
    {
        parent::__construct($message);
    }
}
