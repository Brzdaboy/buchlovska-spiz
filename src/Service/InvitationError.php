<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/** Chyba s textem, který je bezpečné ukázat administrátorovi. */
final class InvitationError extends RuntimeException
{
}
