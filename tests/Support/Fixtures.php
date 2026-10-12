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
}
