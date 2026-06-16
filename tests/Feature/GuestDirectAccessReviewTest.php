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

class GuestDirectAccessReviewTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_guest_access_user',
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

    public function test_reviewed_guest_access_routes_remain_authenticated(): void
    {
        foreach ([
            'suppliers.index',
            'customers.index',
            'articles.index',
            'orders.index',
            'shipments.index',
            'invoices.index',
            'invoices.print',
            'cargos.index',
            'bilties.index',
            'bilties.create',
            'bilties.store',
            'employees.index',
            'employees.create',
            'fabrics.index',
            'physical-quantities.index',
            'productions.index',
            'productions.create',
            'productions.store',
            'expenses.index',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected route [{$routeName}] to be registered.");
            $this->assertContains('auth', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    public function test_guest_cannot_access_operational_read_pages_hidden_from_menu(): void
    {
        $this->actingUser('guest');

        foreach ([
            'suppliers.index',
            'customers.index',
            'articles.index',
            'orders.index',
            'shipments.index',
            'invoices.index',
            'cargos.index',
            'bilties.index',
            'employees.index',
            'fabrics.index',
            'physical-quantities.index',
            'productions.index',
            'expenses.index',
        ] as $routeName) {
            $this->assertForbiddenByRoleGate($this->get(route($routeName)));
        }
    }

    public function test_guest_cannot_access_missing_gate_forms_or_writes(): void
    {
        $this->actingUser('guest');

        foreach ([
            'employees.create',
            'bilties.create',
            'productions.create',
        ] as $routeName) {
            $this->assertForbiddenByRoleGate($this->get(route($routeName)));
        }

        foreach ([
            'bilties.store',
            'productions.store',
        ] as $routeName) {
            $this->assertForbiddenByRoleGate($this->post(route($routeName), []));
        }
    }

    public function test_guest_cannot_access_invoice_print_route(): void
    {
        $this->actingUser('guest');

        $this->assertForbiddenByRoleGate($this->get(route('invoices.print')));
    }

    public function test_staff_roles_still_access_tightened_read_pages(): void
    {
        $this->actingUser('accountant');

        foreach ([
            'suppliers.index',
            'customers.index',
            'articles.index',
            'orders.index',
            'shipments.index',
            'invoices.index',
            'cargos.index',
            'bilties.index',
            'employees.index',
            'fabrics.index',
            'physical-quantities.index',
            'productions.index',
            'expenses.index',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_customer_and_supplier_portal_access_is_preserved(): void
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

        $this->get(route('orders.index'))->assertOk();

        $supplierUser = User::create([
            'name' => 'Supplier User',
            'username' => 'supplier_portal_guest_access_user',
            'password' => Hash::make('password'),
            'role' => 'supplier',
            'status' => 'active',
        ]);

        $this->actingAs($supplierUser);

        Supplier::create([
            'user_id' => $supplierUser->id,
            'supplier_name' => 'Portal Supplier',
            'person_name' => 'Portal Supplier',
            'phone_number' => '000',
            'date' => '2026-06-16',
            'categories_array' => json_encode(['supplier']),
        ]);

        $this->get(route('expenses.index'))->assertOk();
        $this->get(route('productions.index'))->assertOk();
    }

    public function test_readonly_mode_does_not_weaken_guest_access(): void
    {
        $this->actingUser('guest');
        $this->withSession(['readonly' => true]);

        $this->assertForbiddenByRoleGate($this->get(route('orders.index')));
    }
}
