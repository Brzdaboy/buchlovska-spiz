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
use App\Repository\OfferRepository;
use App\Service\OfferValidator;

/**
 * Správa vlastních nabídek zemědělce (SPECIFIKACE §8, §10, §11; docs/ROZHODNUTI.md).
 * Každá akce ověří roli, aktivní účet a vlastnictví na serveru; cizí nabídka = 404.
 */
final class OfferController
{
    public function __construct(private App $app)
    {
    }

    public function register(Router $router): void
    {
        $router->get('/moje-nabidky', $this->index(...));
        $router->get('/moje-nabidky/nova', $this->create(...));
        $router->post('/moje-nabidky', $this->store(...));
        $router->get('/moje-nabidky/{id}/upravit', $this->edit(...));
        $router->post('/moje-nabidky/{id}', $this->update(...));
        $router->post('/moje-nabidky/{id}/ukoncit', $this->end(...));
        $router->get('/moje-nabidky/{id}/obnovit', $this->reactivateForm(...));
        $router->post('/moje-nabidky/{id}/obnovit', $this->reactivate(...));
        $router->get('/moje-nabidky/{id}/smazat', $this->deleteConfirm(...));
        $router->post('/moje-nabidky/{id}/smazat', $this->delete(...));
    }

    /** @return array<string, mixed> */
    private function producer(): array
    {
        $user = $this->app->auth()->requireActive();
        if ($user['role'] !== 'producer') {
            throw HttpException::forbidden('Nabídky vytvářejí jen zemědělci a producenti.');
        }
        return $user;
    }

    private function offers(): OfferRepository
    {
        return new OfferRepository($this->app->db->pdo());
    }

    private function catalog(): CatalogRepository
    {
        return new CatalogRepository($this->app->db->pdo());
    }

    /**
     * @param array<string, string> $params
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} [uživatel, nabídka]
     */
    private function owned(array $params): array
    {
        $user = $this->producer();
        $offer = $this->offers()->findOwned((int) $params['id'], (int) $user['id']);
        if ($offer === null) {
            throw HttpException::notFound();
        }
        return [$user, $offer];
    }

    public function index(): Response
    {
        $user = $this->producer();
        return $this->page('offers/index', 'Moje nabídky', [
            'offers' => $this->offers()->listOwned((int) $user['id']),
            'today' => today(),
        ]);
    }

    public function create(): Response
    {
        $this->producer();
        return $this->form(null, ['availability_type' => 'immediate'], [], 200);
    }

    public function store(Request $request): Response
    {
        $user = $this->producer();
        $result = $this->validate($request, null);
        if ($result['errors'] !== []) {
            return $this->form(null, $request->post, $result['errors'], 422);
        }
        $id = $this->offers()->create((int) $user['id'], $result['data']);
        $this->app->audit()->record((int) $user['id'], 'offer.created', 'offer', $id);
        $this->app->session()->flash('success', 'Nabídka je zveřejněná. Uvidí ji všichni přihlášení členové.');
        return Response::redirect('/moje-nabidky');
    }

    /** @param array<string, string> $params */
    public function edit(Request $request, array $params): Response
    {
        [, $offer] = $this->owned($params);
        // Čísla zobrazíme po česku a bez zbytečných nul (14,50 / 800).
        $values = ['unit_price' => str_replace('.', ',', (string) $offer['unit_price']),
            'available_quantity' => str_replace("\u{00A0}", '', format_number((string) $offer['available_quantity']))] + $offer;
        return $this->form($offer, $values, [], 200);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params): Response
    {
        [$user, $offer] = $this->owned($params);
        $result = $this->validate($request, $offer);
        if ($result['errors'] !== []) {
            return $this->form($offer, $request->post, $result['errors'], 422);
        }
        $newStatus = ListingState::afterEdit((string) $offer['status']);
        $this->offers()->updateContent((int) $offer['id'], (int) $user['id'], $result['data'], $newStatus);

        $changed = array_values(array_filter(
            OfferRepository::CONTENT_COLUMNS,
            static fn (string $c): bool => (string) ($offer[$c] ?? '') !== (string) ($result['data'][$c] ?? '')
        ));
        $this->app->audit()->record((int) $user['id'], 'offer.updated', 'offer', (int) $offer['id'], [
            'changed' => implode(',', $changed),
            'status' => $newStatus,
        ]);
        $this->app->session()->flash('success', $newStatus === ListingState::RESUBMITTED
            ? 'Oprava je uložená. Nabídka se zveřejní, až ji správce znovu uvolní.'
            : 'Změny jsou uložené a hned viditelné.');
        return Response::redirect('/moje-nabidky');
    }

