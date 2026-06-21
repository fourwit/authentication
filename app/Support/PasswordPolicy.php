<?php

namespace Modules\Authentication\Support;

use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordPolicy
{
    public static function config(): array
    {
        $config = config('authentication.password_policy', []);
        $strength = is_array($config['strength_meter'] ?? null) ? $config['strength_meter'] : [];

        return [
            'enabled' => (bool) ($config['enabled'] ?? true),
            'min_length' => max(1, (int) ($config['min_length'] ?? 8)),
            'require_mixed_case' => (bool) ($config['require_mixed_case'] ?? true),
            'require_numbers' => (bool) ($config['require_numbers'] ?? true),
            'require_symbols' => (bool) ($config['require_symbols'] ?? true),
            'uncompromised' => (bool) ($config['uncompromised'] ?? true),
            'strength_meter' => [
                'enabled' => (bool) ($strength['enabled'] ?? true),
                'show_hints' => (bool) ($strength['show_hints'] ?? true),
                'min_score' => max(0, min(4, (int) ($strength['min_score'] ?? 3))),
            ],
        ];
    }

    public static function rules(bool $required = true, bool $confirmed = true): array
    {
        $config = self::config();
        $rules = [$required ? 'required' : 'nullable', 'string'];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        if (! $config['enabled']) {
            $rules[] = 'min:8';

            return $rules;
        }

        $passwordRule = PasswordRule::min($config['min_length']);

        if ($config['require_mixed_case']) {
            $passwordRule = $passwordRule->mixedCase();
        }

        if ($config['require_numbers']) {
            $passwordRule = $passwordRule->numbers();
        }

        if ($config['require_symbols']) {
            $passwordRule = $passwordRule->symbols();
        }

        if ($config['uncompromised']) {
            $passwordRule = $passwordRule->uncompromised();
        }

        $rules[] = $passwordRule;

        return $rules;
    }

    public static function frontendConfig(): array
    {
        $config = self::config();

        return [
            'enabled' => $config['enabled'],
            'min_length' => $config['min_length'],
            'require_mixed_case' => $config['require_mixed_case'],
            'require_numbers' => $config['require_numbers'],
            'require_symbols' => $config['require_symbols'],
            'strength_meter' => $config['strength_meter'],
        ];
    }

    public static function strengthMeterEnabled(): bool
    {
        $config = self::config();

        return $config['enabled'] && (bool) data_get($config, 'strength_meter.enabled', false);
    }
}
