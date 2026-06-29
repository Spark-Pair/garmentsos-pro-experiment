<?php

namespace App\Http\Middleware;

use Closure;

class SubscriptionExpiry
{
    public function handle($request, Closure $next)
    {
        session()->forget(['readonly', 'expiry_warning_shown']);

        return $next($request);
    }
}
