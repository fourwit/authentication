<?php

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Authentication\Support\PasswordResetMethodResolver;
use Modules\Authentication\Support\PhoneInputConfig;
use Modules\Authentication\Support\PhoneNumberNormalizer;
use Modules\Authentication\Support\PhoneNumberValidator;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $method = $this->resolvedMethod();
        $fieldDefinitions = PasswordResetMethodResolver::fields($method);
        $required = PasswordResetMethodResolver::requiredFields($method);
        $phoneRules = ['nullable', 'string', 'max:30'];

        $phoneRules[] = static fn (string $attribute, mixed $value, \Closure $fail): mixed => $value === null || $value === ''
            ? null
            : (PhoneNumberValidator::isValid((string) $value) ? null : $fail('Please enter a valid phone number.'));

        $rules = [
            'auth_method' => ['nullable', 'string'],
        ];

        foreach (array_keys($fieldDefinitions) as $field) {
            $isRequired = in_array($field, $required, true);
            $presence = $isRequired ? ['required'] : ['nullable'];

            $rules[$field] = match ($field) {
                'email' => [...$presence, 'email'],
                'phone' => PhoneInputConfig::supportsPhoneFields()
                    ? array_merge($presence, $phoneRules)
                    : ['nullable'],
                default => [...$presence, 'string'],
            };
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.required_without' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'phone.required_without' => 'Please enter your phone number.',
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'phone' => 'phone number',
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
                : null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('authentication.password.request');
    }

    protected function resolvedMethod(): string
    {
        $requested = $this->input('auth_method', $this->query('auth_method'));

        return PasswordResetMethodResolver::resolve(is_string($requested) ? $requested : null);
    }
}
