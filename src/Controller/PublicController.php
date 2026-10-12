<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Http\Response;
use App\Http\Router;

/**
 * Veřejné stránky. NIKDY nesmí číst data tržiště (nabídky, poptávky, profily,
 * kontakty členů) ani se připojovat k databázi — viz SPECIFIKACE §2 a §16.
 */
final class PublicController
{
    /** cesta => [šablona, titulek, popis] */
    private const PAGES = [
        '/o-projektu' => ['public/about', 'O projektu', 'Proč vznikla Buchlovská spíž a jak propojuje místní zemědělce se školními jídelnami.'],
        '/jak-ziskat-pristup' => ['public/access', 'Jak získat přístup', 'Registrace do Buchlovské spíže je jen na pozvánku. Jak ji získat a co následuje.'],
    ];

    public function __construct(private App $app)
    {
    }

    public function register(Router $router): void
    {
        $router->get('/', $this->home(...));
        foreach (array_keys(self::PAGES) as $path) {
            $router->get($path, fn (): Response => $this->staticPage($path));
        }
        $router->get('/kontakt', $this->contact(...));
        $router->get('/ochrana-osobnich-udaju', fn (): Response => $this->legal('Ochrana osobních údajů'));
        $router->get('/podminky-pouzivani', fn (): Response => $this->legal('Podmínky používání'));
    }

    public function home(): Response
    {
        return $this->page('public/home', null);
    }

    private function staticPage(string $path): Response
    {
        [$template, $title, $description] = self::PAGES[$path];
        return $this->page($template, $title, ['description' => $description]);
    }

    public function contact(): Response
    {
        $config = $this->app->config;
        return $this->page('public/contact', 'Kontakt', [
            'operator' => [
                'name' => trim((string) $config->get('site.operator_name', '')),
                'email' => trim((string) $config->get('site.contact_email', '')),
                'phone' => trim((string) $config->get('site.contact_phone', '')),
                'address' => trim((string) $config->get('site.contact_address', '')),
            ],
        ]);
    }

    private function legal(string $heading): Response
    {
        return $this->page('public/legal', $heading, ['heading' => $heading]);
    }

    /** @param array<string, mixed> $data */
    private function page(string $template, ?string $title, array $data = []): Response
    {
        return Response::html($this->app->view->render($template, $data + ['title' => $title]));
    }
}
