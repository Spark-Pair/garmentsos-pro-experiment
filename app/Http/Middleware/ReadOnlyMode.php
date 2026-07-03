<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReadOnlyMode
{
    /**
     * Block write operations when readonly mode is active.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('license_readonly', false)) {
            return $next($request);
        }

        $method = strtoupper($request->getMethod());
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];

        if (in_array($method, $safeMethods, true)) {
            return $next($request);
        }

        // Allow read-only POST endpoints (data fetchers)
        $allowedRouteNames = [
            'get-order-details',
            'get-category-data',
            'get-program-details',
            'get-shipment-details',
            'get-voucher-details',
            'get-payments-by-method',
            'get-employees-by-category',
            'get-utility-accounts',
            'sales-returns.get-details',
            'reports.statement.get-names',
            'statement-adjustments.first-transaction-date',
            'change-data-layout',
            'set-invoice-type',
            'set-voucher-type',
            'set-production-type',
            'set-daily-ledger-type',
            'set-cr-type',
            'set-statement-type',
            'set-physical-quantity-report-type',
            'update-last-activity',
            'logout',
            'developer.backups.store',
            'developer.backups.verify',
            'developer.updater.check',
            'developer.updater.apply',
            'developer.updater.launcher-handoff.start',
            'developer.updater.clear-update-lock',
            'developer.updater.set-experiment-feed',
            'developer.license.activate.post',
            'developer.license.register',
            'developer.license.check',
            'developer.license.offline.import',
            'developer.license.refresh',
            'developer.license.reactivation-request',
        ];

        $routeName = $request->route()?->getName();
        if ($routeName && in_array($routeName, $allowedRouteNames, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'readonly',
                'message' => 'Read-only mode is enabled. Write actions are disabled.',
            ], 403);
        }

        return redirect()->back()->with('error', 'Read-only mode is enabled. You cannot perform this action.');
    }
}
