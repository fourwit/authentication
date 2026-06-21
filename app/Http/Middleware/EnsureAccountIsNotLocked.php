<?php

namespace Modules\Authentication\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsNotLocked
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
