<?php

declare(strict_types=1);

namespace Tests\Support;

/** Sdílená testovací data (testovací třídy se navzájem nevolají; PHPUnit 10 to nemá rád). */
final class Fixtures
{
    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    public static function producerProfile(array $overrides = []): array
    {
        return $overrides + [
            'producer_name' => 'Farma Pod Barborkou',
            'contact_name' => 'Jan Novák',
            'address_line' => 'Náves 12',
            'municipality' => 'Buchlovice',
            'postal_code' => '687 08',
            'phone' => '+420 600 123 456',
            'public_email' => 'Farma@Example.cz',
            'website' => '',
            'company_id' => '',
            'privacy_consent' => '1',
            'contact_publication_consent' => '1',
        ];
    }

    /**
     * Formulář nabídky. ID kategorie/jednotek odpovídají výchozím číselníkům
     * (1 = Zelenina, 1 = kg, 6 = ks) z migrace 001.
     *
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    public static function offerForm(array $overrides = []): array
    {
        return $overrides + [
            'category_id' => '1',
            'product_name' => 'Brambory Adéla',
            'description' => 'Konzumní, praná.',
            'unit_price' => '14,50',
            'price_unit_id' => '1',
            'available_quantity' => '800',
            'quantity_unit_id' => '1',
            'availability_type' => 'immediate',
            'available_from' => '',
            'available_until' => '',
            'delivery_terms' => 'Rozvoz po Buchlovicích zdarma.',
        ];
    }

    /**
     * Formulář poptávky; období začíná dnes a trvá měsíc (relativně, aby test nezastaral).
     * ID: 2 = Ovoce, 1 = kg.
     *
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    public static function demandForm(array $overrides = []): array
    {
        return $overrides + [
            'category_id' => '2',
            'product_name' => 'Jablka',
            'requested_quantity' => '120',
            'quantity_unit_id' => '1',
            'needed_from' => date('Y-m-d'),
            'needed_until' => date('Y-m-d', strtotime('+30 days')),
            'note' => 'Dodávky jednou týdně.',
        ];
    }
}
