<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Article;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Setup;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileImageUpdatePreservationTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_image_preservation_user',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);

        return $user;
    }

    public function test_customer_update_without_image_preserves_existing_profile_picture(): void
    {
        $this->actingUser('admin');
        $customer = $this->customer('existing-customer.png');

        $this->put(route('customers.update', $customer), [
            'person_name' => 'Updated Customer',
            'urdu_title' => 'Updated Urdu',
            'phone_number' => '111',
            'category' => 'regular',
            'address' => 'Updated Address',
        ])->assertRedirect(route('customers.index'));

        $customer->user->refresh();

        $this->assertSame('existing-customer.png', $customer->user->profile_picture);
    }

    public function test_supplier_update_without_image_preserves_existing_profile_picture(): void
    {
        $this->actingUser('admin');
        $supplier = $this->supplier('existing-supplier.png');

        $this->put(route('suppliers.update', $supplier), [
            'phone_number' => '111',
        ])->assertRedirect(route('suppliers.index'));

        $supplier->user->refresh();

        $this->assertSame('existing-supplier.png', $supplier->user->profile_picture);
    }

    public function test_employee_update_without_image_preserves_existing_profile_picture(): void
    {
        $this->actingUser('admin');
        $type = $this->setupRecord('staff_type', 'Staff');
        $employee = Employee::create([
            'category' => 'staff',
            'type_id' => $type->id,
            'employee_name' => 'Existing Employee',
            'urdu_title' => 'Existing',
            'phone_number' => '000',
            'joining_date' => '2026-06-16',
            'cnic_no' => '123',
            'salary' => 1000,
            'profile_picture' => 'existing-employee.png',
        ]);

        $this->put(route('employees.update', $employee), [
            'type_id' => $type->id,
            'urdu_title' => 'Updated',
            'phone_number' => '111',
            'cnic_no' => '456',
            'salary' => 2000,
        ])->assertRedirect(route('employees.index'));

        $employee->refresh();

        $this->assertSame('existing-employee.png', $employee->profile_picture);
    }

    public function test_article_update_without_image_preserves_existing_image(): void
    {
        $this->actingUser('admin');
        $article = Article::create([
            'article_no' => 'A-001',
            'date' => '2026-06-16',
            'category' => 'shirt',
            'size' => 'M',
            'season' => 'Summer',
            'quantity' => 10,
            'extra_pcs' => 0,
            'fabric_type' => 'cotton',
            'sales_rate' => 100,
            'rates_array' => [],
            'pcs_per_packet' => 1,
            'image' => 'existing-article.png',
        ]);

        $this->put(route('articles.update', $article), [
            'article_no' => 'A-001',
            'category' => 'shirt',
            'size' => 'L',
            'season' => 'Summer',
            'quantity' => 11,
            'extra_pcs' => 1,
            'fabric_type' => 'cotton',
            'rates_array' => '[]',
            'sales_rate' => 110,
        ])->assertRedirect(route('articles.index'));

        $article->refresh();

        $this->assertSame('existing-article.png', $article->image);
    }

    private function customer(string $profilePicture): Customer
    {
        $city = $this->setupRecord('city', 'Test City', 'TC');
        $user = $this->portalUser('customer', 'customer_image_user', $profilePicture);

        return Customer::create([
            'user_id' => $user->id,
            'customer_name' => 'Existing Customer',
            'person_name' => 'Existing Customer',
            'urdu_title' => 'Existing',
            'phone_number' => '000',
            'date' => '2026-06-16',
            'category' => 'regular',
            'city_id' => $city->id,
            'address' => 'Test Address',
        ]);
    }

    private function supplier(string $profilePicture): Supplier
    {
        $user = $this->portalUser('supplier', 'supplier_image_user', $profilePicture);

        return Supplier::create([
            'user_id' => $user->id,
            'supplier_name' => 'Existing Supplier',
            'person_name' => 'Existing Supplier',
            'urdu_title' => 'Existing',
            'phone_number' => '000',
            'date' => '2026-06-16',
            'categories_array' => json_encode([$this->setupRecord('supplier_category', 'Supplier')->id]),
        ]);
    }

    private function portalUser(string $role, string $username, string $profilePicture): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Portal User',
            'username' => $username,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'profile_picture' => $profilePicture,
        ]);
    }

    private function setupRecord(string $type, string $title, ?string $shortTitle = null): Setup
    {
        return Setup::create([
            'type' => $type,
            'title' => $title,
            'short_title' => $shortTitle ?? $title,
        ]);
    }
}
