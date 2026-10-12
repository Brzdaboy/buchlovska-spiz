<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Přístup k tabulce users. Jen parametrizované dotazy. */
final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public function findByLogtoId(string $logtoUserId): ?array
    {
        return $this->one('SELECT * FROM users WHERE logto_user_id = ?', [$logtoUserId]);
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->one('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    /** Propojí pozvaný účet s identitou v Logto (jen pokud ještě propojený není). */
    public function linkIdentity(int $id, string $logtoUserId, string $newStatus): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET logto_user_id = ?, status = ? WHERE id = ? AND logto_user_id IS NULL AND status = 'invited'"
        );
        $stmt->execute([$logtoUserId, $newStatus, $id]);
        return $stmt->rowCount() === 1;
    }

    public function touchLogin(int $id): void
    {
        $this->pdo->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
    }

    public function createInvited(string $email, string $role): int
    {
        $this->pdo->prepare("INSERT INTO users (email, role, status) VALUES (?, ?, 'invited')")
            ->execute([mb_strtolower(trim($email)), $role]);
        return (int) $this->pdo->lastInsertId();
    }

    /** Admin může při novém pozvání změnit roli, dokud uživatel registraci nedokončil. */
    public function updateInvitedRole(int $id, string $role): void
    {
        $this->pdo->prepare("UPDATE users SET role = ? WHERE id = ? AND status = 'invited' AND role IN ('producer', 'school')")
            ->execute([$role, $id]);
    }

    public function createAdmin(string $email): int
    {
        $this->pdo->prepare("INSERT INTO users (email, role, status) VALUES (?, 'admin', 'invited')")
            ->execute([mb_strtolower(trim($email))]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param list<scalar|null> $params
     * @return array<string, mixed>|null
     */
    private function one(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
