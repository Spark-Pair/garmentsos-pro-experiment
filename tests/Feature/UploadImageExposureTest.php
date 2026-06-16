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
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadImageExposureTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role) . ' User',
            'username' => $role . '_upload_user',
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

    public function test_upload_routes_remain_authenticated(): void
    {
        foreach ([
            'users.store',
            'customers.store',
            'customers.update',
            'suppliers.update',
            'employees.store',
            'employees.update',
            'articles.store',
            'articles.update',
            'update-image',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Expected route [{$routeName}] to be registered.");
            $this->assertContains('auth', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
            $this->assertContains('readonly', Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    public function test_customer_creation_upload_uses_public_disk_upload_images_path(): void
    {
        Storage::fake('public');
        $this->actingUser('accountant');
        $city = $this->city();

        $this->post(route('customers.store'), [
            'customer_name' => 'Upload Customer',
            'person_name' => 'Upload Customer',
            'urdu_title' => null,
            'username' => 'uploadcustomer',
            'password' => 'secret',
            'phone_number' => '000',
            'image_upload' => $this->fakePng('customer.png'),
            'date' => '2026-06-16',
            'category' => 'regular',
            'city' => $city->id,
            'address' => 'Test Address',
        ])->assertRedirect(route('customers.create'));

        $createdUser = User::where('username', 'uploadcustomer')->firstOrFail();

        $this->assertNotSame('default_avatar.png', $createdUser->profile_picture);
        Storage::disk('public')->assertExists('uploads/images/'.$createdUser->profile_picture);
    }

    public function test_customer_creation_rejects_non_image_upload(): void
    {
        Storage::fake('public');
        $this->actingUser('accountant');
        $city = $this->city();

        $this->post(route('customers.store'), [
            'customer_name' => 'Invalid Upload Customer',
            'person_name' => 'Invalid Upload Customer',
            'urdu_title' => null,
            'username' => 'invaliduploadcustomer',
            'password' => 'secret',
            'phone_number' => '000',
            'image_upload' => UploadedFile::fake()->create('payload.txt', 1, 'text/plain'),
            'date' => '2026-06-16',
            'category' => 'regular',
            'city' => $city->id,
            'address' => 'Test Address',
        ])->assertSessionHasErrors('image_upload');

        $this->assertSame([], Storage::disk('public')->files('uploads/images'));
    }

    public function test_guest_cannot_upload_or_update_article_image(): void
    {
        Storage::fake('public');
        $this->actingUser('guest');
        $article = $this->article();

        $this->assertForbiddenByRoleGate($this->post(route('update-image'), [
            'article_id' => $article->id,
            'image_upload' => $this->fakePng('article.png'),
        ]));

        $this->assertSame([], Storage::disk('public')->files('uploads/images'));
    }

    public function test_allowed_role_can_update_article_image_with_valid_upload(): void
    {
        Storage::fake('public');
        $this->actingUser('store_keeper');
        $article = $this->article();

        $this->post(route('update-image'), [
            'article_id' => $article->id,
            'image_upload' => $this->fakePng('article.png'),
        ])->assertRedirect(route('articles.index'));

        $article->refresh();

        $this->assertNotSame('no_image_icon.png', $article->image);
        Storage::disk('public')->assertExists('uploads/images/'.$article->image);
    }

    public function test_article_image_update_rejects_non_image_upload(): void
    {
        Storage::fake('public');
        $this->actingUser('store_keeper');
        $article = $this->article();

        $this->post(route('update-image'), [
            'article_id' => $article->id,
            'image_upload' => UploadedFile::fake()->create('payload.txt', 1, 'text/plain'),
        ])->assertSessionHasErrors('image_upload');

        $article->refresh();

        $this->assertSame('no_image_icon.png', $article->image);
        $this->assertSame([], Storage::disk('public')->files('uploads/images'));
    }

    public function test_release_rules_exclude_upload_and_public_runtime_paths(): void
    {
        $rules = json_decode(file_get_contents(base_path('scripts/release-rules.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ([
            'storage/app',
            'storage/app/**',
            'public/storage',
            'public/storage/**',
            'public/uploads',
            'public/uploads/**',
        ] as $excludedPath) {
            $this->assertContains($excludedPath, $rules['exclude_paths']);
        }
    }

    private function city(): Setup
    {
        return Setup::create([
            'type' => 'city',
            'title' => 'Test City',
            'short_title' => 'TC',
        ]);
    }

    private function article(): Article
    {
        return Article::create([
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
            'image' => 'no_image_icon.png',
        ]);
    }

    private function fakePng(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
            true
        );

        $path = tempnam(sys_get_temp_dir(), 'garmentsos-upload-test-');
        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
