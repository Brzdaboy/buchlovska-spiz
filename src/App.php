<?php

declare(strict_types=1);

namespace App;

use App\Auth\CurrentUser;
use App\Auth\LoginService;
use App\Auth\LogtoManagement;
use App\Auth\OidcClient;
use App\Controller\AdminController;
use App\Controller\AuthController;
use App\Controller\PortalController;
use App\Controller\ProfileController;
use App\Controller\PublicController;
use App\Repository\AuditLog;
use App\Repository\InvitationRepository;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Service\InvitationService;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Security\Csrf;
use App\Security\SecurityHeaders;
use App\Security\Session;
use App\Support\Config;
use App\Support\CurlHttpClient;
use App\Support\HttpClient;
use App\Support\Database;
use App\Support\ErrorHandler;
use App\Support\Logger;
use App\View\View;
use Closure;
use Throwable;

/**
 * Jádro aplikace: spustí session, ověří CSRF u každého POST,
 * předá požadavek routeru a ke každé odpovědi přidá bezpečnostní hlavičky.
 */
final class App
{
    public readonly Router $router;
    public readonly View $view;
    public readonly Database $db;
    public readonly HttpClient $http;
    private ?Session $session = null;
    private ?Csrf $csrf = null;
    private ?CurrentUser $currentUser = null;
    private ?OidcClient $oidc = null;

    /** @param Closure(): Session $sessionFactory */
    public function __construct(
        public readonly Config $config,
        public readonly Logger $logger,
        private Closure $sessionFactory,
        private string $projectRoot,
        ?HttpClient $http = null,
    ) {
        $this->http = $http ?? new CurlHttpClient();
        $this->router = new Router();
        $this->view = new View($projectRoot . '/templates', ['config' => $config]);
        $this->db = new Database($config);
        $this->registerRoutes();
    }

    /** Vstupní bod z public/index.php. */
    public static function boot(string $projectRoot): void
    {
        $logger = new Logger($projectRoot . '/var/log');
        try {
            $config = Config::fromFile(Config::locate($projectRoot));
        } catch (Throwable $e) {
            $logger->error('Konfiguraci nelze načíst', ['message' => $e->getMessage()]);
            (new Response(ErrorHandler::fallbackPage(503), 503, ['Content-Type' => 'text/html; charset=utf-8']))->send();
            return;
        }
        date_default_timezone_set((string) $config->get('app.timezone', 'Europe/Prague'));
        ErrorHandler::register($logger, $config->isDebug());

        $app = new self($config, $logger, static fn (): Session => Session::startNative($config), $projectRoot);
        $app->handle(Request::fromGlobals())->send();
    }

    public function handle(Request $request): Response
    {
        try {
            $this->session = ($this->sessionFactory)();
            $this->csrf = new Csrf($this->session);
            $this->currentUser = null;
            $this->view->share('csrf', $this->csrf);
            $this->view->share('session', $this->session);
            $this->view->share('currentPath', $request->path);

            if (!in_array($request->method, ['GET', 'HEAD', 'POST'], true)) {
                throw HttpException::methodNotAllowed(['GET', 'HEAD', 'POST']);
            }
            if ($request->isPost()) {
                $submitted = $request->input(Csrf::FIELD) ?? $request->header('x-csrf-token');
                if (!$this->csrf->isValid($submitted)) {
                    throw HttpException::csrf();
                }
            }
            $response = $this->router->dispatch($request);
        } catch (HttpException $e) {
            $response = $e->isRedirect()
                ? new Response('', $e->status)
                : $this->errorResponse($e->status, $e->getMessage());
            foreach ($e->headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }
        } catch (Throwable $e) {
            $this->logger->error(get_class($e) . ': ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'path' => $request->path,
            ]);
            $detail = $this->config->isDebug() ? get_class($e) . ': ' . $e->getMessage() : null;
            $response = $this->errorResponse(500, 'Něco se pokazilo. Zkuste to prosím za chvíli znovu.', $detail);
        }

