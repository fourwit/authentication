<?php

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OtpResendResource extends JsonResource
{
    public function toArray($request): array
    {
        return array_filter([
            'status' => $this->resource['status'] ?? null,
            'channel' => $this->resource['channel'] ?? null,
            'destination' => $this->resource['destination'] ?? null,
            'expires_at' => $this->resource['expires_at'] ?? null,
            'resend_allowed_at' => $this->resource['resend_allowed_at'] ?? null,
        ], static fn ($value) => $value !== null);
    }
}
