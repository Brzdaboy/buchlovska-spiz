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

        $pending = [
            'state' => OidcClient::randomToken(),
            'nonce' => OidcClient::randomToken(),
            'verifier' => OidcClient::randomToken(),
            'created' => time(),
        ];
        $session->set(self::PENDING_KEY, $pending);

        return Response::redirectExternal($oidc->authorizationUrl(
            $this->app->absoluteUrl('/callback'),
            $pending['state'],
            $pending['nonce'],
            $pending['verifier'],
        ));
    }

    public function callback(Request $request): Response
    {
        $oidc = $this->app->oidc() ?? throw HttpException::notFound();
        $session = $this->app->session();
        $pending = $session->pull(self::PENDING_KEY);

        // Uživatel v Logto přihlášení zrušil nebo nastala chyba na straně Logto.
        if ($request->query('error') !== null) {
            $this->app->logger->info('Logto vrátilo chybu', ['error' => $request->query('error')]);
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
