<?php

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Authentication\Support\PasswordPolicy;
use Modules\Authentication\Support\PasswordResetMethodResolver;
use Modules\Authentication\Support\PhoneInputConfig;
use Modules\Authentication\Support\PhoneNumberNormalizer;
use Modules\Authentication\Support\PhoneNumberValidator;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $method = $this->resolvedMethod();
        $phoneRules = ['nullable', 'string', 'max:30'];
        $phoneRules[] = static fn (string $attribute, mixed $value, \Closure $fail): mixed => $value === null || $value === ''
            ? null
            : (PhoneNumberValidator::isValid((string) $value) ? null : $fail('Please enter a valid phone number.'));

        return [
            'auth_method' => ['nullable', 'string'],
            'token' => $method === 'link' ? ['required', 'string'] : ['nullable', 'string'],
            'reset_grant' => $method === 'link' ? ['nullable', 'string'] : ['required', 'string'],
            'email' => in_array($method, ['link', 'email_otp'], true) ? ['required', 'email'] : ['nullable', 'email'],
            'phone' => $method === 'phone_otp'
                ? (PhoneInputConfig::supportsPhoneFields() ? array_merge(['required'], $phoneRules) : ['required', 'string'])
                : ['nullable'],
            'password' => PasswordPolicy::rules(),
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Please enter a valid email address.',
            'password.min' => 'The new password must be at least :min characters.',
            'password.mixed' => 'The new password must include both uppercase and lowercase letters.',
            'password.letters' => 'The new password must include at least one letter.',
            'password.numbers' => 'The new password must include at least one number.',
            'password.symbols' => 'The new password must include at least one symbol.',
            'password.uncompromised' => 'This password has appeared in a public data breach. Please choose a different password.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'phone' => 'phone number',
            'password' => 'new password',
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        $normalizedPhone = is_string($phone) ? PhoneNumberNormalizer::normalize($phone) : null;

        $this->merge([
            'auth_method' => $this->resolvedMethod(),
            'phone' => PhoneInputConfig::supportsPhoneFields()
                ? ($normalizedPhone ?? $phone)
                : ($phone ?: null),
        ]);
    }

    protected function resolvedMethod(): string
    {
        $requested = $this->input('auth_method', $this->query('auth_method'));

        return PasswordResetMethodResolver::resolve(is_string($requested) ? $requested : null);
    }
}
