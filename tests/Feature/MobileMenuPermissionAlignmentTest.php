<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MobileMenuPermissionAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_mobile_menu_user',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class]);

        return $user;
    }

    public function test_mobile_menu_referenced_routes_exist(): void
    {
        foreach ([
            'users.index',
            'users.create',
            'permissions-report',
            'suppliers.index',
            'suppliers.create',
            'customers.index',
            'customers.create',
            'articles.index',
            'articles.create',
            'orders.index',
            'orders.create',
            'payment-programs.index',
            'payment-programs.create',
            'attendances.create',
            'attendances.manage-salary',
            'attendances.generate-slip',
            'setups.index',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected mobile menu route [{$routeName}] to exist.");
        }
    }

    public function test_guest_does_not_see_restricted_mobile_menu_links(): void
    {
        $this->actingUser('guest');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('Add User', false);
        $response->assertDontSee('Add Supplier', false);
        $response->assertDontSee('Add Customer', false);
        $response->assertDontSee('Add Article', false);
        $response->assertDontSee('Generate Order', false);
        $response->assertDontSee('Setups', false);
        $response->assertDontSee('Record Attendance', false);
        $response->assertDontSee('Manage Salary', false);
        $response->assertDontSee('Generate Slip', false);
    }

    public function test_manager_mobile_menu_shows_attendance_only(): void
    {
        $this->actingUser('manager');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Record Attendance', false);
        $response->assertDontSee('Manage Salary', false);
        $response->assertDontSee('Generate Slip', false);
    }

    public function test_accountant_mobile_menu_shows_payroll_but_not_attendance_recording(): void
    {
        $this->actingUser('accountant');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Manage Salary', false);
        $response->assertSee('Generate Slip', false);
        $response->assertDontSee('Record Attendance', false);
    }

    public function test_admin_mobile_menu_shows_business_and_attendance_links(): void
    {
        $this->actingUser('admin');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Add Supplier', false);
        $response->assertSee('Add Customer', false);
        $response->assertSee('Add Article', false);
        $response->assertSee('Generate Order', false);
        $response->assertSee('Setups', false);
        $response->assertSee('Record Attendance', false);
        $response->assertSee('Manage Salary', false);
        $response->assertSee('Generate Slip', false);
    }
}
