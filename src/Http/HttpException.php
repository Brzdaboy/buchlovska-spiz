<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/** Očekávaná chyba s HTTP stavem a textem, který je bezpečné ukázat uživateli. */
final class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        string $publicMessage,
        public readonly array $headers = [],
    ) {
        parent::__construct($publicMessage, $status);
    }

    public static function notFound(): self
    {
        return new self(404, 'Odkaz je možná neplatný, nebo stránka už neexistuje.');
    }

    public static function forbidden(string $message = 'K této stránce nemáte přístup.'): self
    {
        return new self(403, $message);
    }

    /** @param list<string> $allowed */
    public static function methodNotAllowed(array $allowed): self
    {
        return new self(405, 'Tato akce není povolena.', ['Allow' => implode(', ', $allowed)]);
    }

    public static function csrf(): self
    {
        return new self(419, 'Platnost formuláře vypršela. Vraťte se zpět, obnovte stránku a zkuste to znovu.');
    }
}
