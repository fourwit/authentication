<?php

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Authentication\Support\PhoneInputConfig;
use Modules\Authentication\Support\PhoneNumberNormalizer;
use Modules\Authentication\Support\PhoneNumberValidator;

class RegistrationOtpVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'auth_method' => ['required', 'string', 'in:email_otp,phone_otp'],
            'email' => ['nullable', 'email', 'required_if:auth_method,email_otp'],
            'phone' => PhoneInputConfig::supportsPhoneFields()
                ? [
                    'nullable',
                    'string',
                    'max:30',
                    'required_if:auth_method,phone_otp',
                    static fn (string $attribute, mixed $value, \Closure $fail): mixed => $value === null || $value === ''
                        ? null
                        : (PhoneNumberValidator::isValid((string) $value) ? null : $fail('Please enter a valid phone number.')),
                ]
                : ['prohibited'],
            'code' => ['required', 'string', 'size:' . (int) config('authentication.otp.length', 6)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        $normalizedPhone = is_string($phone) ? PhoneNumberNormalizer::normalize($phone) : null;

        $this->merge([
            'phone' => PhoneInputConfig::supportsPhoneFields()
                ? ($normalizedPhone ?? $phone)
                : null,
        ]);
    }
}
