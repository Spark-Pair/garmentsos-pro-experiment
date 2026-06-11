<?php

namespace App\Http\Middleware;

use App\Support\FeatureManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function __construct(private readonly FeatureManager $features)
    {
    }

    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        if ($feature !== null && $feature !== '' && $this->features->enabled($feature)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => 'Not Found'], Response::HTTP_NOT_FOUND);
        }

        abort(Response::HTTP_NOT_FOUND);
    }
}
