<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\HttpException;
use App\Http\Response;

/**
 * Společná kontrola pro administraci: jen aktivní admin (ověřeno z DB při každém požadavku).
 * Vyžaduje vlastnost $app (App\App).
 */
trait AdminAccess
{
    /** @return array<string, mixed> */
    private function admin(): array
    {
        $user = $this->app->auth()->requireRole('admin');
        if ($user['status'] !== 'active') {
            throw HttpException::forbidden();
        }
        return $user;
    }

    /** @param array<string, mixed> $data */
    private function page(string $template, string $title, array $data = [], int $status = 200): Response
    {
        return Response::html($this->app->view->render($template, $data + ['title' => $title]), $status);
    }

    private function back(string $path, string $type, string $message): Response
    {
        $this->app->session()->flash($type, $message);
        return Response::redirect($path);
    }
}
