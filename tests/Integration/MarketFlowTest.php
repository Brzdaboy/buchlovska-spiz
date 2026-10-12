<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\App;
use App\Http\Request;
use App\Http\Response;
use Tests\AppFactory;

/** Tržiště: viditelnost, hledání, filtry, stránkování, detail s kontakty, dashboard. */
final class MarketFlowTest extends DatabaseTestCase
{
    protected bool $useTransaction = false;
    private App $app;
    private int $producer;
    private int $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = AppFactory::create(['db' => self::dbConfig()]);
        $this->producer = $this->producerWithProfile('Farma Pod Barborkou', '+420 600 123 456', 'farma@example.cz');
        $this->school = $this->createUser('school', ['profile_completed_at' => '2026-10-01 10:00:00']);
        $this->insert('school_profiles', [
            'user_id' => $this->school, 'school_name' => 'ZŠ a MŠ Buchlovice', 'contact_name' => 'Marie Malá',
            'address_line' => 'Školní 1', 'municipality' => 'Buchlovice', 'postal_code' => '68708',
            'phone' => '572 000 000', 'public_email' => 'jidelna@zs.example.cz', 'website' => 'https://zs.example.cz',
            'privacy_consent_at' => '2026-10-01 10:00:00', 'contact_publication_consent_at' => '2026-10-01 10:00:00',
        ]);
        $this->as($this->school, 'school');
    }

    private function producerWithProfile(string $name, string $phone, string $email): int
    {
        $id = $this->createUser('producer', ['profile_completed_at' => '2026-10-01 10:00:00']);
        $this->insert('producer_profiles', [
            'user_id' => $id, 'contact_name' => 'Jan Novák', 'producer_name' => $name, 'address_line' => 'Náves 12',
            'municipality' => 'Buchlovice', 'postal_code' => '68708', 'phone' => $phone, 'public_email' => $email,
            'privacy_consent_at' => '2026-10-01 10:00:00', 'contact_publication_consent_at' => '2026-10-01 10:00:00',
        ]);
        return $id;
    }

    private function as(int $userId, string $role): void
    {
        AppFactory::$sessionData['user_id'] = $userId;
        AppFactory::$sessionData['user_role'] = $role;
    }

    /** @param array<string, string> $query */
    private function get(string $path, array $query = []): Response
    {
        return $this->app->handle(new Request('GET', $path, $query));
    }

    private static function day(int $offset): string
    {
        return date('Y-m-d', strtotime(today() . " {$offset} days"));
    }

    /** @param array<string, scalar|null> $overrides */
    private function offer(array $overrides = []): int
    {
        return $this->insert('offers', $this->offerRow($this->producer, $overrides));
    }

    /** @param array<string, scalar|null> $overrides */
    private function demand(array $overrides = []): int
    {
        return $this->insert('demands', $this->demandRow($this->school, $overrides + [
            'needed_from' => self::day(10), 'needed_until' => self::day(40),
        ]));
    }

    private static function cards(Response $response): int
    {
        return substr_count($response->body, 'class="market-item"');
    }

    // --- přístup a viditelnost ------------------------------------------------------------

    public function testMarketplaceRequiresSignIn(): void
    {
        $offer = $this->offer();
        $demand = $this->demand();
        AppFactory::$sessionData = [];
        foreach (['/nabidky', "/nabidky/{$offer}", '/poptavky', "/poptavky/{$demand}"] as $path) {
            $response = $this->get($path);
            self::assertContains($response->status, [302, 303], $path);
            self::assertStringNotContainsString('Brambory', $response->body);
            self::assertStringNotContainsString('600 123 456', $response->body);
        }
    }

    public function testSignedInMenuShowsOnlyMarketAndPortal(): void
    {
        $body = $this->get('/nabidky')->body;
        preg_match('#<nav class="nav nav-desktop"[^>]*>(.*?)</nav>#s', $body, $m);
        preg_match_all('#<a [^>]*>([^<]+)</a>#', $m[1] ?? '', $links);
        self::assertSame(['Nabídky', 'Poptávky', 'Můj portál'], array_map('trim', $links[1]));
        self::assertStringContainsString('href="/kontakt"', $body, 'kontakt zůstává v patičce');
    }

    public function testFlashMessagesFloatAndOnlySuccessHidesItself(): void
    {
        AppFactory::$sessionData['_flash'] = [
            ['type' => 'success', 'message' => 'Nabídka je zveřejněná.'],
            ['type' => 'error', 'message' => 'Něco se nepovedlo <b>'],
        ];
        $body = $this->get('/nabidky')->body;
        self::assertStringContainsString('<div class="toast toast-success" role="status" data-autohide="5000">', $body);
        self::assertStringContainsString('<div class="toast toast-error" role="alert">', $body);
        self::assertStringContainsString('Něco se nepovedlo &lt;b&gt;', $body);
        self::assertStringContainsString('<script src="/assets/js/app.js" defer></script>', $body);
        self::assertStringNotContainsString('class="toast', $this->get('/nabidky')->body, 'hláška se ukáže jen jednou');
    }

    public function testIncompleteProfileIsSentToProfileForm(): void
    {
        $incomplete = $this->createUser('school', ['status' => 'profile_incomplete']);
        $this->as($incomplete, 'school');
        $response = $this->get('/nabidky');
        self::assertSame('/profil', $response->headers['Location'] ?? null);
    }

    public function testEveryRoleSeesBothListsAndContacts(): void
    {
        $offer = $this->offer();
        $demand = $this->demand();
        $admin = $this->createUser('admin');
        foreach ([[$this->producer, 'producer'], [$this->school, 'school'], [$admin, 'admin']] as [$id, $role]) {
            $this->as($id, $role);
            self::assertSame(1, self::cards($this->get('/nabidky')), $role);
            self::assertSame(1, self::cards($this->get('/poptavky')), $role);
            $detail = $this->get("/nabidky/{$offer}")->body;
            self::assertStringContainsString('href="tel:+420600123456"', $detail);
            self::assertStringContainsString('mailto:farma@example.cz', $detail);
            $detail = $this->get("/poptavky/{$demand}")->body;
            self::assertStringContainsString('ZŠ a MŠ Buchlovice', $detail);
            self::assertStringContainsString('mailto:jidelna@zs.example.cz', $detail);
        }
    }

    public function testOnlyPublishedListingsAreVisible(): void
    {
        $visible = $this->offer(['product_name' => 'Viditelná']);
        $lastDay = $this->offer(['product_name' => 'Poslední den', 'available_until' => today()]);
        $admin = $this->createUser('admin');
        $hidden = [
            $this->offer(['product_name' => 'Ukončená', 'status' => 'ended']),
            $this->offer(['product_name' => 'Vypršelá', 'available_until' => self::day(-1)]),
            $this->offer(['product_name' => 'Stažená', 'status' => 'withdrawn', 'withdrawal_reason' => 'Důvod', 'withdrawn_at' => '2026-10-01 10:00:00', 'withdrawn_by' => $admin]),
            $this->offer(['product_name' => 'Opravená', 'status' => 'resubmitted', 'withdrawal_reason' => 'Důvod', 'withdrawn_at' => '2026-10-01 10:00:00', 'withdrawn_by' => $admin]),
            $this->offer(['product_name' => 'Smazaná', 'deleted_at' => '2026-10-01 10:00:00']),
        ];
        $blocked = $this->producerWithProfile('Blokovaná farma', '600 000 001', 'blok@example.cz');
        $hidden[] = $this->insert('offers', $this->offerRow($blocked, ['product_name' => 'Od blokovaného']));
        $this->db()->exec("UPDATE users SET status = 'blocked' WHERE id = {$blocked}");

        $list = $this->get('/nabidky');
        self::assertSame(2, self::cards($list));
        self::assertStringContainsString('Viditelná', $list->body);
        self::assertStringContainsString('Poslední den', $list->body);
        self::assertSame(200, $this->get("/nabidky/{$visible}")->status);
        self::assertSame(200, $this->get("/nabidky/{$lastDay}")->status);
        foreach ($hidden as $id) {
            self::assertSame(404, $this->get("/nabidky/{$id}")->status, "nabídka {$id}");
        }
        // Ani autor nezveřejněný záznam na tržišti nevidí (má ho ve svém portálu).
        $this->as($this->producer, 'producer');
        self::assertSame(404, $this->get("/nabidky/{$hidden[0]}")->status);
    }

    public function testDemandVisibilityEndsWithNeededPeriod(): void
    {
        $this->demand(['product_name' => 'Aktuální']);
        $this->demand(['product_name' => 'Prošlá', 'needed_from' => self::day(-20), 'needed_until' => self::day(-1)]);
        $this->demand(['product_name' => 'Ukončená', 'status' => 'ended']);
        $list = $this->get('/poptavky');
        self::assertSame(1, self::cards($list));
        self::assertStringContainsString('Aktuální', $list->body);
    }

    public function testUnknownIdIsNotFound(): void
    {
        self::assertSame(404, $this->get('/nabidky/999999')->status);
        self::assertSame(404, $this->get('/poptavky/999999')->status);
    }

    // --- hledání a filtry ----------------------------------------------------------------

    public function testSearchByProductName(): void
    {
        $this->offer(['product_name' => 'Brambory Adéla']);
        $this->offer(['product_name' => 'Jablka Idared']);
        $this->offer(['product_name' => 'Sleva 100% jistá']);
        self::assertSame(1, self::cards($this->get('/nabidky', ['q' => 'brambory'])));
        self::assertSame(1, self::cards($this->get('/nabidky', ['q' => '  JABLKA '])));
        // Zástupné znaky LIKE se berou doslova.
        self::assertSame(1, self::cards($this->get('/nabidky', ['q' => '100%'])));
        self::assertSame(1, self::cards($this->get('/nabidky', ['q' => '%'])));
        self::assertSame(0, self::cards($this->get('/nabidky', ['q' => '_'])));
        $none = $this->get('/nabidky', ['q' => 'mrkev']);
        self::assertSame(0, self::cards($none));
        self::assertStringContainsString('Nic jsme nenašli', $none->body);
    }

    public function testCategoryFilter(): void
    {
        $this->offer(['product_name' => 'Brambory']);
        $this->offer(['product_name' => 'Jablka', 'category_id' => $this->idOf('categories', 'slug', 'ovoce')]);
        $ovoce = $this->get('/nabidky', ['kategorie' => 'ovoce']);
        self::assertSame(1, self::cards($ovoce));
        self::assertStringContainsString('Jablka', $ovoce->body);
        self::assertSame(2, self::cards($this->get('/nabidky', ['kategorie' => 'neexistuje'])), 'neznámá kategorie se ignoruje');
    }

    public function testOfferPeriodFilter(): void
    {
        $this->offer(['product_name' => 'Hned']);
        $this->offer(['product_name' => 'Pozdeji', 'availability_type' => 'from_date', 'available_from' => self::day(30)]);
        $this->offer(['product_name' => 'Kratce', 'available_until' => self::day(5)]);

        $soon = $this->get('/nabidky', ['od' => self::day(1), 'do' => self::day(10)])->body;
        self::assertStringContainsString('Hned', $soon);
        self::assertStringContainsString('Kratce', $soon);
        self::assertStringNotContainsString('Pozdeji', $soon);

        $later = $this->get('/nabidky', ['od' => self::day(20)])->body;
        self::assertStringContainsString('Hned', $later);
        self::assertStringContainsString('Pozdeji', $later);
        self::assertStringNotContainsString('Kratce', $later);
    }

    public function testDemandPeriodFilter(): void
    {
        $this->demand(['product_name' => 'Listopad', 'needed_from' => self::day(10), 'needed_until' => self::day(40)]);
        $this->demand(['product_name' => 'Jaro', 'needed_from' => self::day(150), 'needed_until' => self::day(180)]);
        $body = $this->get('/poptavky', ['od' => self::day(0), 'do' => self::day(60)])->body;
        self::assertStringContainsString('Listopad', $body);
        self::assertStringNotContainsString('Jaro', $body);
        self::assertSame(1, self::cards($this->get('/poptavky', ['od' => self::day(170)])));
    }

    public function testInvalidPeriodIsExplainedAndIgnored(): void
    {
        $this->offer();
        $response = $this->get('/nabidky', ['od' => self::day(10), 'do' => self::day(1)]);
        self::assertStringContainsString('Konec období musí být po jeho začátku.', $response->body);
        self::assertSame(1, self::cards($response));
        self::assertStringContainsString('Zadejte datum.', $this->get('/nabidky', ['od' => '31. 2.'])->body);
    }

    public function testPriceFilterNeedsUnit(): void
    {
        $this->offer(['product_name' => 'Levné kg', 'unit_price' => '14.50']);
        $this->offer(['product_name' => 'Drahé kg', 'unit_price' => '22.00']);
        $this->offer(['product_name' => 'Za kus', 'unit_price' => '5.00', 'price_unit_id' => $this->idOf('units', 'code', 'ks')]);

        $noUnit = $this->get('/nabidky', ['cena_do' => '15']);
        self::assertSame(3, self::cards($noUnit), 'bez jednotky se cena nepoužije');
        self::assertStringContainsString('Cenu lze porovnat jen u stejné jednotky.', $noUnit->body);

        $kg = $this->get('/nabidky', ['cena_do' => '15', 'jednotka' => 'kg'])->body;
        self::assertStringContainsString('Levné kg', $kg);
        self::assertStringNotContainsString('Drahé kg', $kg);
        self::assertStringNotContainsString('Za kus', $kg);

        self::assertSame(2, self::cards($this->get('/nabidky', ['jednotka' => 'kg'])), 'jednotka sama filtruje cenovou jednotku');
        self::assertSame(1, self::cards($this->get('/nabidky', ['cena_do' => '14,50', 'jednotka' => 'kg'])));
        self::assertStringContainsString('Zadejte cenu v Kč', $this->get('/nabidky', ['cena_do' => 'levně', 'jednotka' => 'kg'])->body);
    }

    public function testRowsByDefaultTilesOnRequestAndRemembered(): void
    {
        $this->offer();
        $this->demand();
        $body = $this->get('/nabidky')->body;
        self::assertStringContainsString('<ul class="market-rows">', $body);
        self::assertStringNotContainsString('<img', substr($body, strpos($body, '<main')), 'bez fotografií');

        self::assertStringContainsString('<ul class="market-tiles">', $this->get('/nabidky', ['zobrazeni' => 'dlazdice'])->body);
        self::assertStringContainsString('<ul class="market-tiles">', $this->get('/nabidky')->body, 'volba se pamatuje');
        self::assertStringContainsString('<ul class="market-tiles">', $this->get('/poptavky')->body, 'i pro poptávky');
        self::assertStringContainsString('<ul class="market-rows">', $this->get('/poptavky', ['zobrazeni' => 'radky'])->body);
        self::assertStringContainsString('<ul class="market-rows">', $this->get('/nabidky', ['zobrazeni' => '<x>'])->body, 'neplatná hodnota se ignoruje');

        $switch = $this->get('/nabidky', ['q' => 'brambory'])->body;
        self::assertStringContainsString('href="/nabidky?q=brambory&amp;zobrazeni=dlazdice"', $switch, 'přepínač drží hledání');
    }

    public function testFiltersAreCollapsedUntilUsed(): void
    {
        $this->offer();
        self::assertStringContainsString('<details class="market-more">', $this->get('/nabidky')->body);
        self::assertStringContainsString('<details class="market-more">', $this->get('/nabidky', ['q' => 'brambory'])->body, 'samotné hledání panel neotevírá');

        $filtered = $this->get('/nabidky', ['kategorie' => 'zelenina', 'od' => self::day(1), 'jednotka' => 'kg'])->body;
        self::assertStringContainsString('<details class="market-more" open>', $filtered);
        self::assertStringContainsString('class="filter-count" aria-label="aktivní: 3">3</span>', $filtered);
        self::assertStringContainsString('<details class="market-more" open>', $this->get('/nabidky', ['cena_do' => '10'])->body, 'chyba ve filtru je vidět');
    }

    // --- řazení a stránkování -------------------------------------------------------------

    public function testNewestFirstAndPagesOfTwelve(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            $this->offer(['product_name' => sprintf('Produkt %02d', $i), 'created_at' => sprintf('2026-10-01 10:%02d:00', $i)]);
        }
        $first = $this->get('/nabidky');
        self::assertSame(12, self::cards($first));
        self::assertLessThan(strpos($first->body, 'Produkt 12'), strpos($first->body, 'Produkt 13'));
        self::assertStringNotContainsString('Produkt 01', $first->body);
        self::assertStringContainsString('href="/nabidky?strana=2"', $first->body);

        $second = $this->get('/nabidky', ['strana' => '2']);
        self::assertSame(1, self::cards($second));
        self::assertStringContainsString('Produkt 01', $second->body);

        self::assertStringContainsString('Produkt 01', $this->get('/nabidky', ['strana' => '99'])->body, 'za koncem = poslední stránka');
        self::assertSame(12, self::cards($this->get('/nabidky', ['strana' => 'x'])));
    }

    public function testPaginationKeepsFilters(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            $this->offer(['product_name' => "Jablko {$i}"]);
        }
        $body = $this->get('/nabidky', ['q' => 'jablko', 'cena_do' => '20', 'jednotka' => 'kg'])->body;
        self::assertStringContainsString('href="/nabidky?q=jablko&amp;jednotka=kg&amp;cena_do=20&amp;strana=2"', $body);
    }

    // --- bezpečný výstup ------------------------------------------------------------------

    public function testUserTextIsEscaped(): void
    {
        $id = $this->offer(['product_name' => '<script>alert(1)</script>', 'delivery_terms' => '<img src=x onerror=alert(1)>']);
        $list = $this->get('/nabidky', ['q' => '<script>'])->body;
        self::assertStringNotContainsString('<script>alert', $list);
        self::assertStringContainsString('&lt;script&gt;', $list);
        $detail = $this->get("/nabidky/{$id}")->body;
        self::assertStringNotContainsString('<img src=x', $detail);
    }

    // --- dashboard ------------------------------------------------------------------------

    public function testPortalShowsOnlyOwnListingsAndDetails(): void
    {
        $this->offer(['product_name' => 'Moje brambory']);
        $this->offer(['product_name' => 'Moje ukončené mrkve', 'status' => 'ended']);
        $other = $this->producerWithProfile('Cizí farma', '600 999 999', 'cizi@example.cz');
        $this->insert('offers', $this->offerRow($other, ['product_name' => 'Cizí jablka']));
        $this->demand(['product_name' => 'Hledáme hrušky']);

        $this->as($this->producer, 'producer');
        $body = $this->get('/portal')->body;
        self::assertStringContainsString('Moje brambory', $body);
        self::assertStringContainsString('Moje ukončené mrkve', $body, 'vlastní záznamy včetně neaktivních');
        self::assertStringNotContainsString('Cizí jablka', $body);
        self::assertStringNotContainsString('Hledáme hrušky', $body);
        self::assertStringNotContainsString('Nejnovější', $body);
        self::assertMatchesRegularExpression('#badge-ok">1</span> zveřejněno#', $body);
        self::assertMatchesRegularExpression('#badge-muted">1</span> ukončeno#', $body);
        self::assertStringContainsString('Moje údaje', $body);
        self::assertStringContainsString('+420 600 123 456', $body);

        $this->as($this->school, 'school');
        $body = $this->get('/portal')->body;
        self::assertStringContainsString('Hledáme hrušky', $body);
        self::assertStringNotContainsString('Moje brambory', $body);
        self::assertStringContainsString('Přidat poptávku', $body);
    }
}
