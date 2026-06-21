<?php

namespace Modules\Authentication\Support;

class PhoneNumberNormalizer
{
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $e164 = self::toE164($value);

        if ($e164 === null) {
            return null;
        }

        return match (PhoneInputConfig::storeFormat()) {
            'e164' => $e164,
            'international' => self::toInternational($e164),
            'national' => self::toNational($e164),
        };
    }

    public static function isValid(?string $value): bool
    {
        return self::toE164($value) !== null;
    }

    public static function toE164(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $hasPlus = str_starts_with($value, '+');
        $normalized = preg_replace('/[^\d+]/', '', $value) ?? '';

        if (str_starts_with($normalized, '00')) {
            $normalized = '+' . substr($normalized, 2);
            $hasPlus = true;
        }

        $digits = preg_replace('/\D/', '', $normalized) ?? '';

        if ($digits === '' || strlen($digits) < 6 || strlen($digits) > 15) {
            return null;
        }

        if ($hasPlus) {
            return '+' . $digits;
        }

        $national = ltrim($digits, '0');

        if ($national === '') {
            return null;
        }

        return '+' . PhoneInputConfig::dialCodeFor() . $national;
    }

    public static function toNational(?string $value): ?string
    {
        $e164 = self::toE164($value);

        if ($e164 === null) {
            return null;
        }

        $digits = ltrim($e164, '+');
        $dialCode = self::detectDialCode($digits);

        if ($dialCode !== null && str_starts_with($digits, $dialCode)) {
            return substr($digits, strlen($dialCode));
        }

        return $digits;
    }

    public static function toInternational(?string $value): ?string
    {
        $e164 = self::toE164($value);

        if ($e164 === null) {
            return null;
        }

        $digits = ltrim($e164, '+');
        $dialCode = self::detectDialCode($digits);

        if ($dialCode === null) {
            return $e164;
        }

        $national = substr($digits, strlen($dialCode));

        return '+' . $dialCode . ' ' . $national;
    }

    protected static function detectDialCode(string $digits): ?string
    {
        $codes = array_unique(array_values(PhoneInputConfig::COUNTRY_DIAL_CODES));
        usort($codes, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        foreach ($codes as $dialCode) {
            if (str_starts_with($digits, $dialCode)) {
                return $dialCode;
            }
        }

        return null;
    }
}
