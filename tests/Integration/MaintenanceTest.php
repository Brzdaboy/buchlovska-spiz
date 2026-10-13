<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\App;
use App\Http\Request;
use App\Service\Maintenance;
use DateTimeImmutable;
use DateTimeZone;
use Tests\AppFactory;
use Tests\Support\FakeLogto;
use Tests\Support\Fixtures;

/** Denní úklid (ROZHODNUTI č. 12) a okamžité smazání záznamu autorem. */
final class MaintenanceTest extends DatabaseTestCase
{
    protected bool $useTransaction = false;
    private const CRON_KEY = 'tajny-klic-pro-cron-0123456789abcdef';
    private FakeLogto $logto;
    private App $app;
    private int $admin;
    private int $producer;
    private int $school;
    private string $varDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logto = new FakeLogto();
        $this->varDir = sys_get_temp_dir() . '/bspiz-maint-' . getmypid() . '-' . bin2hex(random_bytes(3));
        $this->app = $this->newApp();
        $this->admin = $this->createUser('admin', ['logto_user_id' => 'logto-admin']);
        $this->producer = $this->createUser('producer', ['logto_user_id' => 'logto-farma', 'profile_completed_at' => '2026-01-01 10:00:00']);
        $this->school = $this->createUser('school', ['logto_user_id' => 'logto-skola', 'profile_completed_at' => '2026-01-01 10:00:00']);
    }

    /** @param array<string, mixed> $extra */
    private function newApp(array $extra = []): App
    {
        return AppFactory::create(array_replace_recursive([
            'db' => self::dbConfig(),
            'app' => ['var_dir' => $this->varDir],
            'cron' => ['key' => self::CRON_KEY],
            'logto' => [
                'endpoint' => FakeLogto::ENDPOINT,
                'app_id' => FakeLogto::CLIENT_ID,
                'app_secret' => FakeLogto::CLIENT_SECRET,
                'm2m_app_id' => 'm2m-id',
                'm2m_app_secret' => 'm2m-secret',
                'management_resource' => 'https://default.logto.app/api',
            ],
        ], $extra), $this->logto);
    }

    private function cleanup(string $now = '2026-10-13 01:00:00', string $today = '2026-10-13'): array
    {
        return $this->app->maintenance()->run(new DateTimeImmutable($now, new DateTimeZone('UTC')), $today);
    }

    private function value(string $sql): mixed
    {
        return $this->db()->query($sql)->fetchColumn();
    }

    private function audit(string $type, int $id): int
    {
        return (int) $this->value("SELECT COUNT(*) FROM audit_log WHERE target_type = '{$type}' AND target_id = {$id}");
    }

    // --- 1. vypršení ------------------------------------------------------------------------

    public function testExpiresOnlyListingsPastTheirEndDate(): void
    {
        $past = $this->insert('offers', $this->offerRow($this->producer, ['available_until' => '2026-10-12']));
        $lastDay = $this->insert('offers', $this->offerRow($this->producer, ['available_until' => '2026-10-13']));
        $open = $this->insert('offers', $this->offerRow($this->producer));
        $ended = $this->insert('offers', $this->offerRow($this->producer, ['available_until' => '2026-01-01', 'status' => 'ended']));
        $demand = $this->insert('demands', $this->demandRow($this->school, ['needed_from' => '2026-09-01', 'needed_until' => '2026-10-12']));

        $report = $this->cleanup();

        self::assertSame(1, $report['expired_offers']);
        self::assertSame(1, $report['expired_demands']);
        self::assertSame('expired', $this->value("SELECT status FROM offers WHERE id = {$past}"));
        self::assertSame('active', $this->value("SELECT status FROM offers WHERE id = {$lastDay}"), 'poslední den ještě platí');
        self::assertSame('active', $this->value("SELECT status FROM offers WHERE id = {$open}"));
        self::assertSame('ended', $this->value("SELECT status FROM offers WHERE id = {$ended}"), 'ukončené zůstávají');
        self::assertSame('expired', $this->value("SELECT status FROM demands WHERE id = {$demand}"));
        self::assertSame('offer.expired', $this->value("SELECT action FROM audit_log WHERE target_type = 'offer' AND target_id = {$past} AND actor_user_id IS NULL"));

        self::assertSame(0, $this->cleanup()['expired_offers'], 'druhý běh nic nemění');
    }

    // --- 2. smazání autorem a staré měkce smazané záznamy -------------------------------------

    public function testAuthorDeleteRemovesRecordAndHistoryImmediately(): void
    {
        AppFactory::$sessionData['user_id'] = $this->producer;
        AppFactory::$sessionData['user_role'] = 'producer';
        $this->app->handle(new Request('GET', '/'));
        $csrf = $this->app->csrf()->token();
        $this->app->handle(new Request('POST', '/moje-nabidky', [], Fixtures::offerForm() + ['_csrf' => $csrf]));
        $id = (int) $this->value('SELECT MAX(id) FROM offers');
        self::assertSame(1, $this->audit('offer', $id));

        $this->app->handle(new Request('POST', "/moje-nabidky/{$id}/smazat", [], ['confirm' => '1', '_csrf' => $csrf]));

        self::assertSame(0, (int) $this->value("SELECT COUNT(*) FROM offers WHERE id = {$id}"));
        self::assertSame(0, $this->audit('offer', $id), 'historie zmizí se záznamem');
    }

    public function testPurgesLegacySoftDeletedListings(): void
    {
        $id = $this->insert('offers', $this->offerRow($this->producer, ['deleted_at' => '2026-10-01 10:00:00']));
        $this->insert('audit_log', ['actor_user_id' => $this->producer, 'action' => 'offer.created', 'target_type' => 'offer', 'target_id' => $id]);
        self::assertSame(1, $this->cleanup()['purged_deleted']);
        self::assertSame(0, (int) $this->value("SELECT COUNT(*) FROM offers WHERE id = {$id}"));
        self::assertSame(0, $this->audit('offer', $id));
    }

    // --- 3. pozvánky ------------------------------------------------------------------------

    private function invitation(string $email, string $status, string $expiresAt, ?string $revokedAt = null, string $userStatus = 'invited'): array
    {
        $userId = $this->createUser('producer', ['email' => $email, 'status' => $userStatus, 'logto_user_id' => $userStatus === 'invited' ? null : 'logto-' . $email]);
        $id = $this->insert('invitations', [
            'user_id' => $userId, 'email' => $email, 'role' => 'producer', 'status' => $status, 'invited_by' => $this->admin,
            'created_at' => (new DateTimeImmutable($expiresAt))->modify('-7 days')->format('Y-m-d H:i:s'), 'expires_at' => $expiresAt,
            'revoked_at' => $revokedAt, 'accepted_at' => $status === 'accepted' ? $expiresAt : null,
        ]);
        $this->insert('audit_log', ['actor_user_id' => $this->admin, 'action' => 'invitation.created', 'target_type' => 'invitation', 'target_id' => $id]);
        return [$id, $userId];
    }

    public function testRemovesInvitationsThirtyDaysAfterExpiryWithUnfinishedAccount(): void
    {
        [$old, $oldUser] = $this->invitation('stara@example.cz', 'pending', '2026-09-12 10:00:00');      // vypršela před 31 dny
        [$recent, $recentUser] = $this->invitation('nedavna@example.cz', 'pending', '2026-09-20 10:00:00'); // před 23 dny
        [$revoked] = $this->invitation('zrusena@example.cz', 'revoked', '2026-12-01 10:00:00', '2026-09-01 10:00:00');
        [$accepted, $acceptedUser] = $this->invitation('prijata@example.cz', 'accepted', '2026-01-10 10:00:00', null, 'active');

        self::assertSame(2, $this->cleanup()['removed_invitations']);

        self::assertSame(0, (int) $this->value("SELECT COUNT(*) FROM invitations WHERE id IN ({$old}, {$revoked})"));
        self::assertSame(0, (int) $this->value("SELECT COUNT(*) FROM users WHERE id = {$oldUser}"), 'nedokončený účet zmizí');
        self::assertSame(0, $this->audit('invitation', $old));
        self::assertSame(1, (int) $this->value("SELECT COUNT(*) FROM invitations WHERE id = {$recent}"));
        self::assertSame(1, (int) $this->value("SELECT COUNT(*) FROM users WHERE id = {$recentUser}"));
        self::assertSame(1, (int) $this->value("SELECT COUNT(*) FROM invitations WHERE id = {$accepted}"), 'přijatá zůstává');
        self::assertSame('active', $this->value("SELECT status FROM users WHERE id = {$acceptedUser}"));
    }

    // --- 4. archivované účty ----------------------------------------------------------------

    private function archivedProducer(string $archivedAt): int
    {
        $id = $this->createUser('producer', ['status' => 'archived', 'archived_at' => $archivedAt, 'logto_user_id' => 'logto-arch-' . bin2hex(random_bytes(3))]);
        $this->insert('producer_profiles', [
            'user_id' => $id, 'contact_name' => 'Jan', 'producer_name' => 'Stará farma', 'address_line' => 'Náves 1',
            'municipality' => 'Buchlovice', 'postal_code' => '68708', 'phone' => '600000000', 'public_email' => 'stara@example.cz',
            'privacy_consent_at' => '2022-01-01 10:00:00', 'contact_publication_consent_at' => '2022-01-01 10:00:00',
        ]);
        $offer = $this->insert('offers', $this->offerRow($id, ['status' => 'ended']));
        $this->insert('audit_log', ['actor_user_id' => $id, 'action' => 'offer.created', 'target_type' => 'offer', 'target_id' => $offer]);
        $this->insert('audit_log', ['actor_user_id' => $this->admin, 'action' => 'user.archive', 'target_type' => 'user', 'target_id' => $id]);
        return $id;
    }

    public function testDeletesAccountsThreeYearsAfterArchivingIncludingLogto(): void
    {
        $old = $this->archivedProducer('2023-10-01 10:00:00');
        $young = $this->archivedProducer('2024-01-01 10:00:00');
        $logtoId = (string) $this->value("SELECT logto_user_id FROM users WHERE id = {$old}");

        $report = $this->cleanup();

        self::assertSame(1, $report['removed_accounts']);
        self::assertSame(0, (int) $this->value("SELECT COUNT(*) FROM users WHERE id = {$old}"));
        self::assertSame(0, (int) $this->value("SELECT COUNT(*) FROM producer_profiles WHERE user_id = {$old}"));
        self::assertSame(0, (int) $this->value("SELECT COUNT(*) FROM offers WHERE producer_user_id = {$old}"));
        self::assertSame(0, (int) $this->value("SELECT COUNT(*) FROM audit_log WHERE actor_user_id = {$old} OR (target_type = 'user' AND target_id = {$old})"));
        self::assertCount(1, $this->logto->managementCalls('DELETE', '/api/users/' . $logtoId));
        self::assertSame(1, (int) $this->value("SELECT COUNT(*) FROM users WHERE id = {$young}"), 'mladší než 3 roky zůstává');
        self::assertSame(1, (int) $this->value("SELECT COUNT(*) FROM users WHERE id = {$this->producer}"), 'aktivní účet se nemaže');
    }

    public function testKeepsAccountWhenLogtoDeletionFails(): void
    {
        $old = $this->archivedProducer('2023-01-01 10:00:00');
        $this->logto->failUserDeletion = true;
        $report = $this->cleanup();
        self::assertSame(0, $report['removed_accounts']);
        self::assertSame(1, $report['skipped_accounts']);
        self::assertSame(1, (int) $this->value("SELECT COUNT(*) FROM users WHERE id = {$old}"), 'zkusí se další noc');
    }

    public function testCanSkipLogtoDeletionByConfig(): void
    {
        $this->app = $this->newApp(['maintenance' => ['delete_logto_accounts' => false]]);
        $this->archivedProducer('2023-01-01 10:00:00');
        self::assertSame(1, $this->cleanup()['removed_accounts']);
        self::assertSame([], $this->logto->managementCalls('DELETE', '/api/users/'));
    }

    // --- 5. logy a stav ---------------------------------------------------------------------

    public function testRemovesLogFilesOlderThanNinetyDays(): void
    {
        $dir = $this->app->logger->directory();
        @mkdir($dir, 0775, true);
        file_put_contents($dir . '/app-2026-07-01.log', "stary\n");   // 104 dní
        file_put_contents($dir . '/app-2026-07-20.log', "novejsi\n"); // 85 dní
        $this->cleanup();
        self::assertFileDoesNotExist($dir . '/app-2026-07-01.log');
        self::assertFileExists($dir . '/app-2026-07-20.log');
        @unlink($dir . '/app-2026-07-20.log');
    }

    public function testCronUrlNeedsKeyAndRecordsLastRun(): void
    {
        self::assertSame(404, $this->app->handle(new Request('GET', '/cron/denni'))->status);
        self::assertSame(404, $this->app->handle(new Request('GET', '/cron/denni', ['klic' => 'spatny']))->status);
        self::assertNull(Maintenance::lastRun($this->app->maintenanceStateFile()));

        $response = $this->app->handle(new Request('GET', '/cron/denni', ['klic' => self::CRON_KEY]));
        self::assertSame(200, $response->status);
        self::assertStringStartsWith("OK\n", $response->body);
        self::assertNotNull(Maintenance::lastRun($this->app->maintenanceStateFile()));

        $noKey = $this->newApp(['cron' => ['key' => '']]);
        self::assertSame(404, $noKey->handle(new Request('GET', '/cron/denni', ['klic' => '']))->status, 'bez klíče je adresa vypnutá');
    }

    public function testAdminDashboardWarnsWhenCleanupDidNotRun(): void
    {
        AppFactory::$sessionData['user_id'] = $this->admin;
        AppFactory::$sessionData['user_role'] = 'admin';
        self::assertStringContainsString('Denní úklid zatím nikdy neproběhl', $this->app->handle(new Request('GET', '/admin'))->body);

        $this->app->handle(new Request('GET', '/cron/denni', ['klic' => self::CRON_KEY]));
        AppFactory::$sessionData['user_id'] = $this->admin;
        AppFactory::$sessionData['user_role'] = 'admin';
        $body = $this->app->handle(new Request('GET', '/admin'))->body;
        self::assertStringContainsString('Denní úklid naposledy proběhl', $body);
        self::assertStringNotContainsString('nikdy neproběhl', $body);
    }
}
