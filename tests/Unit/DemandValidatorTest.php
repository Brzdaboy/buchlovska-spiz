<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\DemandValidator;
use PHPUnit\Framework\TestCase;

final class DemandValidatorTest extends TestCase
{
    private const TODAY = '2026-10-12';

    /** @param array<string, string> $overrides */
    private function validate(array $overrides): array
    {
        return DemandValidator::validate($overrides + [
            'category_id' => '2', 'product_name' => 'Jablka', 'requested_quantity' => '120,5',
            'quantity_unit_id' => '1', 'needed_from' => '2026-11-01', 'needed_until' => '2026-11-30', 'note' => '',
        ], [1 => 'Zelenina', 2 => 'Ovoce'], [1 => 'kg', 6 => 'ks'], self::TODAY);
    }

    public function testValidDemand(): void
    {
        $result = $this->validate([]);
        self::assertSame([], $result['errors']);
        self::assertSame('120.500', $result['data']['requested_quantity']);
        self::assertNull($result['data']['note']);
    }

    public function testDemandHasNoPriceFields(): void
    {
        $result = $this->validate(['unit_price' => '99', 'budget' => '1000']);
        self::assertArrayNotHasKey('unit_price', $result['data']);
        self::assertArrayNotHasKey('budget', $result['data']);
    }

    public function testPeriodMayHaveStartedAlready(): void
    {
        self::assertSame([], $this->validate(['needed_from' => '2026-10-01'])['errors']);
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function invalid(): iterable
    {
        yield 'bez kategorie' => [['category_id' => ''], 'category_id'];
        yield 'neaktivní kategorie' => [['category_id' => '7'], 'category_id'];
        yield 'bez názvu' => [['product_name' => ''], 'product_name'];
        yield 'nulové množství' => [['requested_quantity' => '0'], 'requested_quantity'];
        yield 'text místo množství' => [['requested_quantity' => 'hodně'], 'requested_quantity'];
        yield 'vlastní jednotka' => [['quantity_unit_id' => 'bedna'], 'quantity_unit_id'];
        yield 'bez začátku' => [['needed_from' => ''], 'needed_from'];
        yield 'bez konce' => [['needed_until' => ''], 'needed_until'];
        yield 'konec v minulosti' => [['needed_from' => '2026-09-01', 'needed_until' => '2026-10-11'], 'needed_until'];
        yield 'konec před začátkem' => [['needed_until' => '2026-10-20'], 'needed_until'];
        yield 'neplatné datum' => [['needed_until' => '2026-11-31'], 'needed_until'];
    }

    /** @dataProvider invalid */
    public function testInvalidDemand(array $overrides, string $field): void
    {
        self::assertArrayHasKey($field, $this->validate($overrides)['errors']);
    }
}
