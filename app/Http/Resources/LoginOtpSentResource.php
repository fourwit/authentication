<?php

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LoginOtpSentResource extends JsonResource
{
    public function toArray($request): array
    {
        $status = ($this->resource['status'] ?? null) === 'rate_limited' ? 'rate_limited' : 'otp_sent';

        return [
            'status' => $status,
            'channel' => $this->resource['channel'] ?? null,
            'destination' => $this->resource['destination'] ?? null,
            'expires_at' => $this->resource['expires_at'] ?? null,
            'message' => $status === 'rate_limited'
                ? 'Too many login code requests were made for this account. Please wait before trying again.'
                : null,
        ];
    }

    public function withResponse($request, $response): void
    {
        $status = ($this->resource['status'] ?? null) === 'rate_limited' ? 429 : 202;
        $response->setStatusCode($status);
    }
}
