<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class HelperEndpointPermissionTest extends TestCase
{
    public function test_active_helper_routes_remain_inside_authenticated_safety_group(): void
    {
        foreach ([
            'get-order-details',
            'get-category-data',
            'change-data-layout',
            'get-program-details',
            'set-invoice-type',
            'get-shipment-details',
            'set-voucher-type',
            'set-production-type',
            'get-voucher-details',
            'get-employees-by-category',
            'set-daily-ledger-type',
            'get-utility-accounts',
            'set-statement-type',
            'set-physical-quantity-report-type',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected helper route [{$routeName}] to remain registered.");

            $middleware = Route::getRoutes()->getByName($routeName)->gatherMiddleware();

            $this->assertContains('auth', $middleware, "Expected helper route [{$routeName}] to require auth.");
            $this->assertContains('readonly', $middleware, "Expected helper route [{$routeName}] to remain under readonly middleware.");
            $this->assertContains('dbTransaction', $middleware, "Expected helper route [{$routeName}] to remain under transaction middleware.");
        }
    }

    public function test_dead_helper_routes_pointing_to_missing_controller_methods_are_not_registered(): void
    {
        foreach ([
            'get-payments-by-method',
            'set-cr-type',
        ] as $routeName) {
            $this->assertFalse(Route::has($routeName), "Expected dead helper route [{$routeName}] to be removed.");
        }
    }

    public function test_dead_helper_urls_no_longer_match_missing_controller_actions(): void
    {
        foreach ([
            ['POST', '/get-payments-by-method'],
            ['POST', '/set-cr-type'],
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
