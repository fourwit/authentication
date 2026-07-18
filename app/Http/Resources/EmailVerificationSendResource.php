<?php

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmailVerificationSendResource extends JsonResource
{
    public function toArray($request): array
    {
        $payload = [
            'status' => $this->resource['status'] ?? null,
        ];

        foreach (['channel', 'destination', 'expires_at', 'resend_allowed_at', 'reason'] as $field) {
            if (array_key_exists($field, $this->resource) && $this->resource[$field] !== null) {
                $payload[$field] = $this->resource[$field];
            }
        }

        if (isset($this->resource['user'])) {
            $payload['user'] = new AuthenticatedUserResource($this->resource['user']);
        }

        return $payload;
    }
}
