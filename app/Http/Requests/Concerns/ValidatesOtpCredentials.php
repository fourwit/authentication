<?php

namespace Modules\Authentication\Http\Requests\Concerns;

use Modules\Authentication\Support\PhoneInputConfig;
use Modules\Authentication\Support\PhoneNumberNormalizer;
use Modules\Authentication\Support\PhoneNumberValidator;

trait ValidatesOtpCredentials
{
    protected function otpCredentialRules(bool $requireCode = true, bool $validatePhoneFormat = false): array
    {
        $phoneRules = ['nullable', 'string', 'max:30', 'required_if:auth_method,phone_otp'];

        if ($validatePhoneFormat) {
            $phoneRules[] = static fn (string $attribute, mixed $value, \Closure $fail): mixed => $value === null || $value === ''
                ? null
                : (PhoneNumberValidator::isValid((string) $value) ? null : $fail('Please enter a valid phone number.'));
        }

        $rules = [
            'auth_method' => ['required', 'string', 'in:email_otp,phone_otp'],
            'email' => ['nullable', 'email', 'required_if:auth_method,email_otp'],
            'phone' => PhoneInputConfig::supportsPhoneFields()
                ? $phoneRules
                : ['prohibited'],
        ];

        if ($requireCode) {
            $rules['code'] = ['required', 'string', 'size:' . (int) config('authentication.otp.length', 6)];
        }

        return $rules;
    }

    protected function prepareOtpPhoneForValidation(bool $normalizePhone = false): void
    {
        if (! PhoneInputConfig::supportsPhoneFields() || ! $this->exists('phone')) {
            return;
        }

        if (! $normalizePhone) {
            return;
        }

        $phone = $this->input('phone');
        $normalizedPhone = is_string($phone) ? PhoneNumberNormalizer::normalize($phone) : null;

        $this->merge([
            'phone' => $normalizedPhone ?? $phone,
        ]);
    }
}
