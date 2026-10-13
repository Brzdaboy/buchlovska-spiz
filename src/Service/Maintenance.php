<?php

declare(strict_types=1);

namespace App\Service;

use App\Auth\LogtoManagement;
use App\Support\Logger;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Denní úklid (ROZHODNUTI č. 12, Zásady ochrany osobních údajů §8). Spouští ho jednou za noc
 * plánovaná úloha na WEDOS (bin/daily.php nebo /cron/denni?klic=…).
 *
 *  1. Prošlé nabídky a poptávky přepne na „Vypršelo“ (zapíše do historie jako systém).
 *  2. Dosmaže případné staré „měkce smazané“ záznamy (dnes se maže hned).
 *  3. Nevyužité pozvánky smaže 30 dní po vypršení/zrušení, i s nedokončeným účtem.
 *  4. Archivované účty smaže 3 roky po archivaci – profil, záznamy, historii a účet v Logto.
 *  5. Soubory logů aplikace smaže po 90 dnech.
 *
 * Ukončené a vypršelé záznamy, které autor nesmazal, zůstávají (rozhodnutí zadavatele).
 */
final class Maintenance
{
    public const INVITATION_GRACE_DAYS = 30;
    public const ARCHIVE_YEARS = 3;
    public const LOG_DAYS = 90;

    public function __construct(
        private PDO $pdo,
        private string $logDir,
        private string $stateFile,
        private Logger $logger,
        private ?LogtoManagement $logto,
        private bool $deleteLogtoAccounts,
    ) {
    }

    /**
     * @param string $today dnešní datum v zóně aplikace (Y-m-d)
     * @return array<string, int> počty provedených změn
     */
    public function run(DateTimeImmutable $nowUtc, string $today): array
    {
        $now = $nowUtc->setTimezone(new DateTimeZone('UTC'));
        $report = [
            'expired_offers' => $this->expire('offer', 'offers', 'available_until', $today),
            'expired_demands' => $this->expire('demand', 'demands', 'needed_until', $today),
            'purged_deleted' => $this->purgeSoftDeleted(),
            'removed_invitations' => $this->removeOldInvitations($now),
            'removed_accounts' => 0,
            'skipped_accounts' => 0,
            'removed_logs' => $this->removeOldLogs($now),
        ];
        [$report['removed_accounts'], $report['skipped_accounts']] = $this->removeArchivedAccounts($now);
        $this->saveState($now, $report);
        return $report;
    }

