<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repository\CatalogRepository;
use PDOException;

/**
 * Číselníky: kategorie a jednotky (SPECIFIKACE §14). Položky se nemažou,
 * jen deaktivují: použité záznamy si je ponechají, nové je nenabídnou.
 */
final class AdminCatalogController
{
    use AdminAccess;

    public function __construct(private App $app)
    {
    }

    public function register(Router $router): void
    {
        $router->get('/admin/ciselniky', $this->index(...));
        $router->post('/admin/kategorie', $this->createCategory(...));
        $router->post('/admin/kategorie/{id}', $this->updateCategory(...));
        $router->post('/admin/jednotky', $this->createUnit(...));
        $router->post('/admin/jednotky/{id}', $this->updateUnit(...));
    }

    private function catalog(): CatalogRepository
    {
        return new CatalogRepository($this->app->db->pdo());
    }

    public function index(): Response
    {
        $this->admin();
        return $this->page('admin/catalog', 'Číselníky', [
            'categories' => $this->catalog()->allCategories(),
            'units' => $this->catalog()->allUnits(),
        ]);
    }

    public function createCategory(Request $request): Response
    {
        $admin = $this->admin();
        $name = $this->name($request->input('name', ''), 100);
        $slug = CatalogRepository::slugify($name);
        if ($name === '' || $slug === '') {
            return $this->back('/admin/ciselniky', 'error', 'Zadejte název kategorie (nejvýš 100 znaků).');
        }
        return $this->guardDuplicate(function () use ($admin, $name, $slug, $request): Response {
            $id = $this->catalog()->createCategory($name, $slug, $this->sort($request));
            $this->app->audit()->record((int) $admin['id'], 'category.created', 'category', $id, ['name' => $name]);
            return $this->back('/admin/ciselniky', 'success', "Kategorie „{$name}“ je přidaná.");
        }, 'Kategorie s tímto názvem už existuje.');
    }

    /** @param array<string, string> $params */
    public function updateCategory(Request $request, array $params): Response
    {
        $admin = $this->admin();
        $id = (int) $params['id'];
        if (!$this->catalog()->categoryExists($id)) {
            throw HttpException::notFound();
        }
        $name = $this->name($request->input('name', ''), 100);
        if ($name === '') {
            return $this->back('/admin/ciselniky', 'error', 'Název kategorie nesmí být prázdný.');
        }
        $active = $request->input('is_active') === '1';
        return $this->guardDuplicate(function () use ($admin, $id, $name, $active, $request): Response {
            $this->catalog()->updateCategory($id, $name, $this->sort($request), $active);
            $this->app->audit()->record((int) $admin['id'], 'category.updated', 'category', $id, ['name' => $name, 'active' => $active]);
            return $this->back('/admin/ciselniky', 'success', "Kategorie „{$name}“ je uložená.");
        }, 'Kategorie s tímto názvem už existuje.');
    }

    public function createUnit(Request $request): Response
    {
        $admin = $this->admin();
        $code = trim((string) $request->input('code', ''));
        $label = $this->name($request->input('label', ''), 30);
        if (!preg_match('/^[\p{L}0-9.]{1,8}$/u', $code) || $label === '') {
            return $this->back('/admin/ciselniky', 'error', 'Zadejte zkratku jednotky (1–8 znaků, např. „bal“) a její název.');
        }
        return $this->guardDuplicate(function () use ($admin, $code, $label, $request): Response {
            $id = $this->catalog()->createUnit($code, $label, $this->sort($request));
            $this->app->audit()->record((int) $admin['id'], 'unit.created', 'unit', $id, ['code' => $code]);
            return $this->back('/admin/ciselniky', 'success', "Jednotka „{$code}“ je přidaná.");
        }, 'Jednotka s touto zkratkou už existuje.');
    }

    /** @param array<string, string> $params */
    public function updateUnit(Request $request, array $params): Response
    {
        $admin = $this->admin();
        $id = (int) $params['id'];
        if (!$this->catalog()->unitExists($id)) {
            throw HttpException::notFound();
        }
        $label = $this->name($request->input('label', ''), 30);
        if ($label === '') {
            return $this->back('/admin/ciselniky', 'error', 'Název jednotky nesmí být prázdný.');
        }
        $active = $request->input('is_active') === '1';
        $this->catalog()->updateUnit($id, $label, $this->sort($request), $active);
        $this->app->audit()->record((int) $admin['id'], 'unit.updated', 'unit', $id, ['label' => $label, 'active' => $active]);
        return $this->back('/admin/ciselniky', 'success', "Jednotka „{$label}“ je uložená.");
    }

    private function name(?string $value, int $max): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $value));
        return mb_strlen($value) <= $max ? $value : '';
    }

    private function sort(Request $request): int
    {
        $value = (string) $request->input('sort_order', '0');
        return preg_match('/^-?\d{1,4}$/', $value) ? (int) $value : 0;
    }

    /** @param callable(): Response $action */
    private function guardDuplicate(callable $action, string $message): Response
    {
        try {
            return $action();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return $this->back('/admin/ciselniky', 'error', $message);
            }
            throw $e;
        }
    }
}
