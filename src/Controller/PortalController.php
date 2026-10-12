<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Http\Response;
use App\Http\Router;

/** Přihlášená část. Zatím jen rozcestník; obsah přibude v dalších krocích. */
final class PortalController
{
    public function __construct(private App $app)
    {
    }

    public function register(Router $router): void
    {
        $router->get('/portal', $this->dashboard(...));
    }

    public function dashboard(): Response
    {
        $user = $this->app->auth()->require();
        return Response::html($this->app->view->render('portal/dashboard', [
            'title' => 'Můj portál',
            'user' => $user,
        ]));
    }
}