    /**
     * Spustí úklid, pokud zrovna neběží jiný (zámek v souboru). Vrátí null, když už běží.
     *
     * @return array<string, int>|null
     */
    public function runExclusive(DateTimeImmutable $nowUtc, string $today): ?array
    {
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $lock = fopen($this->stateFile . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return null;
        }
        try {
            return $this->run($nowUtc, $today);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array{last_run: string, report: array<string, int>}|null stav posledního běhu */
    public static function lastRun(string $stateFile): ?array
    {
        if (!is_file($stateFile)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($stateFile), true);
        return is_array($data) && is_string($data['last_run'] ?? null) ? $data : null;
    }

    private function expire(string $key, string $table, string $endColumn, string $today): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM {$table} WHERE status = 'active' AND deleted_at IS NULL AND {$endColumn} IS NOT NULL AND {$endColumn} < ?");
        $stmt->execute([$today]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        foreach ($ids as $id) {
            $this->pdo->prepare("UPDATE {$table} SET status = 'expired' WHERE id = ? AND status = 'active'")->execute([$id]);
            $this->pdo->prepare('INSERT INTO audit_log (actor_user_id, action, target_type, target_id) VALUES (NULL, ?, ?, ?)')
                ->execute([$key . '.expired', $key, $id]);
        }
        return count($ids);
    }

    private function purgeSoftDeleted(): int
    {
        $count = 0;
        foreach (['offer' => 'offers', 'demand' => 'demands'] as $key => $table) {
            $ids = array_map('intval', $this->pdo->query("SELECT id FROM {$table} WHERE deleted_at IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN));
            foreach ($ids as $id) {
                $this->transaction(function () use ($key, $table, $id): void {
                    $this->pdo->prepare('DELETE FROM audit_log WHERE target_type = ? AND target_id = ?')->execute([$key, $id]);
                    $this->pdo->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
                });
                $count++;
            }
        }
        return $count;
    }

    /** Pozvánky nepřijaté 30 dní po vypršení nebo zrušení; uživatel bez jiné pozvánky jde také pryč. */
    private function removeOldInvitations(DateTimeImmutable $now): int
    {
        $limit = $now->modify('-' . self::INVITATION_GRACE_DAYS . ' days')->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "SELECT id, user_id FROM invitations WHERE status <> 'accepted' AND COALESCE(revoked_at, expires_at) < ?"
        );
        $stmt->execute([$limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $this->transaction(function () use ($row): void {
                $this->pdo->prepare("DELETE FROM audit_log WHERE target_type = 'invitation' AND target_id = ?")->execute([(int) $row['id']]);
                $this->pdo->prepare('DELETE FROM invitations WHERE id = ?')->execute([(int) $row['id']]);
                $this->pdo->prepare(
                    "DELETE FROM users WHERE id = ? AND status = 'invited' AND NOT EXISTS (SELECT 1 FROM invitations i WHERE i.user_id = users.id)"
                )->execute([(int) $row['user_id']]);
            });
        }
        return count($rows);
    }

    /** @return array{0: int, 1: int} [smazané, odložené kvůli Logto] */
    private function removeArchivedAccounts(DateTimeImmutable $now): array
    {
        $limit = $now->modify('-' . self::ARCHIVE_YEARS . ' years')->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "SELECT id, logto_user_id FROM users WHERE status = 'archived' AND role IN ('producer', 'school') AND archived_at IS NOT NULL AND archived_at < ?"
        );
        $stmt->execute([$limit]);
        $removed = 0;
        $skipped = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $user) {
            $userId = (int) $user['id'];
            $logtoId = $user['logto_user_id'];
            if ($this->deleteLogtoAccounts && is_string($logtoId) && $logtoId !== '') {
                try {
                    if ($this->logto === null) {
                        throw new \RuntimeException('Logto Management API není nastavené.');
                    }
                    $this->logto->deleteUser($logtoId);
                } catch (\Throwable $e) {
                    // Účet necháme na příští noc, ať v Logto nezůstane osiřelý e-mail a heslo.
                    $this->logger->error('Úklid: účet v Logto nelze smazat, zkusí se znovu', ['user_id' => $userId, 'message' => $e->getMessage()]);
                    $skipped++;
                    continue;
                }
            }
            $this->transaction(fn () => $this->deleteUserData($userId));
            $removed++;
        }
        return [$removed, $skipped];
    }

    private function deleteUserData(int $userId): void
    {
        $exec = fn (string $sql) => $this->pdo->prepare($sql)->execute(array_fill(0, substr_count($sql, '?'), $userId));
        $exec("DELETE FROM audit_log WHERE target_type = 'offer' AND target_id IN (SELECT id FROM offers WHERE producer_user_id = ?)");
        $exec("DELETE FROM audit_log WHERE target_type = 'demand' AND target_id IN (SELECT id FROM demands WHERE school_user_id = ?)");
        $exec("DELETE FROM audit_log WHERE target_type = 'invitation' AND target_id IN (SELECT id FROM invitations WHERE user_id = ?)");
        $exec("DELETE FROM audit_log WHERE (target_type = 'user' AND target_id = ?) OR actor_user_id = ?");
        $exec('DELETE FROM offers WHERE producer_user_id = ?');
        $exec('DELETE FROM demands WHERE school_user_id = ?');
        $exec('DELETE FROM producer_profiles WHERE user_id = ?');
        $exec('DELETE FROM school_profiles WHERE user_id = ?');
        $exec('DELETE FROM invitations WHERE user_id = ?');
        $exec("DELETE FROM users WHERE id = ? AND status = 'archived'");
    }

    private function removeOldLogs(DateTimeImmutable $now): int
    {
        $limit = $now->modify('-' . self::LOG_DAYS . ' days')->format('Y-m-d');
        $count = 0;
        foreach (glob($this->logDir . '/app-*.log') ?: [] as $file) {
            if (preg_match('/app-(\d{4}-\d{2}-\d{2})\.log$/', $file, $m) && $m[1] < $limit && @unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    /** @param array<string, int> $report */
    private function saveState(DateTimeImmutable $now, array $report): void
    {
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($this->stateFile, (string) json_encode(['last_run' => $now->format(DATE_ATOM), 'report' => $report]), LOCK_EX);
    }

    private function transaction(callable $work): void
    {
        $this->pdo->beginTransaction();
        try {
            $work();
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
