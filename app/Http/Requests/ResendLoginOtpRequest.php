<?php

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Authentication\Http\Requests\Concerns\ValidatesOtpCredentials;

class ResendLoginOtpRequest extends FormRequest
{
    use ValidatesOtpCredentials;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->otpCredentialRules(requireCode: false);
    }
}
