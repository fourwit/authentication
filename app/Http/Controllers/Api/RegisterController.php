<?php

namespace Modules\Authentication\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Authentication\Facades\Authentication;
use Modules\Authentication\Http\Requests\RegisterRequest;
use Modules\Authentication\Http\Resources\RegistrationResponseResource;

class RegisterController extends Controller
{
    public function store(RegisterRequest $request)
    {
        $result = Authentication::register($request->validated(), 'api');

        return new RegistrationResponseResource($result);
    }
}
