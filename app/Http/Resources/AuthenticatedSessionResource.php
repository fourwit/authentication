<?php

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'user' => new AuthenticatedUserResource($this->resource['user'] ?? null),
            'token' => new TokenResource($this->resource),
        ];
    }
}
