<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Http\Request;
use App\Http\Response;

/** Veřejné stránky. Nikdy nesmí číst data tržiště (nabídky, poptávky, profily). */
final class PublicController
{
    public function __construct(private App $app)
    {
    }

    /** @param array<string, string> $params */
    public function home(Request $request, array $params = []): Response
    {
        return Response::html($this->app->view->render('public/home', [
            'title' => null,
        ]));
    }
}
