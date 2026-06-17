<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\CR;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DR;
use App\Models\Setup;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FinanceJsonPayloadValidationTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role = 'accountant'): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_finance_json_user',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);

        return $user;
    }

    public function test_voucher_store_rejects_malformed_payment_json_without_creating_voucher(): void
    {
        $this->actingUser();

        $this->post(route('vouchers.store'), [
            'voucher_no' => '1001',
            'date' => '2026-06-17',
            'supplier_id' => null,
            'payment_details_array' => '{bad json',
        ])->assertSessionHasErrors('payment_details_array');

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('supplier_payments', 0);
    }

    public function test_voucher_store_rejects_zero_or_negative_nested_amount_without_writing(): void
    {
        $this->actingUser();

        $this->post(route('vouchers.store'), [
            'voucher_no' => '1002',
            'date' => '2026-06-17',
            'supplier_id' => null,
            'payment_details_array' => json_encode([
                ['method' => 'Cash', 'amount' => 0, 'self_account_id' => null],
            ]),
        ])->assertSessionHasErrors('payment_details_array');

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('supplier_payments', 0);
    }

    public function test_voucher_update_rejects_invalid_nested_row_before_rebuilding_links(): void
    {
        $this->actingUser();
        $supplier = $this->supplier();
        $voucher = Voucher::create([
            'voucher_no' => 1003,
            'date' => '2026-06-17',
            'supplier_id' => $supplier->id,
        ]);
        $payment = SupplierPayment::create([
            'supplier_id' => $supplier->id,
            'date' => '2026-06-17',
            'method' => 'Cash',
            'amount' => 500,
            'voucher_id' => $voucher->id,
        ]);

        $this->put(route('vouchers.update', $voucher), [
            'payment_details_array' => json_encode([
                ['method' => 'Cash', 'amount' => -1],
            ]),
        ])->assertSessionHasErrors('payment_details_array');

        $this->assertDatabaseHas('supplier_payments', [
            'id' => $payment->id,
            'voucher_id' => $voucher->id,
            'amount' => 500,
        ]);
    }

    public function test_cr_store_rejects_invalid_new_payment_without_mutating_return_payments(): void
    {
        $this->actingUser();
        [$voucher, $supplierPayment, $customerPayment] = $this->voucherReturnFixture();

        $this->post(route('cr.store'), [
            'date' => '2026-06-17',
            'voucher_no' => (string) $voucher->voucher_no,
            'voucher_id' => $voucher->id,
            'c_r_no' => 'CR-1',
            'returnPayments' => json_encode([
                ['id' => $supplierPayment->id, 'payment_id' => $customerPayment->id],
            ]),
            'newPayments' => json_encode([
                ['method' => 'Cheque', 'data_value' => $customerPayment->id, 'amount' => -500],
            ]),
        ])->assertSessionHasErrors('newPayments');

        $this->assertDatabaseCount('c_r_s', 0);
        $this->assertFalse($supplierPayment->fresh()->is_return);
        $this->assertFalse($customerPayment->fresh()->is_return);
    }

    public function test_cr_store_rejects_total_mismatch_before_mutation(): void
    {
        $this->actingUser();
        [$voucher, $supplierPayment, $customerPayment] = $this->voucherReturnFixture();

        $this->post(route('cr.store'), [
            'date' => '2026-06-17',
            'voucher_no' => (string) $voucher->voucher_no,
            'voucher_id' => $voucher->id,
            'c_r_no' => 'CR-2',
            'returnPayments' => json_encode([
                ['id' => $supplierPayment->id, 'payment_id' => $customerPayment->id],
            ]),
            'newPayments' => json_encode([
                ['method' => 'Cheque', 'data_value' => $customerPayment->id, 'amount' => 400],
            ]),
        ])->assertSessionHasErrors('newPayments');

        $this->assertDatabaseCount('c_r_s', 0);
        $this->assertFalse($supplierPayment->fresh()->is_return);
        $this->assertFalse($customerPayment->fresh()->is_return);
    }

    public function test_dr_store_rejects_invalid_new_payment_without_mutating_return_payment(): void
    {
        $this->actingUser();
        $customer = $this->customer();
        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'date' => '2026-06-17',
            'type' => 'normal',
            'method' => 'cheque',
            'amount' => 500,
            'cheque_no' => 'CHQ-1',
            'is_return' => true,
        ]);

        $this->post(route('dr.store'), [
            'customer_id' => $customer->id,
            'date' => '2026-06-17',
            'returnPayments' => json_encode([$payment->id]),
            'newPayments' => json_encode([
                ['method' => 'cash', 'amount' => 0],
            ]),
        ])->assertSessionHasErrors('newPayments');

        $this->assertDatabaseCount('d_r_s', 0);
        $this->assertNull($payment->fresh()->d_r_id);
    }

    public function test_dr_store_valid_payload_still_creates_replacement_payment(): void
    {
        $this->actingUser();
        $customer = $this->customer();
        $payment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'date' => '2026-06-17',
            'type' => 'normal',
            'method' => 'slip',
            'amount' => 500,
            'slip_no' => 'SLIP-1',
            'is_return' => true,
        ]);

        $this->post(route('dr.store'), [
            'customer_id' => $customer->id,
            'date' => '2026-06-17',
            'returnPayments' => json_encode([$payment->id]),
            'newPayments' => json_encode([
                ['method' => 'cash', 'amount' => 500],
            ]),
        ])->assertRedirect(route('dr.create'));

        $this->assertDatabaseCount('d_r_s', 1);
        $this->assertNotNull($payment->fresh()->d_r_id);
        $this->assertDatabaseHas('customer_payments', [
            'customer_id' => $customer->id,
            'type' => 'DR',
            'method' => 'cash',
            'amount' => 500,
        ]);
    }

    public function test_readonly_mode_still_blocks_json_finance_writes(): void
    {
        $this->actingUser();
        $this->withSession(['readonly' => true]);

        $response = $this->postJson(route('vouchers.store'), [
            'voucher_no' => '1004',
            'date' => '2026-06-17',
            'payment_details_array' => json_encode([
                ['method' => 'Cash', 'amount' => 100],
            ]),
        ]);

        $response->assertForbidden();
        $response->assertJson([
            'status' => 'readonly',
            'message' => 'Read-only mode is enabled. Write actions are disabled.',
        ]);
    }

    private function voucherReturnFixture(): array
    {
        $supplier = $this->supplier();
        $customer = $this->customer();
        $customerPayment = CustomerPayment::create([
            'customer_id' => $customer->id,
            'date' => '2026-06-17',
            'type' => 'normal',
            'method' => 'cheque',
            'amount' => 500,
            'cheque_no' => 'CHQ-CR-1',
        ]);
        $voucher = Voucher::create([
            'voucher_no' => 2001,
            'date' => '2026-06-17',
            'supplier_id' => $supplier->id,
        ]);
        $supplierPayment = SupplierPayment::create([
            'supplier_id' => $supplier->id,
            'date' => '2026-06-17',
            'method' => 'Cheque',
            'amount' => 500,
            'cheque_id' => $customerPayment->id,
            'voucher_id' => $voucher->id,
        ]);

        return [$voucher, $supplierPayment, $customerPayment];
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
            'username' => 'customer_finance_json_' . uniqid(),
            'password' => Hash::make('password'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        return Customer::create([
            'user_id' => $user->id,
            'customer_name' => 'Finance JSON Customer ' . uniqid(),
            'person_name' => 'Finance JSON Customer',
            'phone_number' => '000',
            'date' => '2026-06-17',
            'category' => 'regular',
            'city_id' => $city->id,
            'address' => 'Test Address',
        ]);
    }

    private function supplier(): Supplier
    {
        $user = User::create([
            'name' => 'Supplier User',
            'username' => 'supplier_finance_json_' . uniqid(),
            'password' => Hash::make('password'),
            'role' => 'supplier',
            'status' => 'active',
        ]);

        return Supplier::create([
            'user_id' => $user->id,
            'supplier_name' => 'Finance JSON Supplier ' . uniqid(),
            'person_name' => 'Finance JSON Supplier',
            'phone_number' => '000',
            'date' => '2026-06-17',
            'categories_array' => json_encode([]),
        ]);
    }
}
