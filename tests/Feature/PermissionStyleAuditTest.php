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

class PermissionStyleAuditTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_permission_style_user',
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

    public function test_newly_reviewed_business_helper_routes_remain_authenticated(): void
    {
        foreach ([
            'sales-returns.index',
            'sales-returns.create',
            'sales-returns.get-details',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected route [{$routeName}] to be registered.");
            $this->assertContains('auth', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }

        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByAction('App\Http\Controllers\DRController@getPayments')->methods());
    }

    public function test_unauthorized_roles_cannot_access_sales_return_routes_or_dr_helper(): void
    {
        foreach (['manager', 'store_keeper', 'guest'] as $role) {
            $this->actingUser($role);

            $this->assertForbiddenByRoleGate($this->get(route('sales-returns.index')));
            $this->assertForbiddenByRoleGate($this->get(route('sales-returns.create')));
            $this->assertForbiddenByRoleGate($this->post(route('sales-returns.get-details'), [
                'customer_id' => 1,
                'getArticles' => true,
            ]));
            $this->assertForbiddenByRoleGate($this->get('/dr/get-payments?customer_id=1'));
        }
    }

    public function test_finance_role_can_access_sales_return_routes_and_dr_helper(): void
    {
        $this->actingUser('accountant');

        $this->get(route('sales-returns.index'))->assertOk();
        $this->get(route('sales-returns.create'))->assertOk();

        $this->post(route('sales-returns.get-details'), [
            'customer_id' => 1,
            'getArticles' => true,
        ])->assertOk();

        $this->get('/dr/get-payments?customer_id=1')
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [],
            ]);
    }

    public function test_readonly_mode_does_not_weaken_business_helper_access(): void
    {
        $this->actingUser('manager');
        $this->withSession(['readonly' => true]);

        $this->assertForbiddenByRoleGate($this->get(route('sales-returns.index')));
    }
}
