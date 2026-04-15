<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentProgram;
use App\Models\Setup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoreFlowsTest extends TestCase
{
    use RefreshDatabase;

    private function actingDeveloper(): User
    {
        $user = User::create([
            'name' => 'Dev User',
            'username' => 'dev_user',
            'password' => Hash::make('password'),
            'role' => 'developer',
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);

        return $user;
    }

    public function test_orders_create_with_date_returns_success(): void
    {
        $this->actingDeveloper();

        $response = $this->get('/orders/create?date=2026-02-26');

        $response->assertStatus(200);
    }

    public function test_shipments_create_with_date_returns_success(): void
    {
        $this->actingDeveloper();

        $response = $this->get('/shipments/create?date=2026-02-26');

        $response->assertStatus(200);
    }

    public function test_payment_program_mark_paid_updates_status(): void
    {
        $user = $this->actingDeveloper();

        $city = Setup::create([
            'title' => 'Karachi',
            'short_title' => 'KHI',
            'type' => 'city',
        ]);

        $customerUser = User::create([
            'name' => 'Customer User',
            'username' => 'customer_user',
            'password' => Hash::make('password'),
            'role' => 'guest',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_name' => 'Test Customer',
            'person_name' => 'Test Person',
            'phone_number' => '03001234567',
            'date' => '2026-02-26',
            'category' => 'cash',
            'city_id' => $city->id,
            'address' => 'Test Address',
            'creator_id' => $user->id,
        ]);

        $program = PaymentProgram::create([
            'program_no' => 999001,
            'date' => '2026-02-26',
            'customer_id' => $customer->id,
            'category' => 'waiting',
            'amount' => 1000,
            'status' => 'Unpaid',
        ]);

        \App\Models\CustomerPayment::create([
            'customer_id' => $customer->id,
            'date' => '2026-02-26',
            'type' => 'program',
            'method' => 'program',
            'amount' => 1000,
            'program_id' => $program->id,
        ]);

        $response = $this->post(route('payment-programs.mark-paid', $program->id));

        $response->assertRedirect(route('payment-programs.index'));
        $this->assertSame('Paid', $program->fresh()->status);
    }

    public function test_program_payment_can_exceed_remaining_balance_and_mark_program_overpaid(): void
    {
        $user = $this->actingDeveloper();

        $city = Setup::create([
            'title' => 'Lahore',
            'short_title' => 'LHR',
            'type' => 'city',
        ]);

        $customerUser = User::create([
            'name' => 'Customer 2',
            'username' => 'customer_user_2',
            'password' => Hash::make('password'),
            'role' => 'guest',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_name' => 'Balance Check Customer',
            'person_name' => 'Person',
            'phone_number' => '03009998888',
            'date' => '2026-02-26',
            'category' => 'cash',
            'city_id' => $city->id,
            'address' => 'Addr',
            'creator_id' => $user->id,
        ]);

        $program = PaymentProgram::create([
            'program_no' => 999002,
            'date' => '2026-02-26',
            'customer_id' => $customer->id,
            'category' => 'supplier',
            'amount' => 1000,
            'status' => 'Unpaid',
        ]);

        CustomerPayment::create([
            'customer_id' => $customer->id,
            'date' => '2026-02-26',
            'type' => 'program',
            'method' => 'program',
            'amount' => 900,
            'program_id' => $program->id,
        ]);

        $response = $this->post(route('customer-payments.store'), [
            'customer_id' => $customer->id,
            'date' => '2026-02-26',
            'type' => 'program',
            'method' => 'program',
            'amount' => 200,
            'program_id' => $program->id,
        ]);

        $response->assertSessionHas('success', 'Payment Added successfully.');
        $this->assertDatabaseHas('customer_payments', [
            'customer_id' => $customer->id,
            'program_id' => $program->id,
            'amount' => 200,
        ]);

        $markPaidResponse = $this->post(route('payment-programs.mark-paid', $program->id));

        $markPaidResponse->assertRedirect(route('payment-programs.index'));
        $this->assertSame('Overpaid', $program->fresh()->status);
    }

    public function test_clear_amount_cannot_exceed_outstanding_amount(): void
    {
        $user = $this->actingDeveloper();

        $city = Setup::create([
            'title' => 'Faisalabad',
            'short_title' => 'FSD',
            'type' => 'city',
        ]);

        $bank = Setup::create([
            'title' => 'Meezan Bank',
            'short_title' => 'MZN',
            'type' => 'bank_name',
        ]);

        $customerUser = User::create([
            'name' => 'Customer 3',
            'username' => 'customer_user_3',
            'password' => Hash::make('password'),
            'role' => 'guest',
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_name' => 'Clear Test Customer',
            'person_name' => 'Person',
            'phone_number' => '03007776666',
            'date' => '2026-02-26',
            'category' => 'cash',
            'city_id' => $city->id,
            'address' => 'Addr',
            'creator_id' => $user->id,
        ]);

        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'date' => '2026-02-26',
            'type' => 'cheque',
            'method' => 'cheque',
            'amount' => 1000,
            'cheque_no' => 'CHQ-001',
            'cheque_date' => '2026-02-26',
        ]);

        $bankAccount = BankAccount::create([
            'category' => 'self',
            'bank_id' => (string) $bank->id,
            'account_title' => 'Test Account',
            'date' => '2026-02-26',
            'account_no' => 'AC-0001',
        ]);

        $response = $this->post(route('customer-payments.clear', $payment->id), [
            'clear_date' => '2026-02-26',
            'method_select' => 'cheque',
            'bank_account_id' => $bankAccount->id,
            'amount' => 1200,
            'reff_no' => 'CLR-001',
        ]);

        $response->assertSessionHas('error', 'Clear amount remaining outstanding se zyada nahi ho sakta.');
    }
}
