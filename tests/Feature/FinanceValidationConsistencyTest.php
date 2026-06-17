<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Customer;
use App\Models\PaymentProgram;
use App\Models\Setup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FinanceValidationConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role = 'accountant', array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_finance_validation_user',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'daily_ledger_type' => 'deposit',
        ], $attributes));

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);

        return $user;
    }

    public function test_daily_ledger_rejects_zero_and_negative_deposit_amounts(): void
    {
        $this->actingUser('accountant', ['daily_ledger_type' => 'deposit']);

        $this->post(route('daily-ledger.store'), [
            'date' => '2026-06-16',
            'method' => 'cash',
            'amount' => 0,
            'reff_no' => 'ZERO',
        ])->assertSessionHasErrors('amount');

        $this->post(route('daily-ledger.store'), [
            'date' => '2026-06-16',
            'method' => 'cash',
            'amount' => -10,
            'reff_no' => 'NEGATIVE',
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('daily_ledger_deposits', 0);
    }

    public function test_daily_ledger_rejects_zero_and_negative_use_amounts(): void
    {
        $this->actingUser('accountant', ['daily_ledger_type' => 'use']);

        $this->post(route('daily-ledger.store'), [
            'date' => '2026-06-16',
            'case' => 'Daily Expenses',
            'amount' => 0,
            'remarks' => 'Zero amount',
        ])->assertSessionHasErrors('amount');

        $this->post(route('daily-ledger.store'), [
            'date' => '2026-06-16',
            'case' => 'Daily Expenses',
            'amount' => -10,
            'remarks' => 'Negative amount',
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('daily_ledger_uses', 0);
    }

    public function test_bank_account_requires_existing_bank_setup_id(): void
    {
        $this->actingUser();

        $this->post(route('bank-accounts.store'), [
            'category' => 'self',
            'bank_id' => 999,
            'account_title' => 'Invalid Bank Account',
            'date' => '2026-06-16',
            'account_no' => '123',
        ])->assertSessionHasErrors('bank_id');

        $this->assertDatabaseCount('bank_accounts', 0);
    }

    public function test_payment_program_requires_valid_sub_category_for_supplier_category(): void
    {
        $this->actingUser();
        $customer = $this->customer();

        $this->post(route('payment-programs.store'), [
            'date' => '2026-06-16',
            'customer_id' => $customer->id,
            'category' => 'supplier',
            'sub_category' => 999,
            'amount' => 1000,
            'remarks' => 'Invalid supplier sub category',
        ])->assertSessionHasErrors('sub_category');

        $this->assertDatabaseCount('payment_programs', 0);
    }

    public function test_payment_program_waiting_category_still_allows_empty_sub_category(): void
    {
        $this->actingUser();
        $customer = $this->customer();

        $this->post(route('payment-programs.store'), [
            'date' => '2026-06-16',
            'customer_id' => $customer->id,
            'category' => 'waiting',
            'sub_category' => null,
            'amount' => 1000,
            'remarks' => 'Waiting program',
        ])->assertRedirect(route('payment-programs.create'));

        $program = PaymentProgram::firstOrFail();

        $this->assertSame('waiting', $program->category);
        $this->assertNull($program->sub_category_id);
        $this->assertNull($program->sub_category_type);
    }

    public function test_payment_program_update_requires_valid_sub_category_for_self_account_category(): void
    {
        $this->actingUser();
        $customer = $this->customer();

        $program = PaymentProgram::create([
            'program_no' => 1,
            'date' => '2026-06-16',
            'customer_id' => $customer->id,
            'category' => 'waiting',
            'amount' => 1000,
            'remarks' => 'Existing program',
        ]);

        $this->post(route('payment-programs.update-program'), [
            'program_id' => $program->id,
            'category' => 'self_account',
            'sub_category' => 999,
            'amount' => 1200,
            'remarks' => 'Invalid self account',
        ])->assertSessionHasErrors('sub_category');

        $program->refresh();

        $this->assertSame('waiting', $program->category);
        $this->assertNull($program->sub_category_id);
        $this->assertNull($program->sub_category_type);
    }

    private function customer(): Customer
    {
        $city = Setup::create([
            'type' => 'city',
            'title' => 'Test City',
            'short_title' => 'TC',
        ]);

        $user = User::create([
            'name' => 'Customer User',
            'username' => 'customer_finance_validation_user',
            'password' => Hash::make('password'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_name' => 'Finance Validation Customer',
            'person_name' => 'Finance Validation Customer',
            'phone_number' => '000',
            'date' => '2026-06-16',
            'category' => 'regular',
            'city_id' => $city->id,
            'address' => 'Test Address',
        ]);
    }
}
