<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Domain\ListingState;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repository\CatalogRepository;
use App\Repository\DemandRepository;
use App\Service\DemandValidator;
use App\Service\OfferValidator;

/**
 * Správa vlastních poptávek školy (SPECIFIKACE §9, §10, §11; docs/ROZHODNUTI.md).
 * Stejná pravidla stavů jako u nabídek; konec platnosti = konec období potřeby.
 */
final class DemandController
{
    public function __construct(private App $app)
    {
    }

    public function register(Router $router): void
    {
        $router->get('/moje-poptavky', $this->index(...));
        $router->get('/moje-poptavky/nova', $this->create(...));
        $router->post('/moje-poptavky', $this->store(...));
        $router->get('/moje-poptavky/{id}/upravit', $this->edit(...));
        $router->post('/moje-poptavky/{id}', $this->update(...));
        $router->post('/moje-poptavky/{id}/ukoncit', $this->end(...));
        $router->get('/moje-poptavky/{id}/obnovit', $this->reactivateForm(...));
        $router->post('/moje-poptavky/{id}/obnovit', $this->reactivate(...));
        $router->get('/moje-poptavky/{id}/smazat', $this->deleteConfirm(...));
        $router->post('/moje-poptavky/{id}/smazat', $this->delete(...));
    }

    /** @return array<string, mixed> */
    private function school(): array
    {
        $user = $this->app->auth()->requireActive();
        if ($user['role'] !== 'school') {
            throw HttpException::forbidden('Poptávky vytvářejí jen školy a školní jídelny.');
        }
        return $user;
    }

    private function demands(): DemandRepository
    {
        return new DemandRepository($this->app->db->pdo());
    }

    private function catalog(): CatalogRepository
    {
        return new CatalogRepository($this->app->db->pdo());
    }

    /**
     * @param array<string, string> $params
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function owned(array $params): array
    {
        $user = $this->school();
        $demand = $this->demands()->findOwned((int) $params['id'], (int) $user['id']);
        if ($demand === null) {
            throw HttpException::notFound();
        }
        return [$user, $demand];
    }

    public function index(): Response
    {
        $user = $this->school();
        return $this->page('demands/index', 'Moje poptávky', [
            'demands' => $this->demands()->listOwned((int) $user['id']),
            'today' => today(),
        ]);
    }

    public function create(): Response
    {
        $this->school();
        return $this->form(null, [], [], 200);
    }

    public function store(Request $request): Response
    {
        $user = $this->school();
        $result = $this->validate($request, null);
        if ($result['errors'] !== []) {
            return $this->form(null, $request->post, $result['errors'], 422);
        }
        $id = $this->demands()->create((int) $user['id'], $result['data']);
        $this->app->audit()->record((int) $user['id'], 'demand.created', 'demand', $id);
        $this->app->session()->flash('success', 'Poptávka je zveřejněná. Uvidí ji všichni přihlášení členové.');
        return Response::redirect('/moje-poptavky');
    }

    /** @param array<string, string> $params */
    public function edit(Request $request, array $params): Response
    {
        [, $demand] = $this->owned($params);
        $values = ['requested_quantity' => str_replace("\u{00A0}", '', format_number((string) $demand['requested_quantity']))] + $demand;
        return $this->form($demand, $values, [], 200);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params): Response
    {
        [$user, $demand] = $this->owned($params);
        $result = $this->validate($request, $demand);
        if ($result['errors'] !== []) {
            return $this->form($demand, $request->post, $result['errors'], 422);
        }
        $newStatus = ListingState::afterEdit((string) $demand['status']);
        $this->demands()->updateContent((int) $demand['id'], (int) $user['id'], $result['data'], $newStatus);

        $changed = array_values(array_filter(
            DemandRepository::CONTENT_COLUMNS,
            static fn (string $c): bool => (string) ($demand[$c] ?? '') !== (string) ($result['data'][$c] ?? '')
        ));
        $this->app->audit()->record((int) $user['id'], 'demand.updated', 'demand', (int) $demand['id'], [
            'changed' => implode(',', $changed),
            'status' => $newStatus,
        ]);
        $this->app->session()->flash('success', $newStatus === ListingState::RESUBMITTED
            ? 'Oprava je uložená. Poptávka se zveřejní, až ji správce znovu uvolní.'
            : 'Změny jsou uložené a hned viditelné.');
        return Response::redirect('/moje-poptavky');
    }

    /** @param array<string, string> $params */
    public function end(Request $request, array $params): Response
    {
        [$user, $demand] = $this->owned($params);
        if (!ListingState::canEnd(ListingState::effective((string) $demand['status'], $demand['needed_until'], today()))) {
            $this->app->session()->flash('error', 'Tuto poptávku nelze ukončit.');
            return Response::redirect('/moje-poptavky');
        }
        $this->demands()->end((int) $demand['id'], (int) $user['id']);
        $this->app->audit()->record((int) $user['id'], 'demand.ended', 'demand', (int) $demand['id']);
        $this->app->session()->flash('success', 'Poptávka je ukončená. Kdykoli ji můžete znovu zveřejnit.');
        return Response::redirect('/moje-poptavky');
    }

