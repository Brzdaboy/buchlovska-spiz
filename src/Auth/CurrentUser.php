<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\HttpException;
use App\Repository\UserRepository;
use App\Security\Session;

/**
 * Přihlášený uživatel aktuálního požadavku.
 * Stav účtu se čte z databáze při KAŽDÉM požadavku: zablokování platí okamžitě.
 */
final class CurrentUser
{
    public const SESSION_KEY = 'user_id';

    /** @var array<string, mixed>|null|false false = ještě nenačteno */
    private array|null|false $user = false;

    public function __construct(private Session $session, private UserRepository $users)
    {
    }

    /** @return array<string, mixed>|null */
    public function get(): ?array
    {
        if ($this->user !== false) {
            return $this->user;
        }
        $id = $this->session->get(self::SESSION_KEY);
        $user = is_int($id) ? $this->users->findById($id) : null;
        if ($user !== null && !in_array($user['status'], ['profile_incomplete', 'active'], true)) {
            $user = null;
        }
        if ($user === null && $id !== null) {
            // Účet zmizel nebo byl zablokován: session ukončíme.
            $this->session->clear();
            $this->session->regenerate();
        }
        return $this->user = $user;
    }

    /**
     * @return array<string, mixed>
     * @throws HttpException přesměrování na přihlášení
     */
    public function require(): array
    {
        return $this->get() ?? throw HttpException::redirect('/prihlaseni');
    }

    /**
     * Vyžaduje přihlášení s jednou z rolí. Neúplný profil pustí jen tam, kde je to výslovně povoleno.
     *
     * @return array<string, mixed>
     */
    public function requireRole(string ...$roles): array
    {
        $user = $this->require();
        if (!in_array($user['role'], $roles, true)) {
            throw HttpException::forbidden();
        }
        return $user;
    }

    /** @param array<string, mixed> $user */
    public function signIn(array $user): void
    {
        $this->session->regenerate();
        $this->session->clear();
        $this->session->set(self::SESSION_KEY, (int) $user['id']);
        // Jen pro zobrazení navigace; oprávnění se vždy ověřují z databáze.
        $this->session->set('user_role', (string) $user['role']);
        $this->user = $user;
    }

    public function signOut(): void
    {
        $this->session->clear();
        $this->session->regenerate();
        $this->user = null;
    }
}
