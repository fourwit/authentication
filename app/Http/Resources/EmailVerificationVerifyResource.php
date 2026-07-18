<?php

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmailVerificationVerifyResource extends JsonResource
{
    public function toArray($request): array
    {
        $payload = [
            'status' => $this->resource['status'] ?? null,
        ];

        if (array_key_exists('next_step', $this->resource) && $this->resource['next_step'] !== null) {
            $payload['next_step'] = $this->resource['next_step'];
        }

        if (isset($this->resource['user'])) {
            $payload['user'] = new AuthenticatedUserResource($this->resource['user']);
        }

        return $payload;
    }
}
