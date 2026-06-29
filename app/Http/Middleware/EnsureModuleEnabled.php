<?php

namespace App\Http\Middleware;

use App\Services\Settings\ModuleAvailabilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $state = app(ModuleAvailabilityService::class)->effectiveState($moduleKey);

        if ($state['available']) {
            return $next($request);
        }

        $message = 'This module is currently disabled.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'module_disabled',
                'message' => $message,
            ], 403);
        }

        return redirect()->route('home')->with('error', $message);
    }
}
