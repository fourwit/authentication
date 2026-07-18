<?php

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResponseResource extends JsonResource
{
    public function toArray($request): array
    {
        $payload = [
            'user' => new AuthenticatedUserResource($this->resource['user'] ?? null),
        ];

        if (! empty($this->resource['registration_grant'])) {
            $payload['registration_grant'] = $this->resource['registration_grant'];
        }

        return $payload;
    }

    public function withResponse($request, $response): void
    {
        $response->setStatusCode(201);
    }
}
