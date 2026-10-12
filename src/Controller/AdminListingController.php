<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Domain\ListingType;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repository\AdminListingRepository;
use App\Repository\AdminUserRepository;

/**
 * Admin a záznamy tržiště: přehled nových/upravených, detail (jen pro čtení),
 * stažení s povinným důvodem a uvolnění. Obsah záznamů admin nikdy nemění.
 */
final class AdminListingController
{
    use AdminAccess;

    private const FILTERS = ['nove' => 'new', 'k-uvolneni' => 'resubmitted', 'stazene' => 'withdrawn', 'vse' => 'all'];

    public function __construct(private App $app)
    {
    }

    public function register(Router $router): void
    {
        $router->get('/admin/zaznamy', $this->overview(...));
        $router->post('/admin/zaznamy/prohlednuto', $this->markSeen(...));
        foreach (['offer', 'demand'] as $key) {
            $segment = ListingType::of($key)->segment();
            $router->get("/admin/{$segment}/{id}", fn (Request $r, array $p): Response => $this->detail($key, (int) $p['id']));
            $router->post("/admin/{$segment}/{id}/stahnout", fn (Request $r, array $p): Response => $this->withdraw($key, (int) $p['id'], $r));
            $router->post("/admin/{$segment}/{id}/uvolnit", fn (Request $r, array $p): Response => $this->release($key, (int) $p['id']));
        }
    }

    private function listings(): AdminListingRepository
    {
        return new AdminListingRepository($this->app->db->pdo());
    }

    public function overview(Request $request): Response
    {
        $admin = $this->admin();
        $filterKey = $request->query('filtr', 'nove');
        $filter = self::FILTERS[$filterKey] ?? 'new';
        $seen = $admin['last_review_seen_at'] ?? null;
        return $this->page('admin/listings', 'Záznamy', [
            'items' => $this->listings()->overview($filter, is_string($seen) ? $seen : null),
            'filter' => array_search($filter, self::FILTERS, true),
            'seenSince' => $seen,
            'counts' => $this->listings()->counts(is_string($seen) ? $seen : null),
            'today' => today(),
        ]);
    }

    public function markSeen(): Response
    {
        $admin = $this->admin();
        (new AdminUserRepository($this->app->db->pdo()))->markReviewSeen((int) $admin['id']);
        return $this->back('/admin/zaznamy', 'success', 'Vše je označené jako prohlédnuté. Příště uvidíte jen novinky.');
    }

    private function detail(string $key, int $id, array $errors = [], string $reason = '', int $status = 200): Response
    {
        $this->admin();
        $type = ListingType::of($key);
        $item = $this->listings()->find($type, $id) ?? throw HttpException::notFound();
        return $this->page('admin/listing-detail', $type->label() . ': ' . $item['product_name'], [
            'type' => $type,
            'item' => $item,
            'history' => $this->listings()->history($type, $id),
            'errors' => $errors,
            'reason' => $reason,
            'today' => today(),
        ], $status);
    }

    private function withdraw(string $key, int $id, Request $request): Response
    {
        $admin = $this->admin();
        $type = ListingType::of($key);
        $reason = trim((string) preg_replace('/\s+/u', ' ', (string) $request->input('reason', '')));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 1000) {
            return $this->detail($key, $id, ['reason' => 'Napište důvod stažení (5–1000 znaků). Autor ho uvidí ve svém portálu.'], $reason, 422);
        }
        if (!$this->listings()->withdraw($type, $id, (int) $admin['id'], $reason)) {
            return $this->back($this->url($type, $id), 'error', 'Stáhnout lze jen zveřejněný záznam.');
        }
        $this->app->audit()->record((int) $admin['id'], $key . '.withdrawn', $key, $id, ['reason' => $reason]);
        return $this->back($this->url($type, $id), 'success', 'Záznam je stažený z tržiště. Autor uvidí důvod a může ho opravit.');
    }

    private function release(string $key, int $id): Response
    {
        $admin = $this->admin();
        $type = ListingType::of($key);
        if (!$this->listings()->release($type, $id)) {
            return $this->back($this->url($type, $id), 'error', 'Uvolnit lze jen stažený záznam.');
        }
        $this->app->audit()->record((int) $admin['id'], $key . '.released', $key, $id);
        return $this->back($this->url($type, $id), 'success', 'Záznam je znovu zveřejněný.');
    }

    private function url(ListingType $type, int $id): string
    {
        return '/admin/' . $type->segment() . '/' . $id;
    }
}
