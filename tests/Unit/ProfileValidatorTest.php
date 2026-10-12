<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\ProfileValidator;
use PHPUnit\Framework\TestCase;

final class ProfileValidatorTest extends TestCase
{
    /** @return array<string, string> */
    public static function validProducer(array $overrides = []): array
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

    public function testValidProducerIsNormalized(): void
    {
        $result = ProfileValidator::validate('producer', self::validProducer(['website' => 'www.farma.cz', 'company_id' => '00006947']), true);
        self::assertSame([], $result['errors']);
        self::assertSame('68708', $result['data']['postal_code']);
        self::assertSame('farma@example.cz', $result['data']['public_email']);
        self::assertSame('https://www.farma.cz', $result['data']['website']);
        self::assertSame('00006947', $result['data']['company_id']);
        self::assertNull(ProfileValidator::validate('producer', self::validProducer(), true)['data']['website']);
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function invalidInputs(): iterable
    {
        yield 'chybí název' => [['producer_name' => '  '], 'producer_name'];
        yield 'chybí telefon' => [['phone' => ''], 'phone'];
        yield 'PSČ krátké' => [['postal_code' => '6870'], 'postal_code'];
        yield 'PSČ s písmeny' => [['postal_code' => '687OB'], 'postal_code'];
        yield 'telefon s písmeny' => [['phone' => '600 abc 456'], 'phone'];
        yield 'neplatný e-mail' => [['public_email' => 'farma@'], 'public_email'];
        yield 'javascript odkaz' => [['website' => 'javascript:alert(1)'], 'website'];
        yield 'ftp odkaz' => [['website' => 'ftp://farma.cz'], 'website'];
        yield 'web bez domény' => [['website' => 'localhost'], 'website'];
        yield 'IČO špatná kontrolní číslice' => [['company_id' => '00006948'], 'company_id'];
        yield 'IČO krátké' => [['company_id' => '12345'], 'company_id'];
        yield 'bez souhlasu GDPR' => [['privacy_consent' => ''], 'privacy_consent'];
        yield 'bez souhlasu s kontakty' => [['contact_publication_consent' => '0'], 'contact_publication_consent'];
        yield 'příliš dlouhý název' => [['producer_name' => str_repeat('a', 191)], 'producer_name'];
    }

    /** @dataProvider invalidInputs */
    public function testInvalidInputProducesFieldError(array $overrides, string $field): void
    {
        $errors = ProfileValidator::validate('producer', self::validProducer($overrides), true)['errors'];
        self::assertArrayHasKey($field, $errors);
    }

    public function testConsentsAreNotRequiredWhenEditing(): void
    {
        $input = self::validProducer(['privacy_consent' => '', 'contact_publication_consent' => '']);
        self::assertSame([], ProfileValidator::validate('producer', $input, false)['errors']);
    }

    public function testSchoolHasOwnFields(): void
    {
        $input = [
            'school_name' => 'ZŠ Buchlovice', 'contact_name' => 'Marie Malá', 'address_line' => 'Školní 1',
            'municipality' => 'Buchlovice', 'postal_code' => '68708', 'phone' => '572 000 000',
            'public_email' => 'jidelna@zs.cz', 'organization_id' => '600 123 456',
            'privacy_consent' => '1', 'contact_publication_consent' => '1',
        ];
        $result = ProfileValidator::validate('school', $input, true);
        self::assertSame([], $result['errors']);
        self::assertSame('600123456', $result['data']['organization_id']);
        self::assertArrayNotHasKey('producer_name', $result['data']);

        $missing = ProfileValidator::validate('school', ['school_name' => ''] + $input, true)['errors'];
        self::assertArrayHasKey('school_name', $missing);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function icos(): iterable
    {
        yield 'Ministerstvo financí' => ['00006947', true];
        yield 'sedmimístné (doplní se nula)' => ['0006947', true];
        yield 'příliš krátké' => ['6947', false];
        yield 'zbytek 0 -> 1' => ['12345671', false];
        yield 'nuly' => ['00000000', false];
        yield 'písmena' => ['0000694a', false];
    }

    /** @dataProvider icos */
    public function testIcoChecksum(string $ico, bool $valid): void
    {
        self::assertSame($valid, ProfileValidator::isValidIco($ico));
    }
}
