<?php

namespace App\Http\Middleware;

use App\Services\Updater\UpdateLockService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockWhenUpdating
{
    protected array $exceptRouteNames = [
        'developer.updater',
        'developer.updater.update-request',
        'developer.updater.update-request.signed',
        'developer.updater.launcher-handoff',
        'developer.updater.launcher-handoff.start',
        'developer.updater.update-lock-status',
        'developer.updater.clear-update-lock',
        'login',
        'loginPost',
        'logout',
    ];

    public function __construct(protected UpdateLockService $locks)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $lock = $this->locks->activeLock();
        if ($lock === null || $request->isMethodSafe() || $this->isExcepted($request)) {
            return $next($request);
        }

        $payload = [
            'message' => $lock['message'] ?? 'GarmentsOS PRO is updating. Please wait until the update is complete.',
            'updating' => true,
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 423);
        }

        return response()
            ->view('updater.updating', ['updateLock' => $lock], 423);
    }

    protected function isExcepted(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return $routeName !== null && in_array($routeName, $this->exceptRouteNames, true);
    }
}
