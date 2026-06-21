<?php

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Authentication\Support\PasswordPolicy;
use Modules\Authentication\Support\PhoneInputConfig;
use Modules\Authentication\Support\PhoneNumberNormalizer;
use Modules\Authentication\Support\PhoneNumberValidator;
use Modules\Authentication\Support\RegistrationMethodResolver;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $method = $this->resolvedMethod();
        $fieldDefinitions = RegistrationMethodResolver::fields($method);
        $required = RegistrationMethodResolver::requiredFields($method);

        $rules = [
            'auth_method' => ['nullable', 'string'],
        ];

        foreach (array_keys($fieldDefinitions) as $field) {
            $rules[$field] = $this->rulesForField($field, in_array($field, $required, true), $method);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'phone.required' => 'Please enter your phone number.',
            'password.required' => 'Please enter your password.',
            'password.min' => 'The password must be at least :min characters.',
            'password.mixed' => 'The password must include both uppercase and lowercase letters.',
            'password.letters' => 'The password must include at least one letter.',
            'password.numbers' => 'The password must include at least one number.',
            'password.symbols' => 'The password must include at least one symbol.',
            'password.uncompromised' => 'This password has appeared in a public data breach. Please choose a different password.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'name',
            'email' => 'email address',
            'phone' => 'phone number',
            'username' => 'username',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'password' => 'password',
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

    protected function resolvedMethod(): string
    {
        $requested = $this->input('auth_method', $this->query('auth_method'));
        return RegistrationMethodResolver::resolve(is_string($requested) ? $requested : null);
    }

    protected function rulesForField(string $field, bool $required, string $method): array
    {
        $presence = $required ? ['required'] : ['nullable'];

        return match ($field) {
            'email' => [...$presence, 'email'],
            'password' => $field === 'password'
                ? PasswordPolicy::rules($required && $method === 'email_password')
                : [...$presence, 'string'],
            'phone' => array_values(array_filter([
                ...$presence,
                'string',
                'max:30',
                static fn (string $attribute, mixed $value, \Closure $fail): mixed => $value === null || $value === ''
                    ? null
                    : (PhoneNumberValidator::isValid((string) $value) ? null : $fail('Please enter a valid phone number.')),
            ])),
            'username' => [...$presence, 'string', 'max:255'],
            'first_name', 'last_name', 'name' => [...$presence, 'string', 'max:255'],
            default => [...$presence, 'string'],
        };
    }
}
