<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Customer;
use App\Models\Setup;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReportPermissionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_report_policy_user',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);

        return $user;
    }

    private function assertForbiddenByRoleGate($response): void
    {
        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'You do not have permission to access this page.');
    }

    public function test_report_routes_remain_authenticated(): void
    {
        foreach ([
            'reports.statement',
            'reports.statement.get-names',
            'reports.statement.record-details',
            'reports.pending-payments',
            'reports.article',
            'reports.physical-quantity',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected report route [{$routeName}] to be registered.");

            $middleware = Route::getRoutes()->getByName($routeName)->gatherMiddleware();

            $this->assertContains('auth', $middleware, "Expected report route [{$routeName}] to require auth.");
            $this->assertContains('readonly', $middleware, "Expected report route [{$routeName}] to remain readonly protected.");
        }
    }

    public function test_guest_role_cannot_access_report_pages_or_helpers(): void
    {
        $this->actingUser('guest');

        foreach ([
            ['get', 'reports.statement', []],
            ['post', 'reports.statement.get-names', ['category' => 'customer']],
            ['get', 'reports.statement.record-details', ['type' => 'invoice', 'id' => 1]],
            ['get', 'reports.pending-payments', []],
            ['get', 'reports.article', []],
            ['get', 'reports.physical-quantity', []],
        ] as [$method, $routeName, $params]) {
            $response = $this->{$method}(route($routeName, $params));

            $this->assertForbiddenByRoleGate($response);
        }
    }

    public function test_accountant_can_access_report_pages_and_helpers(): void
    {
        $this->actingUser('accountant');

        foreach ([
            'reports.statement',
            'reports.pending-payments',
            'reports.article',
            'reports.physical-quantity',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }

        $this->post(route('reports.statement.get-names'), [
            'category' => 'customer',
        ])->assertOk();

        $this->get(route('reports.statement.record-details', [
            'type' => 'invoice',
            'id' => 1,
        ]))->assertNotFound();
    }

    public function test_customer_portal_statement_access_is_kept_but_record_types_are_limited(): void
    {
        $customerUser = $this->actingUser('customer');
        $city = Setup::create([
            'type' => 'city',
            'title' => 'Test City',
            'short_title' => 'TC',
        ]);

        Customer::create([
            'user_id' => $customerUser->id,
            'customer_name' => 'Portal Customer',
            'person_name' => 'Portal Customer',
            'phone_number' => '000',
            'date' => '2026-06-16',
            'category' => 'regular',
            'city_id' => $city->id,
            'address' => 'Test Address',
        ]);

        $this->get(route('reports.statement'))->assertOk();

        $this->post(route('reports.statement.get-names'), [
            'category' => 'customer',
        ])->assertOk();

        $this->get(route('reports.statement.record-details', [
            'type' => 'invoice',
            'id' => 1,
        ]))->assertNotFound();

        $this->get(route('reports.statement.record-details', [
            'type' => 'voucher',
            'id' => 1,
        ]))->assertForbidden();
    }

    public function test_supplier_portal_statement_access_is_kept_but_record_types_are_limited(): void
    {
        $supplierUser = $this->actingUser('supplier');

        Supplier::create([
            'user_id' => $supplierUser->id,
            'supplier_name' => 'Portal Supplier',
            'person_name' => 'Portal Supplier',
            'phone_number' => '000',
            'date' => '2026-06-16',
            'categories_array' => json_encode(['supplier']),
        ]);

        $this->get(route('reports.statement'))->assertOk();

        $this->post(route('reports.statement.get-names'), [
            'category' => 'supplier',
        ])->assertOk();

        $this->get(route('reports.statement.record-details', [
            'type' => 'expense',
            'id' => 1,
        ]))->assertNotFound();

        $this->get(route('reports.statement.record-details', [
            'type' => 'invoice',
            'id' => 1,
        ]))->assertForbidden();
    }

    public function test_readonly_mode_does_not_weaken_report_access(): void
    {
        $this->actingUser('guest');
        $this->withSession(['readonly' => true]);

        $this->get(route('reports.pending-payments'))->assertRedirect(route('home'));
    }
}
