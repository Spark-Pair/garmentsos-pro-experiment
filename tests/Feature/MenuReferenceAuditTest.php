<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MenuReferenceAuditTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_menu_audit_user',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class]);

        return $user;
    }

    public function test_critical_menu_route_references_exist(): void
    {
        foreach ([
            'users.index',
            'users.create',
            'permissions-report',
            'supplier-payments.index',
            'daily-ledger.index',
            'daily-ledger.create',
            'attendances.create',
            'attendances.manage-salary',
            'attendances.generate-slip',
            'orders.index',
            'orders.create',
            'payment-programs.index',
            'payment-programs.create',
            'reports.statement',
            'statement-adjustments.create',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected menu route [{$routeName}] to exist.");
        }
    }

    public function test_accountant_can_see_users_index_but_not_add_user(): void
    {
        $this->actingUser('accountant');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Show Users', false);
        $response->assertDontSee('Add User', false);
        $response->assertDontSee('/users/create', false);
    }

    public function test_manager_can_see_add_user_link(): void
    {
        $this->actingUser('manager');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Show Users', false);
        $response->assertSee('Add User', false);
        $response->assertSee('/users/create', false);
    }

    public function test_guest_does_not_see_users_menu_links(): void
    {
        $this->actingUser('guest');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('Show Users', false);
        $response->assertDontSee('Add User', false);
        $response->assertDontSee('/users/create', false);
    }
}
