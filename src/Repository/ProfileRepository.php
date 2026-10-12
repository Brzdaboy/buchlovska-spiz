<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Profily organizací: producer_profiles a school_profiles (jedna organizace = jeden účet). */
final class ProfileRepository
{
    private const COLUMNS = [
        'producer' => ['contact_name', 'producer_name', 'address_line', 'municipality', 'postal_code', 'phone', 'public_email', 'website', 'company_id'],
        'school' => ['school_name', 'contact_name', 'address_line', 'municipality', 'postal_code', 'phone', 'public_email', 'website', 'organization_id'],
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public static function table(string $role): string
    {
        return match ($role) {
            'producer' => 'producer_profiles',
            'school' => 'school_profiles',
            default => throw new \InvalidArgumentException('Tato role nemá profil.'),
        };
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId, string $role): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . self::table($role) . ' WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * Vloží nebo aktualizuje profil. Souhlasy se zapíšou jen při prvním uložení.
     *
     * @param array<string, string|null> $data validovaná data
     */
    public function save(int $userId, string $role, array $data): void
    {
        $columns = self::COLUMNS[$role] ?? throw new \InvalidArgumentException('Tato role nemá profil.');
        $values = array_map(static fn (string $c) => $data[$c] ?? null, $columns);
        $table = self::table($role);

        if ($this->find($userId, $role) === null) {
            $sql = sprintf(
                'INSERT INTO %s (user_id, %s, privacy_consent_at, contact_publication_consent_at) VALUES (?, %s, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
                $table,
                implode(', ', $columns),
                implode(', ', array_fill(0, count($columns), '?'))
            );
            $this->pdo->prepare($sql)->execute([$userId, ...$values]);
            return;
        }
        $sql = sprintf(
            'UPDATE %s SET %s WHERE user_id = ?',
            $table,
            implode(', ', array_map(static fn (string $c) => "{$c} = ?", $columns))
        );
        $this->pdo->prepare($sql)->execute([...$values, $userId]);
    }
}
