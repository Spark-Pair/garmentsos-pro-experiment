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
        if (!session('readonly', false)) {
            return $next($request);
        }

        $method = strtoupper($request->getMethod());
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];

        if (in_array($method, $safeMethods, true)) {
            return $next($request);
        }

        // These POST routes do not mutate business records. They only fetch
        // data needed by forms while the app is in read-only mode.
        $readonlyDataFetcherRouteNames = [
            'get-order-details',
            'get-category-data',
            'get-program-details',
            'get-shipment-details',
            'get-voucher-details',
            'get-employees-by-category',
            'get-utility-accounts',
            'sales-returns.get-details',
            'reports.statement.get-names',
            'statement-adjustments.first-transaction-date',
        ];

        // These routes update the authenticated user's UI/report preferences
        // only. They are allowed so read-only users can keep navigating safely.
        $readonlyPreferenceRouteNames = [
            'change-data-layout',
            'update-theme',
            'updateMenuShortcuts',
            'set-invoice-type',
            'set-voucher-type',
            'set-production-type',
            'set-daily-ledger-type',
            'set-statement-type',
            'set-physical-quantity-report-type',
        ];

        // Session lifecycle endpoints are allowed because they do not change
        // operational records such as invoices, payments, stock, or ledgers.
        $readonlySessionRouteNames = [
            'update-last-activity',
            'logout',
        ];

        $allowedRouteNames = array_merge(
            $readonlyDataFetcherRouteNames,
            $readonlyPreferenceRouteNames,
            $readonlySessionRouteNames,
        );

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
