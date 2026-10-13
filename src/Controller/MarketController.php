<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Domain\ListingType;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repository\CatalogRepository;
use App\Repository\MarketRepository;
use App\Service\MarketFilter;

/**
 * Tržiště pro přihlášené (SPECIFIKACE §10–12): seznamy Nabídky a Poptávky s hledáním,
 * filtry a stránkováním po 12, detail s kontakty. Vidí ho všechny role s aktivním účtem.
 * Nezveřejněný, vypršelý, smazaný záznam nebo záznam blokovaného autora = 404.
 */
final class MarketController
{
    /** Zobrazení seznamu: hodnota v URL => interní klíč. Volba se pamatuje v session (pro nabídky i poptávky). */
    public const VIEWS = ['radky' => 'rows', 'dlazdice' => 'tiles'];
    private const VIEW_SESSION_KEY = 'market_view';

    public function __construct(private App $app)
    {
    }

    public function register(Router $router): void
    {
        $router->get('/nabidky', fn (Request $r): Response => $this->index($r, ListingType::of('offer')));
        $router->get('/nabidky/{id}', fn (Request $r, array $p): Response => $this->show($p, ListingType::of('offer')));
        $router->get('/poptavky', fn (Request $r): Response => $this->index($r, ListingType::of('demand')));
        $router->get('/poptavky/{id}', fn (Request $r, array $p): Response => $this->show($p, ListingType::of('demand')));
        $router->get('/zemedelci/{id}', fn (Request $r, array $p): Response => $this->member((int) $p['id'], 'producer', $r));
        $router->get('/skoly/{id}', fn (Request $r, array $p): Response => $this->member((int) $p['id'], 'school', $r));
    }

    private function market(): MarketRepository
    {
        return new MarketRepository($this->app->db->pdo());
    }

    private function index(Request $request, ListingType $type): Response
    {
        $user = $this->app->auth()->requireActive();
        $catalog = new CatalogRepository($this->app->db->pdo());
        $isOffer = $type->key === 'offer';
        $units = $catalog->activeUnits();
        $filter = MarketFilter::fromQuery($request->query, $catalog->activeCategoryOptions(), $units, $isOffer);
        $result = $isOffer ? $this->market()->offers($filter, today()) : $this->market()->demands($filter, today());
        $view = $this->view($request);

        return Response::html($this->app->view->render('market/index', [
            'title' => $isOffer ? 'Nabídky zemědělců' : 'Poptávky škol',
            'type' => $type,
            'user' => $user,
            'filter' => $filter->withPage($result['page']),
            'result' => $result,
            'categories' => $catalog->activeCategoryOptions(),
            'units' => $units,
            'view' => $view,
        ]));
    }

    /** Řádky (výchozí), nebo dlaždice. Volbu z odkazu ?zobrazeni=… si zapamatuje. */
    private function view(Request $request): string
    {
        $session = $this->app->session();
        $requested = $request->query('zobrazeni');
        if ($requested !== null && isset(self::VIEWS[$requested])) {
            $session->set(self::VIEW_SESSION_KEY, $requested);
        }
        $stored = $session->get(self::VIEW_SESSION_KEY);
        return self::VIEWS[is_string($stored) && isset(self::VIEWS[$stored]) ? $stored : 'radky'];
    }

    /** @param array<string, string> $params */
    private function show(array $params, ListingType $type): Response
    {
        $user = $this->app->auth()->requireActive();
        $id = (int) $params['id'];
        $item = $type->key === 'offer' ? $this->market()->offer($id, today()) : $this->market()->demand($id, today());
        if ($item === null) {
            throw HttpException::notFound();
        }
        $ownerId = (int) ($item['producer_user_id'] ?? $item['school_user_id']);
        return Response::html($this->app->view->render('market/detail', [
            'title' => $item['product_name'] . ' · ' . $type->label(),
            'type' => $type,
            'item' => $item,
            'isOwn' => $ownerId === (int) $user['id'],
        ]));
    }

    /** Veřejný (pro členy) profil zemědělce nebo školy se zveřejněnými záznamy. Neaktivní účet = 404. */
    private function member(int $id, string $role, Request $request): Response
    {
        $this->app->auth()->requireActive();
        $member = $this->market()->member($role, $id);
        if ($member === null) {
            throw HttpException::notFound();
        }
        $isProducer = $role === 'producer';
        return Response::html($this->app->view->render('market/member', [
            'title' => (string) ($isProducer ? $member['producer_name'] : $member['school_name']),
            'member' => $member,
            'isProducer' => $isProducer,
            'type' => ListingType::of($isProducer ? 'offer' : 'demand'),
            'items' => $isProducer ? $this->market()->offersOf($id, today()) : $this->market()->demandsOf($id, today()),
            'view' => $this->view($request),
        ]));
    }
}
