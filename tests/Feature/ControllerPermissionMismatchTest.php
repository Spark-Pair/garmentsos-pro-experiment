<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ControllerPermissionMismatchTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_controller_permission_user',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);

        return $user;
    }

    public function test_sensitive_menu_hidden_routes_are_not_public(): void
    {
        foreach ([
            'users.index',
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
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected route [{$routeName}] to be registered.");

            $middleware = Route::getRoutes()->getByName($routeName)->gatherMiddleware();

            $this->assertContains('auth', $middleware, "Expected route [{$routeName}] to require auth.");
        }
    }

    public function test_guest_role_cannot_access_sensitive_direct_urls_hidden_from_menu(): void
    {
        $this->actingUser('guest');

        foreach ([
            'users.index',
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
        ] as $routeName) {
            $response = $this->get(route($routeName));

            $response->assertRedirect(route('home'));
            $response->assertSessionHas('error', 'You do not have permission to access this page.');
        }
    }

    public function test_guest_role_cannot_post_cr_or_dr_directly(): void
    {
        $this->actingUser('guest');

        foreach (['cr.store', 'dr.store'] as $routeName) {
            $response = $this->post(route($routeName), []);

            $response->assertRedirect(route('home'));
            $response->assertSessionHas('error', 'You do not have permission to access this page.');
        }
    }

    public function test_accountant_can_still_access_tightened_sensitive_read_routes(): void
    {
        $this->actingUser('accountant');

        foreach ([
            'users.index',
            'customer-payments.index',
            'supplier-payments.index',
            'payment-programs.index',
            'bank-accounts.index',
            'vouchers.index',
            'employee-payments.index',
            'cr.index',
            'dr.index',
        ] as $routeName) {
            $response = $this->get(route($routeName));

            $response->assertOk();
        }
    }

    public function test_accountant_still_reaches_cr_and_dr_store_validation(): void
    {
        $this->actingUser('accountant');

        $crResponse = $this->post(route('cr.store'), []);
        $crResponse->assertSessionHasErrors(['date', 'voucher_no', 'voucher_id', 'c_r_no', 'returnPayments', 'newPayments']);

        $drResponse = $this->post(route('dr.store'), []);
        $drResponse->assertSessionHasErrors(['customer_id', 'date', 'returnPayments', 'newPayments']);
    }
}
