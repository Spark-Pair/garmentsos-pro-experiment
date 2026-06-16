<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SidebarPermissionAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_sidebar_user',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class]);

        return $user;
    }

    public function test_sidebar_referenced_attendance_routes_exist(): void
    {
        foreach ([
            'attendances.create',
            'attendances.manage-salary',
            'attendances.generate-slip',
            'daily-ledger.index',
            'daily-ledger.create',
            'supplier-payments.index',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected sidebar route [{$routeName}] to exist.");
        }
    }

    public function test_manager_sees_record_attendance_but_not_payroll_links(): void
    {
        $this->actingUser('manager');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Record Attendance', false);
        $response->assertDontSee('Manage Salary', false);
        $response->assertDontSee('Generate Slip', false);
    }

    public function test_accountant_sees_payroll_links_but_not_record_attendance(): void
    {
        $this->actingUser('accountant');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Manage Salary', false);
        $response->assertSee('Generate Slip', false);
        $response->assertDontSee('Record Attendance', false);
    }

    public function test_guest_does_not_see_attendance_or_payroll_links(): void
    {
        $this->actingUser('guest');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('Record Attendance', false);
        $response->assertDontSee('Manage Salary', false);
        $response->assertDontSee('Generate Slip', false);
    }

    public function test_admin_sees_all_attendance_and_payroll_links(): void
    {
        $this->actingUser('admin');

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Record Attendance', false);
        $response->assertSee('Manage Salary', false);
        $response->assertSee('Generate Slip', false);
    }
}
