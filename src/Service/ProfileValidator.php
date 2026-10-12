<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Validace a normalizace profilu zemědělce / školy (SPECIFIKACE §6, §7).
 * Vrací očištěná data nebo chyby po polích (texty pro uživatele).
 */
final class ProfileValidator
{
    /** pole => [popisek, max. délka, povinné] */
    private const COMMON = [
        'contact_name' => ['Jméno kontaktní osoby', 150, true],
        'address_line' => ['Ulice a číslo popisné', 190, true],
        'municipality' => ['Obec', 120, true],
        'postal_code' => ['PSČ', 6, true],
        'phone' => ['Telefon', 32, true],
        'public_email' => ['Kontaktní e-mail', 190, true],
        'website' => ['Web', 255, false],
    ];

    /**
     * @param array<string, mixed> $input
     * @return array{data: array<string, string|null>, errors: array<string, string>}
     */
    public static function validate(string $role, array $input, bool $requireConsents): array
    {
        $fields = self::COMMON + ($role === 'school'
            ? ['school_name' => ['Název školy nebo jídelny', 190, true], 'organization_id' => ['IČO nebo identifikátor organizace', 20, false]]
            : ['producer_name' => ['Název farmy nebo producenta', 190, true], 'company_id' => ['IČO', 10, false]]);

        $data = [];
        $errors = [];
        foreach ($fields as $name => [$label, $max, $required]) {
            $value = $input[$name] ?? '';
            $value = is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value) ?? '') : '';
            if ($value === '') {
                if ($required) {
                    $errors[$name] = "Vyplňte pole {$label}.";
                }
                $data[$name] = null;
                continue;
            }
            if (mb_strlen($value) > $max && !in_array($name, ['postal_code', 'company_id', 'organization_id'], true)) {
                $errors[$name] = "{$label} může mít nejvýš {$max} znaků.";
            }
            $data[$name] = $value;
        }

        // PSČ: "687 08" i "68708" -> "68708"
        if ($data['postal_code'] !== null) {
            $psc = str_replace(' ', '', $data['postal_code']);
            if (preg_match('/^[1-7]\d{4}$/', $psc)) {
                $data['postal_code'] = $psc;
            } else {
                $errors['postal_code'] = 'PSČ musí mít 5 číslic, např. 687 08.';
            }
        }

        if ($data['phone'] !== null && !preg_match('/^\+?[0-9][0-9 ]{7,19}$/', $data['phone'])) {
            $errors['phone'] = 'Zadejte telefon jen s číslicemi, mezerami a případně předvolbou +420.';
        }

        if ($data['public_email'] !== null) {
            $email = mb_strtolower($data['public_email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['public_email'] = 'Zadejte platnou e-mailovou adresu.';
            }
            $data['public_email'] = $email;
        }

        if ($data['website'] !== null) {
            $url = $data['website'];
            if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
                $url = 'https://' . $url;
            }
            // Jen http(s): zabrání odkazům typu javascript: v profilu.
            if (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL) || !str_contains((string) parse_url($url, PHP_URL_HOST), '.')) {
                $errors['website'] = 'Zadejte webovou adresu, např. www.farma.cz.';
            }
            $data['website'] = $url;
        }

        if ($role === 'producer' && $data['company_id'] !== null) {
            $ico = str_replace(' ', '', $data['company_id']);
            if (!self::isValidIco($ico)) {
                $errors['company_id'] = 'IČO není platné (8 číslic s kontrolní číslicí).';
            }
            $data['company_id'] = str_pad($ico, 8, '0', STR_PAD_LEFT);
        }
        if ($role === 'school' && $data['organization_id'] !== null) {
            $id = str_replace(' ', '', $data['organization_id']);
            if (!preg_match('/^\d{6,16}$/', $id)) {
                $errors['organization_id'] = 'Zadejte IČO nebo identifikátor jen z číslic (6–16).';
            }
            $data['organization_id'] = $id;
        }

        if ($requireConsents) {
            if (($input['privacy_consent'] ?? '') !== '1') {
                $errors['privacy_consent'] = 'Bez souhlasu se zpracováním údajů nelze platformu používat.';
            }
            if (($input['contact_publication_consent'] ?? '') !== '1') {
                $errors['contact_publication_consent'] = 'Kontakty jsou nutné, aby vás ostatní členové mohli oslovit.';
            }
        }

        return ['data' => $data, 'errors' => $errors];
    }

    /** IČO: 8 číslic, kontrolní součet mod 11 (vážené 8…2). Sedmimístné doplní nulou. */
    public static function isValidIco(string $ico): bool
    {
        if (!preg_match('/^\d{7,8}$/', $ico)) {
            return false;
        }
        $ico = str_pad($ico, 8, '0', STR_PAD_LEFT);
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $ico[$i] * (8 - $i);
        }
        $remainder = $sum % 11;
        $check = match ($remainder) {
            0 => 1,
            1 => 0,
            default => 11 - $remainder,
        };
        return (int) $ico[7] === $check;
    }
}
