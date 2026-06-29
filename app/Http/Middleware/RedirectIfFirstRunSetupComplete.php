<?php

namespace App\Http\Middleware;

use App\Services\Setup\SetupStatusService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfFirstRunSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $setup = app(SetupStatusService::class);

        if (!$setup->isForceEnabled() && $setup->canCheck() && $setup->isComplete()) {
            return auth()->check()
                ? redirect()->route('home')
                : redirect()->route('login');
        }

        session()->forget(['readonly', 'license_readonly']);

        return $next($request);
    }
}
