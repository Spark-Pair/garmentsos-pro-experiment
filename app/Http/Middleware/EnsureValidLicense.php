<?php

namespace App\Http\Middleware;

use App\Services\Licensing\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        $licenseService = app(LicenseService::class);

        if (!$licenseService->enabled()) {
            return $next($request);
        }

        $status = $licenseService->currentStatus();
        $request->attributes->set('license_status', $status);

        if ($status->shouldReadOnly()) {
            session(['readonly' => true]);
            session()->flash('warning', $status->message);
        }

        if ($status->shouldBlock()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'license_blocked',
                    'message' => $status->message,
                ], 403);
            }

            return response($status->message, 403);
        }

        return $next($request);
    }
}
