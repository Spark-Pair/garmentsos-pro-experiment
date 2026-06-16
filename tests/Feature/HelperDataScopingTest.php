<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HelperDataScopingTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_helper_scope_user',
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

    public function test_customer_and_supplier_roles_cannot_call_cross_scope_business_helpers(): void
    {
        foreach (['customer', 'supplier'] as $role) {
            $this->actingUser($role);

            foreach ([
                ['get-order-details', ['order_no' => 'ORD-1']],
                ['get-category-data', ['category' => 'self_account']],
                ['get-program-details', ['program_no' => 1, 'customer_id' => 1]],
                ['get-shipment-details', ['shipment_no' => 'SHP-1']],
                ['get-voucher-details', ['voucher_no' => 'VCH-1']],
                ['get-employees-by-category', ['category' => 'worker']],
                ['get-utility-accounts', ['bill_type_id' => 1, 'location_id' => 1]],
            ] as [$routeName, $payload]) {
                $this->assertForbiddenByRoleGate($this->post(route($routeName), $payload));
            }
        }
    }

    public function test_accountant_can_still_call_business_helpers(): void
    {
        $this->actingUser('accountant');

        $this->post(route('get-category-data'), ['category' => 'customer'])->assertOk();
        $this->post(route('get-order-details'), ['order_no' => 'ORD-1'])->assertOk();
        $this->post(route('get-program-details'), ['program_no' => 1, 'customer_id' => 1])->assertOk();
        $this->post(route('get-shipment-details'), ['shipment_no' => 'SHP-1'])->assertOk();
        $this->post(route('get-voucher-details'), ['voucher_no' => 'VCH-1'])->assertOk();
        $this->post(route('get-employees-by-category'), ['category' => 'worker'])->assertOk();
        $this->post(route('get-utility-accounts'), ['bill_type_id' => 1, 'location_id' => 1])->assertOk();
    }

    public function test_store_keeper_can_call_utility_helper_but_not_finance_helpers(): void
    {
        $this->actingUser('store_keeper');

        $this->post(route('get-utility-accounts'), [
            'bill_type_id' => 1,
            'location_id' => 1,
        ])->assertOk();

        foreach ([
            ['get-category-data', ['category' => 'self_account']],
            ['get-order-details', ['order_no' => 'ORD-1']],
            ['get-shipment-details', ['shipment_no' => 'SHP-1']],
            ['get-voucher-details', ['voucher_no' => 'VCH-1']],
            ['get-employees-by-category', ['category' => 'worker']],
        ] as [$routeName, $payload]) {
            $this->assertForbiddenByRoleGate($this->post(route($routeName), $payload));
        }
    }

    public function test_readonly_mode_does_not_weaken_helper_scope(): void
    {
        $this->actingUser('supplier');
        $this->withSession(['readonly' => true]);

        $this->assertForbiddenByRoleGate($this->post(route('get-category-data'), [
            'category' => 'customer',
        ]));
    }
}
