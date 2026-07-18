<?php

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LoginOtpResendResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'status' => $this->resource['status'] ?? 'sent',
            'channel' => $this->resource['channel'] ?? null,
        ];
    }
}