    /** @param array<string, string> $params */
    public function reactivateForm(Request $request, array $params): Response
    {
        [, $demand] = $this->owned($params);
        $this->assertReactivatable($demand);
        return $this->reactivatePage($demand, ['needed_from' => $demand['needed_from'], 'needed_until' => $demand['needed_until']], [], 200);
    }

    /** @param array<string, string> $params */
    public function reactivate(Request $request, array $params): Response
    {
        [$user, $demand] = $this->owned($params);
        $this->assertReactivatable($demand);
        $today = today();
        $from = trim((string) $request->input('needed_from', (string) $demand['needed_from']));
        $until = trim((string) $request->input('needed_until', (string) $demand['needed_until']));

        $errors = [];
        if (!OfferValidator::isDate($from)) {
            $errors['needed_from'] = 'Zadejte, od kdy produkt potřebujete.';
        }
        if (!OfferValidator::isDate($until)) {
            $errors['needed_until'] = 'Zadejte, do kdy produkt potřebujete.';
        } elseif ($until < $today) {
            $errors['needed_until'] = 'Původní období už skončilo. Zadejte nový konec období (dnes nebo později).';
        } elseif (OfferValidator::isDate($from) && $until < $from) {
            $errors['needed_until'] = 'Konec období musí být po jeho začátku.';
        }
        if ($errors !== []) {
            return $this->reactivatePage($demand, ['needed_from' => $from, 'needed_until' => $until], $errors, 422);
        }

        $this->demands()->reactivate((int) $demand['id'], (int) $user['id'], $from, $until);
        $this->app->audit()->record((int) $user['id'], 'demand.reactivated', 'demand', (int) $demand['id'], [
            'needed_from' => $from,
            'needed_until' => $until,
        ]);
        $this->app->session()->flash('success', 'Poptávka je znovu zveřejněná.');
        return Response::redirect('/moje-poptavky');
    }

    /** @param array<string, string> $params */
    public function deleteConfirm(Request $request, array $params): Response
    {
        [, $demand] = $this->owned($params);
        return $this->page('demands/delete', 'Smazat poptávku', ['demand' => $demand]);
    }

    /** @param array<string, string> $params */
    public function delete(Request $request, array $params): Response
    {
        [$user, $demand] = $this->owned($params);
        if ($request->input('confirm') !== '1') {
            return Response::redirect('/moje-poptavky/' . (int) $demand['id'] . '/smazat');
        }
        $this->demands()->softDelete((int) $demand['id'], (int) $user['id']);
        $this->app->audit()->record((int) $user['id'], 'demand.deleted', 'demand', (int) $demand['id'], [
            'product_name' => (string) $demand['product_name'],
        ]);
        $this->app->session()->flash('success', 'Poptávka „' . $demand['product_name'] . '“ je smazaná.');
        return Response::redirect('/moje-poptavky');
    }

    /** @param array<string, mixed> $demand */
    private function assertReactivatable(array $demand): void
    {
        if (!ListingState::canReactivate(ListingState::effective((string) $demand['status'], $demand['needed_until'], today()))) {
            throw HttpException::badRequest('Tuto poptávku nelze znovu zveřejnit.');
        }
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    private function validate(Request $request, ?array $existing): array
    {
        $catalog = $this->catalog();
        return DemandValidator::validate($request->post, $catalog->activeCategories(), $catalog->activeUnits(), today(), $existing);
    }

    /**
     * @param array<string, mixed>|null $demand
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(?array $demand, array $values, array $errors, int $status): Response
    {
        $catalog = $this->catalog();
        return $this->page('demands/form', $demand === null ? 'Nová poptávka' : 'Upravit poptávku', [
            'demand' => $demand,
            'values' => $values,
            'errors' => $errors,
            'categories' => $catalog->activeCategories(),
            'units' => $catalog->activeUnits(),
            'today' => today(),
        ], $status);
    }

    /**
     * @param array<string, mixed> $demand
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function reactivatePage(array $demand, array $values, array $errors, int $status): Response
    {
        return $this->page('demands/reactivate', 'Znovu zveřejnit poptávku', [
            'demand' => $demand, 'values' => $values, 'errors' => $errors, 'today' => today(),
        ], $status);
    }

    /** @param array<string, mixed> $data */
    private function page(string $template, string $title, array $data = [], int $status = 200): Response
    {
        return Response::html($this->app->view->render($template, $data + ['title' => $title]), $status);
    }
}
