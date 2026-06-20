<?php

namespace Tests\Unit;

use App\Services\Licensing\LicenseStatus;
use App\Services\AuditLogService;
use App\Services\BackupService;
use App\Services\Licensing\InstallationFingerprintService;
use App\Services\Licensing\OfflineActivationService;
use App\Services\Licensing\SignedLicenseFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LicensingFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'licensing.identity_path' => storage_path('framework/testing/license-' . Str::random(12) . '/installation.json'),
            'licensing.cache_path' => storage_path('framework/testing/license-' . Str::random(12) . '/license.json'),
        ]);
    }

    public function test_valid_status_object(): void
    {
        $status = LicenseStatus::valid('database', [
            'features' => ['reports'],
            'update_channel' => 'stable',
        ]);

        $this->assertSame('valid', $status->state);
        $this->assertSame('none', $status->enforcement);
        $this->assertTrue($status->isAllowed());
        $this->assertSame(['reports'], $status->features);
        $this->assertSame('stable', $status->updateChannel);
    }

    public function test_machine_fingerprint_returns_hash_only(): void
    {
        $hash = app(InstallationFingerprintService::class)->fingerprintHash();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    public function test_signed_license_cache_detects_tampering(): void
    {
        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($keyPair, $privateKey);
        $details = openssl_pkey_get_details($keyPair);
        $publicKey = $details['key'];

        config(['licensing.public_key' => $publicKey]);

        $service = app(SignedLicenseFileService::class);
        $payload = [
            'license_key_hash' => hash('sha256', 'license-key'),
            'fingerprint_hash' => hash('sha256', 'machine'),
            'status' => 'active',
            'expires_at' => now()->addMonth()->toDateString(),
        ];

        openssl_sign($service->canonicalJson($payload), $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $verified = $service->verifyDocument([
            'payload' => $payload,
            'signature' => base64_encode($signature),
        ]);

        $tampered = $service->verifyDocument([
            'payload' => array_merge($payload, ['status' => 'blocked']),
            'signature' => base64_encode($signature),
        ]);

        $this->assertTrue($verified['valid']);
        $this->assertFalse($tampered['valid']);
        $this->assertSame('signature_mismatch', $tampered['reason']);
    }

    public function test_offline_activation_service_generates_request_code_without_internet(): void
    {
        $requestCode = app(OfflineActivationService::class)->requestCode();
        $decoded = json_decode(base64_decode($requestCode), true);

        $this->assertSame('garmentsos-pro', $decoded['app']);
        $this->assertArrayHasKey('installation_uuid', $decoded);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $decoded['fingerprint_hash']);
    }

    public function test_audit_log_sanitizer_removes_secrets(): void
    {
        $sanitized = app(AuditLogService::class)->sanitizeContext([
            'license_key' => 'RAW-LICENSE',
            'nested' => [
                'password' => 'secret-password',
                'safe' => 'value',
            ],
        ]);

        $this->assertSame('[redacted]', $sanitized['license_key']);
        $this->assertSame('[redacted]', $sanitized['nested']['password']);
        $this->assertSame('value', $sanitized['nested']['safe']);
    }

    public function test_backup_service_redacts_public_paths(): void
    {
        $service = app(BackupService::class);

        $this->assertSame('[public-path-redacted]', $service->privatePathOnly('/app/public/backups/db.sqlite'));
        $this->assertSame('storage/app/private/backups/db.sqlite', $service->privatePathOnly('storage/app/private/backups/db.sqlite'));
    }
}
