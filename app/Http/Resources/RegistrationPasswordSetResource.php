<?php

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationPasswordSetResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'status' => $this->resource['status'] ?? null,
            'next_step' => $this->resource['next_step'] ?? null,
            'user' => new AuthenticatedUserResource($this->resource['user'] ?? null),
        ];
    }
}
