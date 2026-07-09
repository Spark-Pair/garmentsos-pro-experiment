<?php

namespace App\Http\Middleware;

use App\Services\Licensing\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidLicense
{
    protected array $alwaysAllowedRoutePrefixes = [
        'developer.license',
        'developer.updater',
    ];

    protected array $alwaysAllowedRouteNames = [
        'updating',
        'login',
        'loginPost',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $licenseService = app(LicenseService::class);

        if (!$licenseService->enabled() && $licenseService->developmentBypass()) {
            session()->forget(['readonly', 'license_readonly']);
            $request->attributes->set('license_status', $licenseService->currentStatus());

            return $next($request);
        }

        $status = $licenseService->currentStatus();
        $request->attributes->set('license_status', $status);

        if ($this->isAlwaysAllowed($request)) {
            return $next($request);
        }

        if ($status->shouldReadOnly()) {
            session()->forget('readonly');
            session(['license_readonly' => true]);
            session()->flash('warning', $status->message);

            if ($request->routeIs('home')) {
                return redirect()
                    ->route('developer.license.status')
                    ->with('warning', $status->message);
            }
        } else {
            session()->forget(['readonly', 'license_readonly']);
        }

        if ($status->shouldBlock()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'license_blocked',
                    'message' => $status->message,
                ], 403);
            }

            return redirect()
                ->route('developer.license.status')
                ->with('warning', $status->message);
        }

        return $next($request);
    }

    protected function isAlwaysAllowed(Request $request): bool
    {
        $routeName = (string) $request->route()?->getName();
        if ($routeName === '') {
            return false;
        }

        if (in_array($routeName, $this->alwaysAllowedRouteNames, true)) {
            return true;
        }

        foreach ($this->alwaysAllowedRoutePrefixes as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
