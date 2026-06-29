<?php

namespace App\Http\Middleware;

use App\Services\Setup\SetupStatusService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFirstRunSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $setup = app(SetupStatusService::class);

        if ($setup->requiresFirstRunSetup() && !$request->routeIs('setup.*')) {
            return redirect()->route('setup.index');
        }

        return $next($request);
    }
}
