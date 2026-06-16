<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class RouteSafetyCleanupTest extends TestCase
{
    public function test_existing_implemented_resource_routes_remain_registered(): void
    {
        foreach ([
            'supplier-payments.index',
            'daily-ledger.index',
            'daily-ledger.create',
            'daily-ledger.store',
            'invoices.index',
            'invoices.create',
            'invoices.store',
            'articles.index',
            'articles.edit',
            'articles.update',
            'payment-programs.index',
            'payment-programs.create',
            'payment-programs.store',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected route [{$routeName}] to remain registered.");
        }
    }

    public function test_custom_working_routes_remain_registered(): void
    {
        foreach ([
            'customer-payments.clear',
            'customer-payments.split',
            'payment-programs.update-program',
            'payment-programs.mark-paid',
            'bank-accounts.update-serial',
            'bank-accounts.update-serial-post',
            'fabrics.issue',
            'fabrics.issuePost',
            'fabrics.return',
            'fabrics.returnPost',
            'sales-returns.get-details',
            'invoices.print',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected custom route [{$routeName}] to remain registered.");
        }
    }

    public function test_incomplete_supplier_payment_routes_are_not_registered(): void
    {
        foreach ([
            'supplier-payments.create',
            'supplier-payments.store',
            'supplier-payments.show',
            'supplier-payments.edit',
            'supplier-payments.update',
            'supplier-payments.destroy',
        ] as $routeName) {
            $this->assertFalse(Route::has($routeName), "Expected incomplete route [{$routeName}] to be removed.");
        }
    }

    public function test_incomplete_daily_ledger_routes_are_not_registered(): void
    {
        foreach ([
            'daily-ledger.show',
            'daily-ledger.edit',
            'daily-ledger.update',
            'daily-ledger.destroy',
        ] as $routeName) {
            $this->assertFalse(Route::has($routeName), "Expected incomplete route [{$routeName}] to be removed.");
        }
    }

    public function test_common_blank_resource_actions_are_not_registered(): void
    {
        foreach ([
            'invoices.show',
            'invoices.edit',
            'invoices.update',
            'invoices.destroy',
            'physical-quantities.show',
            'physical-quantities.edit',
            'physical-quantities.update',
            'physical-quantities.destroy',
            'payment-programs.show',
            'payment-programs.edit',
            'payment-programs.update',
            'payment-programs.destroy',
            'rates.show',
            'rates.edit',
            'rates.update',
            'rates.destroy',
        ] as $routeName) {
            $this->assertFalse(Route::has($routeName), "Expected blank route [{$routeName}] to be removed.");
        }
    }

    public function test_direct_incomplete_urls_do_not_match_blank_controller_actions(): void
    {
        foreach ([
            ['GET', '/supplier-payments/create'],
            ['POST', '/supplier-payments'],
            ['GET', '/supplier-payments/1'],
            ['GET', '/daily-ledger/1'],
            ['GET', '/daily-ledger/1/edit'],
            ['PUT', '/daily-ledger/1'],
            ['GET', '/invoices/1/edit'],
            ['DELETE', '/invoices/1'],
        ] as [$method, $path]) {
            try {
                Route::getRoutes()->match(request()->create($path, $method));
                $this->fail("Expected [{$method} {$path}] to be unavailable.");
            } catch (NotFoundHttpException|MethodNotAllowedHttpException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
