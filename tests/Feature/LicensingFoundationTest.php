<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureValidLicense;
use App\Http\Middleware\CheckActiveSession;
use App\Http\Middleware\SubscriptionExpiry;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AppInstallation;
use App\Models\License;
use App\Models\User;
use App\Services\Licensing\LicenseService;
use App\Services\Licensing\InstallationFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Tests\TestCase;

class LicensingFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'licensing.enabled' => false,
            'licensing.identity_path' => storage_path('framework/testing/license-' . Str::random(12) . '/installation.json'),
            'licensing.cache_path' => storage_path('framework/testing/license-' . Str::random(12) . '/license.json'),
        ]);

        $this->withoutMiddleware([CheckActiveSession::class, SubscriptionExpiry::class, VerifyCsrfToken::class]);
    }

    public function test_licensing_disabled_middleware_passes_through(): void
    {
        config(['licensing.enabled' => false]);

        $middleware = new EnsureValidLicense();
        $request = Request::create('/test-license-disabled', 'GET');
        Session::put('existing_key', 'existing_value');

        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
        $this->assertSame('existing_value', Session::get('existing_key'));
        $this->assertFalse(Session::has('readonly'));
    }

    public function test_no_license_blocks_only_when_enforcement_enabled(): void
    {
        config(['licensing.enabled' => true]);

        $status = app(LicenseService::class)->currentStatus();

        $this->assertSame('unactivated', $status->state);
        $this->assertSame('blocked', $status->enforcement);
    }

    public function test_subscription_expired_calculates_readonly_status(): void
    {
        config(['licensing.enabled' => true]);
        $installation = AppInstallation::create([
            'installation_uuid' => (string) Str::uuid(),
            'installation_mode' => 'local_lan',
            'fingerprint_hash' => app(InstallationFingerprintService::class)->fingerprintHash(),
        ]);

        $license = License::create([
            'app_installation_id' => $installation->id,
            'license_key_hash' => hash('sha256', 'expired-key'),
            'status' => 'active',
            'subscription_status' => 'expired',
            'subscription_expires_at' => now()->subDay(),
            'enforcement_mode' => 'readonly',
        ]);

        $status = app(LicenseService::class)->statusForLicense($license);

        $this->assertSame('subscription_expired', $status->state);
        $this->assertSame('readonly', $status->enforcement);
    }

    public function test_blocked_license_calculates_blocked_status(): void
    {
        config(['licensing.enabled' => true]);
        $installation = AppInstallation::create([
            'installation_uuid' => (string) Str::uuid(),
            'installation_mode' => 'local_lan',
            'fingerprint_hash' => app(InstallationFingerprintService::class)->fingerprintHash(),
        ]);

        $license = License::create([
            'app_installation_id' => $installation->id,
            'license_key_hash' => hash('sha256', 'blocked-key'),
            'status' => 'blocked',
            'subscription_expires_at' => now()->addMonth(),
            'enforcement_mode' => 'readonly',
        ]);

        $status = app(LicenseService::class)->statusForLicense($license);

        $this->assertSame('blocked', $status->state);
        $this->assertSame('blocked', $status->enforcement);
    }

    public function test_installation_persists_fingerprint_hash_without_raw_machine_details(): void
    {
        $installation = AppInstallation::create([
            'installation_uuid' => (string) Str::uuid(),
            'installation_mode' => 'local_lan',
            'fingerprint_hash' => hash('sha256', 'server-installation'),
            'status' => 'active',
        ]);

        $this->assertSame(64, strlen($installation->fingerprint_hash));
        $this->assertArrayNotHasKey('raw_machine_details', $installation->getAttributes());
        $this->assertArrayNotHasKey('machine_guid', $installation->getAttributes());
        $this->assertArrayNotHasKey('bios_serial', $installation->getAttributes());
    }

    public function test_lan_browser_clients_are_not_separate_licensed_devices(): void
    {
        $this->assertFalse(class_exists(\App\Models\LicenseDevice::class));
        $this->assertTrue(class_exists(AppInstallation::class));
    }

    public function test_missing_license_tables_render_friendly_pending_state_instead_of_500(): void
    {
        Schema::dropIfExists('license_checks');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('app_installations');

        $this->actingAs($this->user('developer'));

        $this->get(route('developer.license.status'))
            ->assertOk()
            ->assertSee('Licensing setup is pending')
            ->assertSee('Setup pending')
            ->assertSee('Missing: app_installations, licenses, license_checks, audit_logs');

        $this->post(route('developer.license.refresh'))
            ->assertRedirect(route('developer.license.status'))
            ->assertSessionHas('error');
    }

    protected function user(string $role): User
    {
        return User::create([
            'name' => Str::title($role) . ' User',
            'username' => $role . '_' . Str::random(8),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
