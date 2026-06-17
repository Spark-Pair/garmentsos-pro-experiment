<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FeatureGuardRolloutTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role = 'admin'): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_feature_guard_user',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class]);

        return $user;
    }

    public function test_disabled_feature_blocks_direct_module_route(): void
    {
        $this->actingUser('admin');
        Config::set('features.shipments', false);

        $this->get(route('shipments.index'))->assertNotFound();
    }

    public function test_enabled_feature_preserves_existing_module_route_access(): void
    {
        $this->actingUser('admin');
        Config::set('features.shipments', true);

        $this->get(route('shipments.index'))->assertOk();
    }

    public function test_disabled_feature_hides_desktop_and_mobile_menu_links(): void
    {
        $this->actingUser('admin');
        Config::set('features.shipments', false);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('Show Shipments', false);
        $response->assertDontSee('/shipments', false);
    }

    public function test_enabled_feature_keeps_menu_links_visible_for_allowed_role(): void
    {
        $this->actingUser('admin');
        Config::set('features.shipments', true);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Show Shipments', false);
        $response->assertSee('/shipments', false);
    }

    public function test_reports_feature_blocks_report_and_statement_adjustment_routes(): void
    {
        $this->actingUser('admin');
        Config::set('features.reports', false);

        $this->get(route('reports.statement'))->assertNotFound();
        $this->get(route('statement-adjustments.create'))->assertNotFound();
    }

    public function test_core_module_routes_have_expected_feature_middleware(): void
    {
        $expected = [
            'suppliers.index' => 'feature:suppliers',
            'customers.index' => 'feature:customers',
            'articles.index' => 'feature:articles',
            'orders.index' => 'feature:orders',
            'shipments.index' => 'feature:shipments',
            'physical-quantities.index' => 'feature:stock',
            'invoices.index' => 'feature:invoices',
            'customer-payments.index' => 'feature:payments',
            'supplier-payments.index' => 'feature:payments',
            'payment-programs.index' => 'feature:payment_programs',
            'bank-accounts.index' => 'feature:bank_accounts',
            'cargos.index' => 'feature:logistics',
            'bilties.index' => 'feature:logistics',
            'expenses.index' => 'feature:expenses',
            'vouchers.index' => 'feature:vouchers',
            'fabrics.index' => 'feature:fabrics',
            'productions.index' => 'feature:production',
            'employees.index' => 'feature:employees',
            'employee-payments.index' => 'feature:payments',
            'cr.index' => 'feature:cr_dr',
            'dr.index' => 'feature:cr_dr',
            'daily-ledger.index' => 'feature:daily_ledger',
            'sales-returns.index' => 'feature:sales_returns',
            'attendances.create' => 'feature:attendance',
            'utility-bills.index' => 'feature:utilities',
            'utility-accounts.index' => 'feature:utilities',
            'reports.statement' => 'feature:reports',
        ];

        foreach ($expected as $routeName => $middleware) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Expected route [{$routeName}] to exist.");
            $this->assertContains($middleware, $route->gatherMiddleware(), "Expected route [{$routeName}] to use [{$middleware}].");
        }
    }
}
