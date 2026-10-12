<?php

declare(strict_types=1);

namespace App\Auth;

use RuntimeException;

/** Identita z Logto je platná, ale v aplikaci nemá (nebo už nemá) přístup. */
final class LoginDenied extends RuntimeException
{
    public const UNKNOWN = 'unknown';
    public const BLOCKED = 'blocked';
    public const INVITATION_EXPIRED = 'invitation_expired';
    public const EMAIL_NOT_VERIFIED = 'email_not_verified';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    public function userMessage(): string
    {
        return match ($this->reason) {
            self::BLOCKED => 'Váš účet je zablokovaný. Pokud jde o omyl, kontaktujte správce.',
            self::INVITATION_EXPIRED => 'Platnost vaší pozvánky vypršela. Požádejte správce o novou.',
            self::EMAIL_NOT_VERIFIED => 'E-mailová adresa ještě není ověřená. Dokončete ověření a přihlaste se znovu.',
            default => 'Tento účet nemá do Buchlovské spíže přístup. Registrace je možná jen na pozvánku od správce.',
        };
    }
}
