<?php

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id ?? null, 'name' => $this->name ?? null, 'email' => $this->email ?? null];
    }
}
