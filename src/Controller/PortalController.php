<?php

declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Domain\ListingState;
use App\Http\Response;
use App\Http\Router;
use App\Repository\DemandRepository;
use App\Repository\OfferRepository;

/**
 * Můj portál: jen vlastní záznamy (s jejich stavem) a vlastní kontaktní údaje.
 * Cizí nabídky a poptávky jsou na stránkách Nabídky a Poptávky.
 */
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
        $user = $this->app->auth()->requireActive();
        $role = (string) $user['role'];
        $pdo = $this->app->db->pdo();
        $today = today();
        $profile = in_array($role, ['producer', 'school'], true)
            ? $this->app->profiles()->find((int) $user['id'], $role)
            : null;

        $own = match ($role) {
            'producer' => (new OfferRepository($pdo))->listOwned((int) $user['id']),
            'school' => (new DemandRepository($pdo))->listOwned((int) $user['id']),
            default => [],
        };
        $summary = ['active' => 0, 'inactive' => 0, 'attention' => 0];
        foreach ($own as $i => $row) {
            $state = ListingState::effective((string) $row['status'], $row[$role === 'producer' ? 'available_until' : 'needed_until'], $today);
            $own[$i]['state'] = $state;
            $summary[match ($state) {
                ListingState::ACTIVE => 'active',
                ListingState::WITHDRAWN, ListingState::RESUBMITTED => 'attention',
                default => 'inactive',
            }]++;
        }

        return Response::html($this->app->view->render('portal/dashboard', [
            'title' => 'Můj portál',
            'user' => $user,
            'profile' => $profile,
            'summary' => $summary,
            'own' => $own,
        ]));
    }
}
