<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Service\InvitationError;
use App\Service\InvitationService;

/**
 * Administrace. Každá akce ověřuje roli admin a aktivní účet na serveru.
 * Admin nikdy neupravuje obsah nabídek a poptávek (SPECIFIKACE §3).
 */
final class AdminController
{
    use AdminAccess;

    public function __construct(private App $app)
    {
    }

    public function register(Router $router): void
    {
        $router->get('/admin', $this->dashboard(...));
        $router->get('/admin/pozvanky', $this->invitations(...));
        $router->post('/admin/pozvanky', $this->createInvitation(...));
        $router->post('/admin/pozvanky/{id}/znovu', $this->resendInvitation(...));
        $router->post('/admin/pozvanky/{id}/zrusit', $this->revokeInvitation(...));
    }

    public function dashboard(): Response
    {
        $admin = $this->admin();
        $pdo = $this->app->db->pdo();
        $seen = is_string($admin['last_review_seen_at'] ?? null) ? $admin['last_review_seen_at'] : null;
        $pendingInvitations = (int) $pdo->query(
            "SELECT COUNT(*) FROM invitations WHERE status = 'pending' AND expires_at > UTC_TIMESTAMP()"
        )->fetchColumn();
        return $this->page('admin/dashboard', 'Administrace', [
            'counts' => (new \App\Repository\AdminListingRepository($pdo))->counts($seen),
            'users' => (new \App\Repository\AdminUserRepository($pdo))->activeCounts(),
            'pendingInvitations' => $pendingInvitations,
            'maintenance' => \App\Service\Maintenance::lastRun($this->app->maintenanceStateFile()),
        ]);
    }

    public function invitations(): Response
    {
        $this->admin();
        $session = $this->app->session();
        return $this->page('admin/invitations', 'Pozvánky', [
            'invitations' => $this->app->invitations()->listForAdmin(),
            'roles' => InvitationService::ROLES,
            'configured' => $this->app->invitationService() !== null,
            'old' => $session->pull('old_input', []),
        ]);
    }

    public function createInvitation(Request $request): Response
    {
        $admin = $this->admin();
        $email = (string) $request->input('email', '');
        $role = (string) $request->input('role', '');
        return $this->run(function (InvitationService $service) use ($email, $role, $admin): string {
            $service->invite($email, $role, (int) $admin['id']);
            return 'Pozvánka pro ' . mb_strtolower(trim($email)) . ' byla odeslána. Platí 7 dní.';
        }, ['email' => $email, 'role' => $role]);
    }

    /** @param array<string, string> $params */
    public function resendInvitation(Request $request, array $params): Response
    {
        $admin = $this->admin();
        return $this->run(function (InvitationService $service) use ($params, $admin): string {
            $service->resend((int) $params['id'], (int) $admin['id']);
            return 'Nová pozvánka byla odeslána, původní odkaz už neplatí.';
        });
    }

    /** @param array<string, string> $params */
    public function revokeInvitation(Request $request, array $params): Response
    {
        $admin = $this->admin();
        return $this->run(function (InvitationService $service) use ($params, $admin): string {
            $service->revoke((int) $params['id'], (int) $admin['id']);
            return 'Pozvánka byla zrušena.';
        });
    }

    /**
     * Provede akci se službou pozvánek a vrátí se na přehled (Post/Redirect/Get).
     *
     * @param callable(InvitationService): string $action
     * @param array<string, string> $oldInput
     */
    private function run(callable $action, array $oldInput = []): Response
    {
        $session = $this->app->session();
        $service = $this->app->invitationService();
        if ($service === null) {
            $session->flash('error', 'Pozvánky nejsou nastavené: v config.php chybí údaje Logto (M2M aplikace a ID organizace).');
            return Response::redirect('/admin/pozvanky');
        }
        try {
            $session->flash('success', $action($service));
        } catch (InvitationError $e) {
            $session->flash('error', $e->getMessage());
            $session->set('old_input', $oldInput);
        }
        return Response::redirect('/admin/pozvanky');
    }

}
