<?php

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Authentication\Support\PhoneInputConfig;
use Modules\Authentication\Support\PhoneNumberNormalizer;
use Modules\Authentication\Support\PhoneNumberValidator;
use Modules\Authentication\Support\VerificationConfig;

class VerifyEmailRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        // Support legacy email link verification and new code-based verification
        $rules = [
            'email' => ['sometimes', 'email'],
            'phone' => PhoneInputConfig::supportsPhoneFields() ? [
                'sometimes',
                'string',
                'max:30',
                static fn (string $attribute, mixed $value, \Closure $fail): mixed => $value === null || $value === ''
                    ? null
                    : (PhoneNumberValidator::isValid((string) $value) ? null : $fail('Please enter a valid phone number.')),
            ] : ['prohibited'],
            'id' => ['sometimes'],
            'hash' => ['sometimes'],
            'token' => ['sometimes'],
            'channel' => ['sometimes', 'string', VerificationConfig::channel() === 'phone' ? 'in:phone' : 'in:email'],
            'code' => ['sometimes', 'string', 'size:6'],
        ];

        // If using legacy path require email
        if (! $this->has('code') && ! $this->has('channel')) {
            $rules['email'] = ['required', 'email'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if (! PhoneInputConfig::supportsPhoneFields() || ! $this->exists('phone')) {
            return;
        }

        $phone = $this->input('phone');
        $normalizedPhone = is_string($phone) ? PhoneNumberNormalizer::normalize($phone) : null;

        $this->merge([
            'phone' => $normalizedPhone ?? $phone,
        ]);
    }
}
