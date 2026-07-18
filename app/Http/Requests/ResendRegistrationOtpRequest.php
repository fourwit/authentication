<?php

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Authentication\Http\Requests\Concerns\ValidatesOtpCredentials;

class ResendRegistrationOtpRequest extends FormRequest
{
    use ValidatesOtpCredentials;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->otpCredentialRules(requireCode: false, validatePhoneFormat: true);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareOtpPhoneForValidation(normalizePhone: true);
    }
}
