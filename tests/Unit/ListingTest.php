<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\ListingState as S;
use App\Service\OfferValidator;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fixtures;

final class ListingTest extends TestCase
{
    private const TODAY = '2026-10-12';
    private const CATEGORIES = [1 => 'Zelenina', 2 => 'Ovoce'];
    private const UNITS = [1 => 'kg', 6 => 'ks'];

    // --- stavový automat --------------------------------------------------------

    public function testActiveWithPastEndDateIsExpired(): void
    {
        self::assertSame(S::EXPIRED, S::effective(S::ACTIVE, '2026-10-11', self::TODAY));
        self::assertSame(S::ACTIVE, S::effective(S::ACTIVE, '2026-10-12', self::TODAY), 'poslední den ještě platí');
        self::assertSame(S::ACTIVE, S::effective(S::ACTIVE, null, self::TODAY), 'bez data platí do ukončení');
        self::assertSame(S::ENDED, S::effective(S::ENDED, '2026-01-01', self::TODAY));
    }

    public function testEditingWithdrawnListingSendsItForRelease(): void
    {
        self::assertSame(S::RESUBMITTED, S::afterEdit(S::WITHDRAWN));
        foreach ([S::ACTIVE, S::ENDED, S::EXPIRED, S::RESUBMITTED] as $status) {
            self::assertSame($status, S::afterEdit($status), "úprava {$status} stav nemění (bez schvalování)");
        }
    }

    public function testEndAndReactivateRules(): void
    {
        self::assertTrue(S::canEnd(S::ACTIVE));
        foreach ([S::ENDED, S::EXPIRED, S::WITHDRAWN, S::RESUBMITTED] as $status) {
            self::assertFalse(S::canEnd($status));
        }
        self::assertTrue(S::canReactivate(S::ENDED));
        self::assertTrue(S::canReactivate(S::EXPIRED));
        foreach ([S::ACTIVE, S::WITHDRAWN, S::RESUBMITTED] as $status) {
            self::assertFalse(S::canReactivate($status), "{$status}: stažený záznam uvolňuje jen admin");
        }
        self::assertTrue(S::reactivationNeedsNewDate('2026-10-01', self::TODAY));
        self::assertFalse(S::reactivationNeedsNewDate(null, self::TODAY));
        self::assertFalse(S::reactivationNeedsNewDate('2026-12-01', self::TODAY));
    }

    public function testOnlyActiveIsPublic(): void
    {
        self::assertTrue(S::isPublic(S::ACTIVE, null, self::TODAY));
        foreach ([S::ENDED, S::WITHDRAWN, S::RESUBMITTED] as $status) {
            self::assertFalse(S::isPublic($status, null, self::TODAY));
        }
        self::assertFalse(S::isPublic(S::ACTIVE, '2026-10-01', self::TODAY));
    }

    // --- validace nabídky ---------------------------------------------------------

    private function validate(array $overrides): array
    {
        return OfferValidator::validate(Fixtures::offerForm($overrides), self::CATEGORIES, self::UNITS, self::TODAY);
    }

    public function testValidOfferIsNormalized(): void
    {
        $result = $this->validate(['unit_price' => '1 250,5', 'available_quantity' => '800', 'description' => '  ']);
        self::assertSame([], $result['errors']);
        self::assertSame('1250.50', $result['data']['unit_price']);
        self::assertSame('800.000', $result['data']['available_quantity']);
        self::assertNull($result['data']['description']);
        self::assertNull($result['data']['available_from']);
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function invalidOffers(): iterable
    {
        yield 'cena dohodou' => [['unit_price' => 'dohodou'], 'unit_price'];
        yield 'prázdná cena' => [['unit_price' => ''], 'unit_price'];
        yield 'nulová cena' => [['unit_price' => '0'], 'unit_price'];
        yield 'záporná cena' => [['unit_price' => '-5'], 'unit_price'];
        yield 'tři desetinná místa' => [['unit_price' => '1,555'], 'unit_price'];
        yield 'neznámá jednotka ceny' => [['price_unit_id' => '99'], 'price_unit_id'];
        yield 'vlastní textová jednotka' => [['quantity_unit_id' => 'pytel'], 'quantity_unit_id'];
        yield 'neaktivní kategorie' => [['category_id' => '5'], 'category_id'];
        yield 'nulové množství' => [['available_quantity' => '0'], 'available_quantity'];
        yield 'bez názvu' => [['product_name' => ' '], 'product_name'];
        yield 'bez dopravy' => [['delivery_terms' => ''], 'delivery_terms'];
        yield 'od data bez data' => [['availability_type' => 'from_date', 'available_from' => ''], 'available_from'];
        yield 'neplatné datum' => [['availability_type' => 'from_date', 'available_from' => '2026-02-30'], 'available_from'];
        yield 'neznámá dostupnost' => [['availability_type' => 'brzy'], 'availability_type'];
        yield 'konec v minulosti' => [['available_until' => '2026-10-11'], 'available_until'];
        yield 'konec před začátkem' => [['availability_type' => 'from_date', 'available_from' => '2026-11-10', 'available_until' => '2026-11-01'], 'available_until'];
    }

    /** @dataProvider invalidOffers */
    public function testInvalidOfferIsRejected(array $overrides, string $field): void
    {
        self::assertArrayHasKey($field, $this->validate($overrides)['errors']);
    }

    public function testImmediateAvailabilityDropsFromDate(): void
    {
        $result = $this->validate(['availability_type' => 'immediate', 'available_from' => '2026-11-01']);
        self::assertNull($result['data']['available_from']);
    }

    public function testEditKeepsCategoryThatWasDeactivatedMeanwhile(): void
    {
        $existing = ['category_id' => 5, 'price_unit_id' => 1, 'quantity_unit_id' => 1];
        $result = OfferValidator::validate(Fixtures::offerForm(['category_id' => '5']), self::CATEGORIES, self::UNITS, self::TODAY, $existing);
        self::assertArrayNotHasKey('category_id', $result['errors']);
    }

    public function testCzechFormatting(): void
    {
        self::assertSame("14,50\u{00A0}Kč\u{00A0}/\u{00A0}kg", format_price('14.5', 'kg'));
        self::assertSame('800', format_number('800.000'));
        self::assertSame("1\u{00A0}250,5", format_number('1250.500'));
        self::assertSame('5. 11. 2026', format_date('2026-11-05'));
    }
}
