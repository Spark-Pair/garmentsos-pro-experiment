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

class SensitiveWritePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_user',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'daily_ledger_type' => 'deposit',
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);

        return $user;
    }

    public function test_daily_ledger_write_routes_are_not_public(): void
    {
        foreach ([
            'daily-ledger.create',
            'daily-ledger.store',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected route [{$routeName}] to be registered.");

            $middleware = Route::getRoutes()->getByName($routeName)->gatherMiddleware();

            $this->assertContains('auth', $middleware, "Expected route [{$routeName}] to require auth.");
            $this->assertContains('readonly', $middleware, "Expected route [{$routeName}] to remain readonly protected.");
            $this->assertContains('dbTransaction', $middleware, "Expected route [{$routeName}] to remain transactional.");
        }
    }

    public function test_guest_role_cannot_open_daily_ledger_create_form(): void
    {
        $this->actingUser('guest');

        $response = $this->get(route('daily-ledger.create'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'You do not have permission to access this page.');
    }

    public function test_guest_role_cannot_store_daily_ledger_record(): void
    {
        $this->actingUser('guest');

        $response = $this->post(route('daily-ledger.store'), [
            'date' => '2026-06-16',
            'method' => 'cash',
            'amount' => 100,
            'reff_no' => 'TEST',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('error', 'You do not have permission to access this page.');
    }

    public function test_accountant_can_reach_daily_ledger_store_validation(): void
    {
        $this->actingUser('accountant');

        $response = $this->post(route('daily-ledger.store'), []);

        $response->assertSessionHasErrors(['date', 'amount']);
    }

    public function test_readonly_mode_still_blocks_daily_ledger_store_before_business_write(): void
    {
        $this->actingUser('accountant');
        $this->withSession(['readonly' => true]);

        $response = $this->postJson(route('daily-ledger.store'), [
            'date' => '2026-06-16',
            'method' => 'cash',
            'amount' => 100,
        ]);

        $response->assertForbidden();
        $response->assertJson([
            'status' => 'readonly',
            'message' => 'Read-only mode is enabled. Write actions are disabled.',
        ]);
    }
}
