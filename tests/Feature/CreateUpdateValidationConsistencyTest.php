<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Article;
use App\Models\Setup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateUpdateValidationConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_validation_user',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);

        $this->actingAs($user);
        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);

        return $user;
    }

    public function test_customer_create_accepts_profile_picture_field_used_by_form(): void
    {
        Storage::fake('public');
        $this->actingUser('accountant');
        $city = $this->setupRecord('city', 'Test City', 'TC');

        $this->post(route('customers.store'), [
            'customer_name' => 'Form Customer',
            'person_name' => 'Form Customer',
            'urdu_title' => null,
            'username' => 'formcustomer',
            'password' => 'secret',
            'phone_number' => '000',
            'profile_picture' => $this->fakePng('customer.png'),
            'date' => '2026-06-16',
            'category' => 'regular',
            'city' => $city->id,
            'address' => 'Test Address',
        ])->assertRedirect(route('customers.create'));

        $createdUser = User::where('username', 'formcustomer')->firstOrFail();

        $this->assertNotSame('default_avatar.png', $createdUser->profile_picture);
        Storage::disk('public')->assertExists('uploads/images/'.$createdUser->profile_picture);
    }

    public function test_supplier_create_accepts_profile_picture_field_used_by_form(): void
    {
        Storage::fake('public');
        $this->actingUser('accountant');
        $category = $this->setupRecord('supplier_category', 'Fabric Supplier');

        $this->post(route('suppliers.store'), [
            'supplier_name' => 'Form Supplier',
            'urdu_title' => null,
            'person_name' => 'Form Supplier',
            'username' => 'formsupplier',
            'password' => 'secret',
            'phone_number' => '000',
            'profile_picture' => $this->fakePng('supplier.png'),
            'date' => '2026-06-16',
            'categories_array' => json_encode([$category->id]),
        ])->assertRedirect(route('suppliers.create'));

        $createdUser = User::where('username', 'formsupplier')->firstOrFail();

        $this->assertNotSame('default_avatar.png', $createdUser->profile_picture);
        Storage::disk('public')->assertExists('uploads/images/'.$createdUser->profile_picture);
    }

    public function test_article_store_rejects_invalid_image_before_storing_files(): void
    {
        Storage::fake('public');
        $this->actingUser('accountant');

        $this->post(route('articles.store'), [
            'article_no' => 1,
            'date' => '2026-06-16',
            'category' => 'shirt',
            'size' => 'M',
            'season' => 'Summer',
            'quantity' => 10,
            'extra_pcs' => 0,
            'fabric_type' => 'cotton',
            'rates_array' => '[]',
            'sales_rate' => 100,
            'image_upload' => UploadedFile::fake()->create('payload.txt', 1, 'text/plain'),
        ])->assertSessionHasErrors('image_upload');

        $this->assertDatabaseCount('articles', 0);
        $this->assertSame([], Storage::disk('public')->files('uploads/images'));
    }

    public function test_article_update_rejects_invalid_image_without_deleting_existing_image(): void
    {
        Storage::fake('public');
        $this->actingUser('admin');
        Storage::disk('public')->put('uploads/images/existing-article.png', 'existing');

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
            'image_upload' => UploadedFile::fake()->create('payload.txt', 1, 'text/plain'),
        ])->assertSessionHasErrors('image_upload');

        $article->refresh();

        $this->assertSame('existing-article.png', $article->image);
        Storage::disk('public')->assertExists('uploads/images/existing-article.png');
    }

    private function setupRecord(string $type, string $title, ?string $shortTitle = null): Setup
    {
        return Setup::create([
            'type' => $type,
            'title' => $title,
            'short_title' => $shortTitle ?? $title,
        ]);
    }

    private function fakePng(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
            true
        );

        $path = tempnam(sys_get_temp_dir(), 'garmentsos-validation-upload-test-');
        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
