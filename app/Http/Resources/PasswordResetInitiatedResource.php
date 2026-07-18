<?php

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PasswordResetInitiatedResource extends JsonResource
{
    public function toArray($request): array
    {
        if (($this->resource['flow'] ?? null) === 'link') {
            return [
                'status' => 'passwords.sent',
                'message' => 'If an account with that email exists, a password reset link has been sent.',
            ];
        }

        $status = ($this->resource['status'] ?? null) === 'rate_limited' ? 'rate_limited' : 'otp_sent';

        return [
            'status' => $status,
            'auth_method' => $this->resource['auth_method'] ?? null,
            'channel' => $this->resource['channel'] ?? null,
            'destination' => $this->resource['destination'] ?? null,
            'message' => $status === 'rate_limited'
                ? 'Too many password reset code requests were made for this account. Please wait before trying again.'
                : 'If an account matches that identifier, a recovery code has been sent.',
        ];
    }

    public function withResponse($request, $response): void
    {
        if (($this->resource['flow'] ?? null) === 'link') {
            $response->setStatusCode(200);

            return;
        }

        $status = ($this->resource['status'] ?? null) === 'rate_limited' ? 429 : 202;
        $response->setStatusCode($status);
    }
}
