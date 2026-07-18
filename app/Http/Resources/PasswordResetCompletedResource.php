<?php

namespace Modules\Authentication\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PasswordResetCompletedResource extends JsonResource
{
    public function toArray($request): array
    {
        $payload = [
            'status' => $this->resource['status'] ?? null,
        ];

        if (isset($this->resource['user'])) {
            $payload['user'] = new AuthenticatedUserResource($this->resource['user']);
        }

        if (array_key_exists('auto_login', $this->resource)) {
            $payload['auto_login'] = (bool) $this->resource['auto_login'];
        }

        if (isset($this->resource['token'])) {
            $token = $this->resource['token'];
            $payload['token'] = is_array($token)
                ? new TokenResource($token)
                : ['token' => $token, 'expires_at' => null];
        }

        return $payload;
    }
}
