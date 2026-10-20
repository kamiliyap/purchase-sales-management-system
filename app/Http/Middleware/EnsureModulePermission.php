<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnsureModulePermission
{
    public function handle(Request $request, Closure $next)
    {
        Gate::authorize('access-route', $request->route()->getName());

        return $next($request);
    }
}
