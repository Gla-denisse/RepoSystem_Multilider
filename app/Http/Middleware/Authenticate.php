<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // API routes always return 401 JSON, never redirect
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }
        return null; // no login route in this API-only backend
    }
}
