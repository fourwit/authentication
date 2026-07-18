<?php

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyVerificationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:' . (int) config('authentication.otp.length', 6)],
            'channel' => ['sometimes', 'string', 'in:email,phone'],
        ];
    }
}
