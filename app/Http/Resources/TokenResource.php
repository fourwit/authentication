<?php

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TokenResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'token' => $this->resource['token'] ?? null,
            'expires_at' => $this->resource['expires_at'] ?? null,
        ];
    }
}
