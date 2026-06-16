<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Setup;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PrintExportDownloadPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_print_export_user',
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

    public function test_print_export_download_routes_remain_authenticated(): void
    {
        foreach ([
            'invoices.print',
            'reports.statement',
            'reports.statement.get-names',
            'reports.statement.record-details',
            'reports.pending-payments',
            'reports.article',
            'reports.physical-quantity',
            'attendances.generate-slip',
            'attendances.generate-slip-post',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected route [{$routeName}] to be registered.");

            $middleware = Route::getRoutes()->getByName($routeName)->gatherMiddleware();

            $this->assertContains('auth', $middleware, "Expected route [{$routeName}] to require auth.");
            $this->assertContains('readonly', $middleware, "Expected route [{$routeName}] to remain readonly protected.");
        }

        $this->assertTrue(collect(Route::getRoutes())->contains(function ($route): bool {
            return in_array('GET', $route->methods(), true) && $route->uri() === 'backup-db';
        }), 'Expected /backup-db to remain registered.');
    }

    public function test_unauthenticated_users_cannot_access_print_export_download_routes(): void
    {
        $this->get(route('invoices.print'))->assertRedirect(route('login'));
        $this->get(route('reports.statement'))->assertRedirect(route('login'));
        $this->postJson(route('reports.statement.get-names'), ['category' => 'customer'])->assertUnauthorized();
        $this->get(route('reports.statement.record-details', ['type' => 'invoice', 'id' => 1]))->assertRedirect(route('login'));
        $this->get(route('reports.pending-payments'))->assertRedirect(route('login'));
        $this->get(route('reports.article'))->assertRedirect(route('login'));
        $this->get(route('reports.physical-quantity'))->assertRedirect(route('login'));
        $this->get(route('attendances.generate-slip'))->assertRedirect(route('login'));
        $this->postJson(route('attendances.generate-slip-post'), ['month' => '2026-06'])->assertUnauthorized();
        $this->get('/backup-db')->assertRedirect(route('login'));
    }

    public function test_portal_roles_cannot_open_internal_print_export_pages(): void
    {
        foreach (['customer', 'supplier'] as $role) {
            $this->actingUser($role);

            $this->assertForbiddenByRoleGate($this->get(route('invoices.print')));
            $this->assertForbiddenByRoleGate($this->get(route('reports.pending-payments')));
            $this->assertForbiddenByRoleGate($this->get(route('reports.article')));
            $this->assertForbiddenByRoleGate($this->get(route('reports.physical-quantity')));
            $this->assertForbiddenByRoleGate($this->get(route('attendances.generate-slip')));
            $this->get('/backup-db')
                ->assertForbidden()
                ->assertSeeText('You do not have permission to download database backup.');

            auth()->logout();
        }
    }

    public function test_customer_statement_detail_cannot_cross_scope_to_another_customer_invoice(): void
    {
        $customerUser = $this->actingUser('customer');
        $city = $this->city();

        $ownCustomer = $this->customer($customerUser, $city, 'Own Customer');
        $otherCustomer = $this->customer($this->portalUser('customer', 'other_customer_print_user'), $city, 'Other Customer');

        $ownInvoice = Invoice::create([
            'invoice_no' => '26-0001',
            'customer_id' => $ownCustomer->id,
            'date' => '2026-06-16',
            'netAmount' => 1000,
        ]);

        $otherInvoice = Invoice::create([
            'invoice_no' => '26-0002',
            'customer_id' => $otherCustomer->id,
            'date' => '2026-06-16',
            'netAmount' => 2000,
        ]);

        $this->get(route('reports.statement.record-details', [
            'type' => 'invoice',
            'id' => $ownInvoice->id,
        ]))->assertOk();

        $this->get(route('reports.statement.record-details', [
            'type' => 'invoice',
            'id' => $otherInvoice->id,
        ]))->assertForbidden();
    }

    public function test_supplier_statement_detail_cannot_cross_scope_to_another_supplier_expense(): void
    {
        $supplierUser = $this->actingUser('supplier');
        $expenseType = Setup::create([
            'type' => 'supplier_category',
            'title' => 'Fabric',
            'short_title' => 'FAB',
        ]);

        $ownSupplier = $this->supplier($supplierUser, 'Own Supplier');
        $otherSupplier = $this->supplier($this->portalUser('supplier', 'other_supplier_print_user'), 'Other Supplier');

        $ownExpense = Expense::create([
            'date' => '2026-06-16',
            'supplier_id' => $ownSupplier->id,
            'expense' => $expenseType->id,
            'reff_no' => 'EXP-001',
            'amount' => 1000,
        ]);

        $otherExpense = Expense::create([
            'date' => '2026-06-16',
            'supplier_id' => $otherSupplier->id,
            'expense' => $expenseType->id,
            'reff_no' => 'EXP-002',
            'amount' => 2000,
        ]);

        $this->get(route('reports.statement.record-details', [
            'type' => 'expense',
            'id' => $ownExpense->id,
        ]))->assertOk();

        $this->get(route('reports.statement.record-details', [
            'type' => 'expense',
            'id' => $otherExpense->id,
        ]))->assertForbidden();
    }

    public function test_finance_staff_can_still_access_internal_print_export_pages(): void
    {
        $this->actingUser('accountant');

        $this->get(route('invoices.print'))
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHas('error', 'No invoices to print.');
        $this->get(route('reports.statement'))->assertOk();
        $this->get(route('reports.pending-payments'))->assertOk();
        $this->get(route('reports.article'))->assertOk();
        $this->get(route('reports.physical-quantity'))->assertOk();
        $this->get(route('attendances.generate-slip'))->assertOk();
        $this->postJson(route('attendances.generate-slip-post'), ['month' => '2026-06'])->assertOk();
    }

    public function test_readonly_mode_does_not_weaken_print_export_access(): void
    {
        $this->actingUser('customer');
        $this->withSession(['readonly' => true]);

        $this->assertForbiddenByRoleGate($this->get(route('reports.pending-payments')));
    }

    private function city(): Setup
    {
        return Setup::create([
            'type' => 'city',
            'title' => 'Test City',
            'short_title' => 'TC',
        ]);
    }

    private function customer(?User $user, Setup $city, string $name): Customer
    {
        return Customer::create([
            'user_id' => $user?->id,
            'customer_name' => $name,
            'person_name' => $name,
            'phone_number' => '000',
            'date' => '2026-06-16',
            'category' => 'regular',
            'city_id' => $city->id,
            'address' => 'Test Address',
        ]);
    }

    private function supplier(?User $user, string $name): Supplier
    {
        return Supplier::create([
            'user_id' => $user?->id,
            'supplier_name' => $name,
            'person_name' => $name,
            'phone_number' => '000',
            'date' => '2026-06-16',
            'categories_array' => json_encode(['supplier']),
        ]);
    }

    private function portalUser(string $role, string $username): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Portal User',
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
