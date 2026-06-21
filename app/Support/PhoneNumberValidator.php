<?php

namespace Modules\Authentication\Support;

class PhoneNumberValidator
{
    protected const COUNTRY_RULES = [
        'IN' => [
            'dial_code' => '91',
            'national_length' => 10,
            'pattern' => '/^[6-9]\d{9}$/',
            'strip_leading_zero' => true,
        ],
        'US' => [
            'dial_code' => '1',
            'national_length' => 10,
            'pattern' => '/^[2-9]\d{2}[2-9]\d{6}$/',
            'strip_leading_zero' => false,
        ],
        'CA' => [
            'dial_code' => '1',
            'national_length' => 10,
            'pattern' => '/^[2-9]\d{2}[2-9]\d{6}$/',
            'strip_leading_zero' => false,
        ],
        'GB' => [
            'dial_code' => '44',
            'national_length' => 10,
            'pattern' => '/^[1-9]\d{9}$/',
            'strip_leading_zero' => true,
        ],
    ];

    public static function isValid(?string $value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        $normalized = preg_replace('/[^\d+]/', '', $value) ?? '';
        if ($normalized === '') {
            return false;
        }

        if (str_starts_with($normalized, '00')) {
            $normalized = '+' . substr($normalized, 2);
        }

        if (str_starts_with($normalized, '+')) {
            return self::validateInternational($normalized);
        }

        return self::validateLocal($normalized);
    }

    protected static function validateInternational(string $value): bool
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        foreach (self::allowedCountries() as $country) {
            $rule = self::COUNTRY_RULES[$country] ?? null;
            if (! $rule) {
                continue;
            }

            $dialCode = $rule['dial_code'];
            if (! str_starts_with($digits, $dialCode)) {
                continue;
            }

            $national = substr($digits, strlen($dialCode));

            return self::matchesRule($country, $national);
        }

        return false;
    }

    protected static function validateLocal(string $value): bool
    {
        $country = self::defaultCountry();
        $national = self::normalizeNational($country, preg_replace('/\D/', '', $value) ?? '');

        return self::matchesRule($country, $national);
    }

    protected static function matchesRule(string $country, string $national): bool
    {
        $rule = self::COUNTRY_RULES[$country] ?? null;

        if (! $rule) {
            return false;
        }

        if (strlen($national) !== $rule['national_length']) {
            return false;
        }

        return (bool) preg_match($rule['pattern'], $national);
    }

    protected static function normalizeNational(string $country, string $digits): string
    {
        $rule = self::COUNTRY_RULES[$country] ?? null;

        if (! $rule) {
            return $digits;
        }

        if (($rule['strip_leading_zero'] ?? false) === true) {
            $digits = ltrim($digits, '0');
        }

        return $digits;
    }

    protected static function allowedCountries(): array
    {
        $countries = PhoneInputConfig::onlyCountries();

        if ($countries === []) {
            $countries = PhoneInputConfig::preferredCountries();
        }

        if ($countries === []) {
            $countries = [PhoneInputConfig::defaultCountry()];
        }

        return array_values(array_filter($countries, static fn (string $country): bool => isset(self::COUNTRY_RULES[$country])));
    }

    protected static function defaultCountry(): string
    {
        $country = PhoneInputConfig::defaultCountry();

        return isset(self::COUNTRY_RULES[$country]) ? $country : 'IN';
    }
}
