<?php

namespace Modules\Authentication\Support;

use InvalidArgumentException;

class PhoneInputConfig
{
    public const ALLOWED_STORE_FORMATS = ['e164', 'international', 'national'];

    public const COUNTRY_DIAL_CODES = [
        'IN' => '91',
        'US' => '1',
        'GB' => '44',
        'CA' => '1',
    ];

    public static function enabled(): bool
    {
        return (bool) config('authentication.phone_input.enabled', true);
    }

    public static function storeFormat(): string
    {
        $format = (string) config('authentication.phone_input.store_format', 'e164');

        if (! in_array($format, self::ALLOWED_STORE_FORMATS, true)) {
            throw new InvalidArgumentException('Invalid authentication.phone_input.store_format value.');
        }

        return $format;
    }

    public static function defaultCountry(): string
    {
        $country = strtoupper((string) config('authentication.phone_input.default_country', 'IN'));

        return array_key_exists($country, self::COUNTRY_DIAL_CODES) ? $country : 'IN';
    }

    public static function dialCodeFor(?string $country = null): string
    {
        return self::COUNTRY_DIAL_CODES[$country ?: self::defaultCountry()] ?? self::COUNTRY_DIAL_CODES['IN'];
    }

    public static function preferredCountries(): array
    {
        return array_values(array_filter(array_map('strtoupper', (array) config('authentication.phone_input.preferred_countries', []))));
    }

    public static function onlyCountries(): array
    {
        return array_values(array_filter(array_map('strtoupper', (array) config('authentication.phone_input.only_countries', []))));
    }

    public static function viewConfig(): array
    {
        $library = (string) config('authentication.phone_input.library', 'intl-tel-input');

        return [
            'enabled' => self::enabled(),
            'default_country' => self::defaultCountry(),
            'preferred_countries' => self::preferredCountries(),
            'only_countries' => self::onlyCountries(),
            'library' => in_array($library, ['intl-tel-input', 'none', 'custom'], true) ? $library : 'intl-tel-input',
            'cdn' => (bool) config('authentication.phone_input.cdn', true),
            'version' => (string) config('authentication.phone_input.version', '24.0.0'),
            'separate_dial_code' => (bool) config('authentication.phone_input.separate_dial_code', true),
            'store_format' => self::storeFormat(),
        ];
    }

    public static function supportsPhoneFields(): bool
    {
        return true;
    }

    public static function supportsPhoneMethod(string $method): bool
    {
        return true;
    }
}
