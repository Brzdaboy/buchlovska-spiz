<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Service\ProfileValidator;
use PDOException;

/**
 * Profil organizace (SPECIFIKACE §6, §7). Při prvním uložení se zaznamenají souhlasy
 * a účet se aktivuje; další změny se zveřejní okamžitě bez schvalování (§10).
 */
final class ProfileController
{
    public function __construct(private App $app)
    {
    }

    public function register(Router $router): void
    {
        $router->get('/profil', $this->show(...));
        $router->post('/profil', $this->save(...));
    }

    /** @return array<string, mixed> */
    private function member(): array
    {
        $user = $this->app->auth()->require();
        if (!in_array($user['role'], ['producer', 'school'], true)) {
            // Administrátor profil organizace nemá.
            throw \App\Http\HttpException::redirect('/admin');
        }
        return $user;
    }

    public function show(): Response
    {
        $user = $this->member();
        $profile = $this->app->profiles()->find((int) $user['id'], (string) $user['role']);
        return $this->form($user, $profile ?? [], $profile, [], 200);
    }

    public function save(Request $request): Response
    {
        $user = $this->member();
        $userId = (int) $user['id'];
        $role = (string) $user['role'];
        $existing = $this->app->profiles()->find($userId, $role);

        $result = ProfileValidator::validate($role, $request->post, $existing === null);
        if ($result['errors'] !== []) {
            return $this->form($user, $request->post, $existing, $result['errors'], 422);
        }

        $pdo = $this->app->db->pdo();
        $pdo->beginTransaction();
        try {
            $this->app->profiles()->save($userId, $role, $result['data']);
            $activated = $this->app->users()->activateAfterProfile($userId);
            $this->app->audit()->record($userId, $activated ? 'profile.completed' : 'profile.updated', 'user', $userId);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            // 23000 + duplicitní IČO: jiná organizace už má účet.
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'uq_')) {
                $field = $role === 'school' ? 'organization_id' : 'company_id';
                return $this->form($user, $request->post, $existing, [
                    $field => 'Organizace s tímto IČO už v Buchlovské spíži má účet. Jedna organizace může mít jen jeden účet; kontaktujte správce.',
                ], 422);
            }
            throw $e;
        }

        $this->app->session()->flash('success', $activated
            ? 'Profil je hotový. Vítejte v Buchlovské spíži!'
            : 'Změny profilu jsou uložené a hned viditelné ostatním členům.');
        return Response::redirect('/portal');
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $values
     * @param array<string, mixed>|null $existing
     * @param array<string, string> $errors
     */
    private function form(array $user, array $values, ?array $existing, array $errors, int $status): Response
    {
        return Response::html($this->app->view->render('profile/form', [
            'title' => $existing === null ? 'Dokončení profilu' : 'Můj profil',
            'role' => $user['role'],
            'values' => $values,
            'existing' => $existing,
            'errors' => $errors,
            'accountEmail' => $user['email'],
        ]), $status);
    }
}
