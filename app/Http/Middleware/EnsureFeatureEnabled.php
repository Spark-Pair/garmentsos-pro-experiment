<?php

namespace App\Http\Middleware;

use App\Services\Settings\FeatureAvailabilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $state = app(FeatureAvailabilityService::class)->effectiveState($featureKey);

        if ($state['available']) {
            return $next($request);
        }

        $message = $state['reason'] === 'disabled_by_license'
            ? 'This feature is not included in the active license.'
            : 'This feature is currently disabled.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'feature_disabled',
                'message' => $message,
            ], 403);
        }

        return redirect()->route('home')->with('error', $message);
    }
}