    /** @param array<string, string> $params */
    public function end(Request $request, array $params): Response
    {
        [$user, $offer] = $this->owned($params);
        $effective = ListingState::effective((string) $offer['status'], $offer['available_until'], today());
        if (!ListingState::canEnd($effective)) {
            $this->app->session()->flash('error', 'Tuto nabídku nelze ukončit.');
            return Response::redirect('/moje-nabidky');
        }
        $this->offers()->end((int) $offer['id'], (int) $user['id']);
        $this->app->audit()->record((int) $user['id'], 'offer.ended', 'offer', (int) $offer['id']);
        $this->app->session()->flash('success', 'Nabídka je ukončená. Kdykoli ji můžete znovu zveřejnit.');
        return Response::redirect('/moje-nabidky');
    }

    /** @param array<string, string> $params */
    public function reactivateForm(Request $request, array $params): Response
    {
        [, $offer] = $this->owned($params);
        $this->assertReactivatable($offer);
        return $this->page('offers/reactivate', 'Znovu zveřejnit nabídku', ['offer' => $offer, 'errors' => [], 'value' => '']);
    }

    /** @param array<string, string> $params */
    public function reactivate(Request $request, array $params): Response
    {
        [$user, $offer] = $this->owned($params);
        $this->assertReactivatable($offer);
        $today = today();
        $needsNewDate = ListingState::reactivationNeedsNewDate($offer['available_until'], $today);
        $newUntil = trim((string) $request->input('available_until', ''));
        $changeUntil = $needsNewDate || $request->input('change_until') === '1';

        if ($changeUntil) {
            $error = null;
            if ($newUntil === '') {
                // Bez data: nabídka bude platit do ručního ukončení (SPECIFIKACE §10).
                $newUntil = null;
            } elseif (!OfferValidator::isDate($newUntil) || $newUntil < $today) {
                $error = 'Zadejte dnešní nebo budoucí datum, nebo pole nechte prázdné (nabídka bude platit do ručního ukončení).';
            } elseif ($offer['available_from'] !== null && $newUntil < $offer['available_from']) {
                $error = 'Konec platnosti musí být po datu dostupnosti.';
            }
            if ($error !== null) {
                return $this->page('offers/reactivate', 'Znovu zveřejnit nabídku', [
                    'offer' => $offer, 'errors' => ['available_until' => $error], 'value' => $newUntil,
                ], 422);
            }
        }

        $this->offers()->reactivate((int) $offer['id'], (int) $user['id'], $newUntil, $changeUntil);
        $this->app->audit()->record((int) $user['id'], 'offer.reactivated', 'offer', (int) $offer['id'], [
            'available_until' => $changeUntil ? $newUntil : $offer['available_until'],
        ]);
        $this->app->session()->flash('success', 'Nabídka je znovu zveřejněná.');
        return Response::redirect('/moje-nabidky');
    }

    /** @param array<string, string> $params */
    public function deleteConfirm(Request $request, array $params): Response
    {
        [, $offer] = $this->owned($params);
        return $this->page('offers/delete', 'Smazat nabídku', ['offer' => $offer]);
    }

    /** @param array<string, string> $params */
    public function delete(Request $request, array $params): Response
    {
        [$user, $offer] = $this->owned($params);
        if ($request->input('confirm') !== '1') {
            return Response::redirect('/moje-nabidky/' . (int) $offer['id'] . '/smazat');
        }
        $this->offers()->softDelete((int) $offer['id'], (int) $user['id']);
        $this->app->audit()->record((int) $user['id'], 'offer.deleted', 'offer', (int) $offer['id'], [
            'product_name' => (string) $offer['product_name'],
        ]);
        $this->app->session()->flash('success', 'Nabídka „' . $offer['product_name'] . '“ je smazaná.');
        return Response::redirect('/moje-nabidky');
    }

    /** @param array<string, mixed> $offer */
    private function assertReactivatable(array $offer): void
    {
        $effective = ListingState::effective((string) $offer['status'], $offer['available_until'], today());
        if (!ListingState::canReactivate($effective)) {
            throw HttpException::badRequest('Tuto nabídku nelze znovu zveřejnit.');
        }
    }

    /**
     * @param array<string, mixed>|null $existing
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    private function validate(Request $request, ?array $existing): array
    {
        $catalog = $this->catalog();
        return OfferValidator::validate($request->post, $catalog->activeCategories(), $catalog->activeUnits(), today(), $existing);
    }

    /**
     * @param array<string, mixed>|null $offer
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(?array $offer, array $values, array $errors, int $status): Response
    {
        $catalog = $this->catalog();
        return $this->page('offers/form', $offer === null ? 'Nová nabídka' : 'Upravit nabídku', [
            'offer' => $offer,
            'values' => $values,
            'errors' => $errors,
            'categories' => $catalog->activeCategories(),
            'units' => $catalog->activeUnits(),
            'today' => today(),
        ], $status);
    }

    /** @param array<string, mixed> $data */
    private function page(string $template, string $title, array $data = [], int $status = 200): Response
    {
        return Response::html($this->app->view->render($template, $data + ['title' => $title]), $status);
    }
}
