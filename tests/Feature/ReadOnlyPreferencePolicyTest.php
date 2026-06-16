<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReadOnlyPreferencePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function actingReadonlyUser(): User
    {
        $user = User::create([
            'name' => 'Readonly User',
            'username' => 'readonly_user',
            'password' => Hash::make('password'),
            'role' => 'developer',
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withSession(['readonly' => true]);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class]);

        return $user;
    }

    public function test_safe_preference_setters_remain_allowed_in_readonly_mode(): void
    {
        $this->actingReadonlyUser();

        $preferenceRequests = [
            ['change-data-layout', ['route_name' => 'home', 'layout' => 'grid']],
            ['update-theme', ['theme' => 'dark']],
            ['updateMenuShortcuts', ['menu_shortcuts' => ['home']]],
            ['set-invoice-type', ['invoice_type' => 'shipment']],
            ['set-voucher-type', ['voucher_type' => 'self_account']],
            ['set-production-type', ['production_type' => 'receive']],
            ['set-daily-ledger-type', ['daily_ledger_type' => 'use']],
            ['set-statement-type', ['statement_type' => 'detailed']],
            ['set-physical-quantity-report-type', ['physical_quantity_report_type' => 'stock']],
        ];

        foreach ($preferenceRequests as [$routeName, $payload]) {
            $response = $this->postJson(route($routeName), $payload);

            $response->assertOk();
            $this->assertNotSame('readonly', $response->json('status'), "Route [{$routeName}] was blocked by readonly mode.");
        }
    }

    public function test_business_writes_are_blocked_in_readonly_mode(): void
    {
        $this->actingReadonlyUser();

        $response = $this->postJson(route('daily-ledger.store'), []);

        $response->assertForbidden();
        $response->assertJson([
            'status' => 'readonly',
            'message' => 'Read-only mode is enabled. Write actions are disabled.',
        ]);
    }

    public function test_dead_readonly_route_names_are_not_still_allowed(): void
    {
        $this->actingReadonlyUser();

        Route::middleware(['web', 'readonly'])
            ->post('/__test-dead-readonly-set-cr-type', fn () => response()->json(['ok' => true]))
            ->name('set-cr-type');

        Route::middleware(['web', 'readonly'])
            ->post('/__test-dead-readonly-get-payments-by-method', fn () => response()->json(['ok' => true]))
            ->name('get-payments-by-method');

        $this->postJson('/__test-dead-readonly-set-cr-type')->assertForbidden();
        $this->postJson('/__test-dead-readonly-get-payments-by-method')->assertForbidden();
    }

    public function test_unauthenticated_users_cannot_access_preference_setters(): void
    {
        $response = $this->postJson(route('set-invoice-type'), ['invoice_type' => 'order']);

        $response->assertUnauthorized();
    }

    public function test_preference_setter_routes_remain_non_public(): void
    {
        foreach ([
            'change-data-layout',
            'update-theme',
            'updateMenuShortcuts',
            'set-invoice-type',
            'set-voucher-type',
            'set-production-type',
            'set-daily-ledger-type',
            'set-statement-type',
            'set-physical-quantity-report-type',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected preference route [{$routeName}] to be registered.");

            $middleware = Route::getRoutes()->getByName($routeName)->gatherMiddleware();

            $this->assertContains('auth', $middleware, "Expected preference route [{$routeName}] to require auth.");
            $this->assertContains('readonly', $middleware, "Expected preference route [{$routeName}] to remain under readonly middleware.");
            $this->assertContains('dbTransaction', $middleware, "Expected preference route [{$routeName}] to remain under transaction middleware.");
        }
    }
}
