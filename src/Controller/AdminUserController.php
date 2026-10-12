<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repository\AdminUserRepository;

/**
 * Admin a účty: přehled, detail, blokace, archivace a obnovení.
 * Blokace platí okamžitě (stav se kontroluje při každém požadavku) a záznamy
 * zablokovaného uživatele zmizí z tržiště.
 */
final class AdminUserController
{
    use AdminAccess;

    private const ROLES = ['zemedelci' => 'producer', 'skoly' => 'school', 'spravci' => 'admin'];
    private const STATUSES = ['aktivni' => 'active', 'neuplny-profil' => 'profile_incomplete', 'blokovani' => 'blocked', 'archiv' => 'archived'];

    public function __construct(private App $app)
    {
    }

    public function register(Router $router): void
    {
        $router->get('/admin/uzivatele', $this->index(...));
        $router->get('/admin/uzivatele/{id}', $this->detail(...));
        $router->post('/admin/uzivatele/{id}/blokovat', fn (Request $r, array $p): Response => $this->change((int) $p['id'], 'block'));
        $router->post('/admin/uzivatele/{id}/obnovit', fn (Request $r, array $p): Response => $this->change((int) $p['id'], 'restore'));
        $router->post('/admin/uzivatele/{id}/archivovat', fn (Request $r, array $p): Response => $this->change((int) $p['id'], 'archive'));
    }

    private function users(): AdminUserRepository
    {
        return new AdminUserRepository($this->app->db->pdo());
    }

    public function index(Request $request): Response
    {
        $this->admin();
        $role = self::ROLES[$request->query('role', '')] ?? null;
        $status = self::STATUSES[$request->query('stav', '')] ?? null;
        return $this->page('admin/users', 'Uživatelé', [
            'users' => $this->users()->list($role, $status),
            'roleFilter' => (string) array_search($role, self::ROLES, true),
            'statusFilter' => (string) array_search($status, self::STATUSES, true),
        ]);
    }

    /** @param array<string, string> $params */
    public function detail(Request $request, array $params): Response
    {
        $admin = $this->admin();
        $user = $this->users()->find((int) $params['id']) ?? throw HttpException::notFound();
        return $this->page('admin/user-detail', (string) ($user['organization'] ?? $user['email']), [
            'user' => $user,
            'listings' => $this->users()->listings((int) $user['id']),
            'isSelf' => (int) $user['id'] === (int) $admin['id'],
            'today' => today(),
        ]);
    }

    private function change(int $id, string $action): Response
    {
        $admin = $this->admin();
        $users = $this->users();
        $ok = match ($action) {
            'block' => $users->block($id),
            'restore' => $users->restore($id),
            'archive' => $users->archive($id),
        };
        $path = '/admin/uzivatele/' . $id;
        if (!$ok) {
            return $this->back($path, 'error', 'Tuto změnu nelze u tohoto účtu provést.');
        }
        $this->app->audit()->record((int) $admin['id'], 'user.' . $action, 'user', $id);
        return $this->back($path, 'success', match ($action) {
            'block' => 'Účet je zablokovaný. Uživatel je odhlášen a jeho záznamy nejsou v tržišti vidět.',
            'restore' => 'Účet je znovu aktivní.',
            'archive' => 'Účet je archivovaný.',
        });
    }
}