        if ($request->method === 'HEAD') {
            $response->body = '';
        }
        return SecurityHeaders::apply(
            $response,
            $this->config->isHttps(),
            SecurityHeaders::originOf($this->config->get('logto.endpoint')),
        );
    }

    public function session(): Session
    {
        return $this->session ?? throw new \LogicException('Session ještě neběží.');
    }

    public function csrf(): Csrf
    {
        return $this->csrf ?? throw new \LogicException('Session ještě neběží.');
    }

    /** Přihlášený uživatel aktuálního požadavku (databáze se načte až při prvním použití). */
    public function auth(): CurrentUser
    {
        return $this->currentUser ??= new CurrentUser($this->session(), $this->users());
    }

    public function users(): UserRepository
    {
        return new UserRepository($this->db->pdo());
    }

    public function loginService(): LoginService
    {
        $pdo = $this->db->pdo();
        return new LoginService($pdo, new UserRepository($pdo), new InvitationRepository($pdo), new AuditLog($pdo));
    }

    /** OIDC klient, nebo null, pokud Logto ještě není v konfiguraci vyplněné. */
    public function oidc(): ?OidcClient
    {
        $endpoint = (string) $this->config->get('logto.endpoint', '');
        $appId = (string) $this->config->get('logto.app_id', '');
        $secret = (string) $this->config->get('logto.app_secret', '');
        if (!str_starts_with($endpoint, 'https://') || str_contains($endpoint, '<') || $appId === '' || $secret === '') {
            return null;
        }
        $cacheDir = rtrim((string) $this->config->get('app.var_dir', $this->projectRoot . '/var'), '/') . '/cache';
        return $this->oidc ??= new OidcClient($this->http, $endpoint, $appId, $secret, $cacheDir);
    }

    /** Klient Logto Management API, nebo null, pokud M2M aplikace není nastavená. */
    public function logtoManagement(): ?LogtoManagement
    {
        $endpoint = (string) $this->config->get('logto.endpoint', '');
        $id = (string) $this->config->get('logto.m2m_app_id', '');
        $secret = (string) $this->config->get('logto.m2m_app_secret', '');
        $resource = (string) $this->config->get('logto.management_resource', '');
        if ($this->oidc() === null || $id === '' || $secret === '' || !str_starts_with($resource, 'https://') || str_contains($resource, '<')) {
            return null;
        }
        return new LogtoManagement($this->http, $endpoint, $id, $secret, $resource);
    }

    /** Služba pozvánek, nebo null, pokud chybí nastavení Logto (M2M + organizace). */
    public function invitationService(): ?InvitationService
    {
        $management = $this->logtoManagement();
        $organizationId = (string) $this->config->get('logto.members_organization_id', '');
        if ($management === null || $organizationId === '') {
            return null;
        }
        $pdo = $this->db->pdo();
        return new InvitationService(
            $pdo,
            new UserRepository($pdo),
            new InvitationRepository($pdo),
            new AuditLog($pdo),
            $management,
            $organizationId,
            fn (int $id, string $token): string => $this->absoluteUrl('/pozvanka?' . http_build_query(['id' => $id, 'token' => $token])),
            $this->logger,
        );
    }

    public function profiles(): ProfileRepository
    {
        return new ProfileRepository($this->db->pdo());
    }

    public function audit(): AuditLog
    {
        return new AuditLog($this->db->pdo());
    }

    public function invitations(): InvitationRepository
    {
        return new InvitationRepository($this->db->pdo());
    }

    /** Absolutní adresa v rámci webu (pro redirect_uri u Logto). */
    public function absoluteUrl(string $path): string
    {
        return rtrim((string) $this->config->get('app.url'), '/') . $path;
    }

    private function errorResponse(int $status, string $message, ?string $debugDetail = null): Response
    {
        try {
            return Response::html($this->view->render('errors/error', [
                'title' => $status === 404 ? 'Stránka nenalezena' : 'Chyba',
                'status' => $status,
                'message' => $message,
                'debugDetail' => $debugDetail,
            ]), $status);
        } catch (Throwable $e) {
            $this->logger->error('Chybovou stránku nelze vykreslit: ' . $e->getMessage());
            return new Response(ErrorHandler::fallbackPage($status), $status, ['Content-Type' => 'text/html; charset=utf-8']);
        }
    }

    private function registerRoutes(): void
    {
        (new PublicController($this))->register($this->router);
        (new AuthController($this))->register($this->router);
        (new PortalController($this))->register($this->router);
        (new ProfileController($this))->register($this->router);
        (new AdminController($this))->register($this->router);
    }
}
