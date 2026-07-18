<?php

namespace Modules\Authentication\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Authentication\Facades\Authentication;
use Modules\Authentication\Http\Requests\RegisterRequest;
use Modules\Authentication\Http\Resources\AuthenticatedUserResource;

class RegisterController extends Controller
{
    public function store(RegisterRequest $request)
    {
        $result = Authentication::register($request->validated(), 'api');

        $payload = ['user' => new AuthenticatedUserResource($result['user'])];

        if (! empty($result['registration_grant'])) {
            $payload['registration_grant'] = $result['registration_grant'];
        }

        return response()->json($payload, 201);
    }
}
