<?php

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PasswordResetOtpVerifiedResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'status' => 'verified',
            'next_step' => 'set_password',
            'reset_grant' => $this->resource['reset_grant'] ?? null,
            'auth_method' => $this->resource['auth_method'] ?? null,
            'email' => $this->resource['email'] ?? null,
            'phone' => $this->resource['phone'] ?? null,
        ];
    }
}
