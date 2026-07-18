<?php

namespace Modules\Authentication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Authentication\Support\PasswordPolicy;

class SetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'password' => PasswordPolicy::rules(),
        ];

        if (! auth()->check()) {
            $rules['registration_grant'] = ['required', 'string', 'uuid'];
            $rules['user_id'] = ['prohibited'];
        }

        return $rules;
    }
}
