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

class AttendancePayrollPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_attendance_user',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);

        return $user;
    }

    public function test_attendance_payroll_routes_are_not_public(): void
    {
        foreach ([
            'attendances.create',
            'attendances.store',
            'attendances.manage-salary',
            'attendances.manage-salary-post',
            'attendances.generate-slip',
            'attendances.generate-slip-post',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected route [{$routeName}] to be registered.");

            $middleware = Route::getRoutes()->getByName($routeName)->gatherMiddleware();

            $this->assertContains('auth', $middleware, "Expected route [{$routeName}] to require auth.");
            $this->assertContains('readonly', $middleware, "Expected route [{$routeName}] to remain readonly protected.");
            $this->assertContains('dbTransaction', $middleware, "Expected route [{$routeName}] to remain transactional.");
        }
    }

    public function test_guest_cannot_access_attendance_or_payroll_writes(): void
    {
        $this->actingUser('guest');

        $this->get(route('attendances.create'))->assertRedirect(route('home'));
        $this->post(route('attendances.store'), ['attendances' => '[]'])->assertRedirect(route('home'));
        $this->get(route('attendances.manage-salary'))->assertRedirect(route('home'));
        $this->post(route('attendances.manage-salary-post'), [])->assertRedirect(route('home'));
        $this->get(route('attendances.generate-slip'))->assertRedirect(route('home'));
        $this->postJson(route('attendances.generate-slip-post'), ['month' => '2026-06'])->assertRedirect(route('home'));
    }

    public function test_manager_can_record_attendance_but_cannot_manage_salary_or_generate_slips(): void
    {
        $this->actingUser('manager');

        $this->get(route('attendances.create'))->assertOk();
        $this->post(route('attendances.store'), ['attendances' => '[]'])->assertRedirect();

        $this->get(route('attendances.manage-salary'))->assertRedirect(route('home'));
        $this->post(route('attendances.manage-salary-post'), [])->assertRedirect(route('home'));
        $this->get(route('attendances.generate-slip'))->assertRedirect(route('home'));
        $this->postJson(route('attendances.generate-slip-post'), ['month' => '2026-06'])->assertRedirect(route('home'));
    }

    public function test_accountant_can_manage_salary_and_generate_slips_but_not_record_attendance(): void
    {
        $this->actingUser('accountant');

        $this->get(route('attendances.create'))->assertRedirect(route('home'));
        $this->post(route('attendances.store'), ['attendances' => '[]'])->assertRedirect(route('home'));

        $this->get(route('attendances.manage-salary'))->assertOk();
        $this->post(route('attendances.manage-salary-post'), [])->assertSessionHasErrors(['month', 'employee_id', 'types_array', 'amount']);
        $this->get(route('attendances.generate-slip'))->assertOk();
        $this->postJson(route('attendances.generate-slip-post'), ['month' => '2026-06'])->assertOk();
    }

    public function test_readonly_mode_blocks_attendance_and_payroll_writes(): void
    {
        $this->actingUser('developer');
        $this->withSession(['readonly' => true]);

        $this->postJson(route('attendances.store'), ['attendances' => '[]'])
            ->assertForbidden()
            ->assertJson(['status' => 'readonly']);

        $this->postJson(route('attendances.manage-salary-post'), [])
            ->assertForbidden()
            ->assertJson(['status' => 'readonly']);

        $this->postJson(route('attendances.generate-slip-post'), ['month' => '2026-06'])
            ->assertForbidden()
            ->assertJson(['status' => 'readonly']);
    }

    public function test_unauthenticated_users_are_blocked_from_attendance_payroll_routes(): void
    {
        $this->get(route('attendances.create'))->assertRedirect(route('login'));
        $this->postJson(route('attendances.store'), ['attendances' => '[]'])->assertUnauthorized();
        $this->get(route('attendances.manage-salary'))->assertRedirect(route('login'));
        $this->postJson(route('attendances.manage-salary-post'), [])->assertUnauthorized();
        $this->get(route('attendances.generate-slip'))->assertRedirect(route('login'));
        $this->postJson(route('attendances.generate-slip-post'), ['month' => '2026-06'])->assertUnauthorized();
    }
}
