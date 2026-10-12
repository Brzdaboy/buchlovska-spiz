<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Auth\AuthException;
use App\Auth\LoginDenied;
use App\Auth\OidcClient;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

/**
 * Přihlášení a odhlášení přes Logto (OpenID Connect).
 *   GET  /prihlaseni  -> přesměrování na Logto
 *   GET  /callback    -> návrat z Logto, ověření, propojení s lokálním účtem
 *   POST /odhlaseni   -> zrušení session a odhlášení v Logto
 */
final class AuthController
{
    private const PENDING_KEY = 'oidc_pending';
    private const PENDING_TTL = 600; // 10 minut na dokončení přihlášení

    public function __construct(private App $app)
    {
    }

    public function register(Router $router): void
    {
        $router->get('/prihlaseni', $this->login(...));
        $router->get('/callback', $this->callback(...));
        $router->get('/pozvanka', $this->acceptInvitation(...));
        $router->post('/odhlaseni', $this->logout(...));
    }

    public function login(Request $request): Response
    {
        $session = $this->app->session();
        if (is_int($session->get('user_id'))) {
            return Response::redirect('/portal');
        }
        $oidc = $this->app->oidc();
        if ($oidc === null) {
            return $this->page('public/login-placeholder', 'Přihlášení', [], 200);
        }
        return $this->redirectToLogto($oidc, []);
    }

    /**
     * Odkaz z e-mailové pozvánky: ověří lokální pozvánku a pošle uživatele do Logto
     * s jednorázovým tokenem (registrace funguje i při vypnuté veřejné registraci).
     */
    public function acceptInvitation(Request $request): Response
    {
        $oidc = $this->app->oidc() ?? throw HttpException::notFound();
        $id = $request->query('id');
        $token = $request->query('token');
        if (!is_string($id) || !ctype_digit($id) || !is_string($token) || !preg_match('/^[A-Za-z0-9_-]{8,256}$/', $token)) {
            return $this->invitationProblem('Odkaz z pozvánky je neúplný. Zkopírujte ho prosím z e-mailu celý.');
        }
        $invitation = $this->app->invitations()->findById((int) $id);
        $expired = $invitation !== null && strtotime((string) $invitation['expires_at'] . ' UTC') <= time();

        if ($invitation === null || $invitation['status'] === 'revoked') {
            return $this->invitationProblem('Tato pozvánka už neplatí. Pokud jste dostali novější, použijte odkaz z ní.');
        }
        if ($invitation['status'] === 'accepted') {
            return Response::redirect('/prihlaseni');
        }
        if ($invitation['status'] === 'expired' || $expired) {
            return $this->invitationProblem('Platnost pozvánky vypršela. Požádejte správce o novou.');
        }

        // Případné staré přihlášení v tomto prohlížeči ukončíme.
        $this->app->auth()->signOut();
        return $this->redirectToLogto($oidc, [
            'one_time_token' => $token,
            'login_hint' => (string) $invitation['email'],
        ], (int) $invitation['id']);
    }

    /** @param array<string, string> $extraParams */
    private function redirectToLogto(OidcClient $oidc, array $extraParams, ?int $invitationId = null): Response
    {
        $pending = [
            'state' => OidcClient::randomToken(),
            'nonce' => OidcClient::randomToken(),
            'verifier' => OidcClient::randomToken(),
            'created' => time(),
            'invitation_id' => $invitationId,
        ];
        $this->app->session()->set(self::PENDING_KEY, $pending);

        return Response::redirectExternal($oidc->authorizationUrl(
            $this->app->absoluteUrl('/callback'),
            $pending['state'],
            $pending['nonce'],
            $pending['verifier'],
            $extraParams,
        ));
    }

    private function invitationProblem(string $message): Response
    {
        return $this->page('auth/invitation-problem', 'Pozvánka', ['message' => $message], 410);
    }

    public function callback(Request $request): Response
    {
        $oidc = $this->app->oidc() ?? throw HttpException::notFound();
        $session = $this->app->session();
        $pending = $session->pull(self::PENDING_KEY);

        // Uživatel v Logto přihlášení zrušil nebo nastala chyba na straně Logto.
        if ($request->query('error') !== null) {
            $this->app->logger->info('Logto vrátilo chybu', [
                'error' => mb_substr((string) $request->query('error'), 0, 100),
                // Popis chyby od Logto (neobsahuje tajemství) – nutný pro ladění konfigurace.
                'description' => mb_substr((string) $request->query('error_description', ''), 0, 300),
            ]);
            return $this->failure('Přihlášení se nedokončilo. Zkuste to prosím znovu.', 400);
        }

        $state = $request->query('state');
        $code = $request->query('code');
        if (
            !is_array($pending) || !is_string($state) || !is_string($code) || $code === ''
            || !hash_equals((string) ($pending['state'] ?? ''), $state)
            || (int) ($pending['created'] ?? 0) < time() - self::PENDING_TTL
        ) {
            return $this->failure('Platnost přihlášení vypršela nebo odkaz není platný. Zkuste se přihlásit znovu.', 400);
        }

        try {
            $claims = $oidc->completeLogin($code, $this->app->absoluteUrl('/callback'), (string) $pending['verifier'], (string) $pending['nonce']);
        } catch (AuthException $e) {
            $this->app->logger->error('Ověření přihlášení selhalo: ' . $e->getMessage());
            return $this->failure('Přihlášení se nepodařilo ověřit. Zkuste to prosím znovu.', 400);
        }

        try {
            $user = $this->app->loginService()->resolve($claims);
        } catch (LoginDenied $e) {
            $this->app->logger->info('Přihlášení odmítnuto', ['reason' => $e->reason]);
            return $this->failure($e->userMessage(), 403);
        }

        $this->app->auth()->signIn($user);
        $this->app->users()->touchLogin((int) $user['id']);
        if (($pending['invitation_id'] ?? null) !== null) {
            $this->app->invitationService()?->markAcceptedInLogto((int) $user['id'], (string) $claims['sub']);
        }
        return Response::redirect('/portal');
    }

    public function logout(Request $request): Response
    {
        $this->app->auth()->signOut();
        $oidc = $this->app->oidc();
        if ($oidc === null) {
            return Response::redirect('/');
        }
        return Response::redirectExternal($oidc->endSessionUrl($this->app->absoluteUrl('/')));
    }

    private function failure(string $message, int $status): Response
    {
        return $this->page('auth/failure', 'Přihlášení', ['message' => $message], $status);
    }

    /** @param array<string, mixed> $data */
    private function page(string $template, string $title, array $data, int $status): Response
    {
        return Response::html($this->app->view->render($template, $data + ['title' => $title]), $status);
    }
}
