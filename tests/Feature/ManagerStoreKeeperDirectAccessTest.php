<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\BankAccount;
use App\Models\Setup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ManagerStoreKeeperDirectAccessTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_direct_access_user',
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

    public function test_reviewed_routes_remain_authenticated(): void
    {
        foreach ([
            'customer-payments.index',
            'supplier-payments.index',
            'payment-programs.index',
            'bank-accounts.index',
            'vouchers.index',
            'employee-payments.index',
            'cr.index',
            'cr.create',
            'cr.store',
            'dr.index',
            'dr.create',
            'dr.store',
            'reports.statement',
            'reports.statement.get-names',
            'reports.statement.record-details',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected route [{$routeName}] to be registered.");
            $this->assertContains('auth', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    public function test_manager_cannot_directly_access_hidden_finance_and_statement_routes(): void
    {
        $this->actingUser('manager');

        foreach ([
            'customer-payments.index',
            'supplier-payments.index',
            'payment-programs.index',
            'bank-accounts.index',
            'vouchers.index',
            'employee-payments.index',
            'cr.index',
            'cr.create',
            'dr.index',
            'dr.create',
            'reports.statement',
        ] as $routeName) {
            $this->assertForbiddenByRoleGate($this->get(route($routeName)));
        }

        $this->assertForbiddenByRoleGate($this->post(route('reports.statement.get-names'), [
            'category' => 'customer',
        ]));

        $this->assertForbiddenByRoleGate($this->get(route('reports.statement.record-details', [
            'type' => 'invoice',
            'id' => 1,
        ])));
    }

    public function test_manager_cannot_post_hidden_finance_write_routes(): void
    {
        $this->actingUser('manager');

        foreach (['cr.store', 'dr.store'] as $routeName) {
            $this->assertForbiddenByRoleGate($this->post(route($routeName), []));
        }
    }

    public function test_manager_cannot_change_bank_account_status_or_serial(): void
    {
        $this->actingUser('manager');
        $bank = Setup::create([
            'type' => 'bank_name',
            'title' => 'Test Bank',
            'short_title' => 'TB',
        ]);
        $account = BankAccount::create([
            'category' => 'self',
            'bank_id' => $bank->id,
            'account_title' => 'Test Account',
            'date' => '2026-06-16',
            'account_no' => 'BA-001',
            'status' => 'active',
        ]);

        $this->assertForbiddenByRoleGate($this->post(route('update-bank-account-status'), [
            'user_id' => $account->id,
            'status' => 'active',
        ]));

        $this->assertForbiddenByRoleGate($this->put(route('bank-accounts.update-serial', $account), [
            'cheque_book_serial' => [
                'start' => 1,
                'end' => 10,
            ],
        ]));
    }

    public function test_store_keeper_keeps_stock_pages_but_not_finance_statement_routes(): void
    {
        $this->actingUser('store_keeper');

        foreach ([
            'articles.index',
            'physical-quantities.index',
            'fabrics.index',
            'reports.article',
            'reports.physical-quantity',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }

        $this->assertForbiddenByRoleGate($this->get(route('reports.statement')));
        $this->assertForbiddenByRoleGate($this->post(route('reports.statement.get-names'), [
            'category' => 'bank_account',
        ]));
    }

    public function test_finance_roles_still_access_tightened_routes(): void
    {
        $this->actingUser('accountant');

        foreach ([
            'customer-payments.index',
            'supplier-payments.index',
            'payment-programs.index',
            'bank-accounts.index',
            'vouchers.index',
            'employee-payments.index',
            'cr.index',
            'dr.index',
            'reports.statement',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }

        $this->post(route('reports.statement.get-names'), [
            'category' => 'bank_account',
        ])->assertOk();
    }

    public function test_readonly_mode_does_not_weaken_manager_finance_access(): void
    {
        $this->actingUser('manager');
        $this->withSession(['readonly' => true]);

        $this->assertForbiddenByRoleGate($this->get(route('bank-accounts.index')));
    }
}
