<?php

namespace App\Services\Licensing;

use App\Models\License;
use App\Models\LicenseCheck;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class LicenseService
{
    public function __construct(
        protected InstallationIdentityService $identity,
        protected InstallationFingerprintService $fingerprints,
        protected SignedLicenseFileService $signedFiles,
        protected LicenseActivationClient $activationClient,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('licensing.enabled', false);
    }

    public function currentStatus(): LicenseStatus
    {
        if (!$this->enabled()) {
            return LicenseStatus::disabled();
        }

        $installation = $this->identity->current();
        $license = License::query()
            ->where('app_installation_id', $installation->id)
            ->latest('id')
            ->first();

        if (!$license) {
            return LicenseStatus::problem(
                'unactivated',
                'blocked',
                'No license has been activated.',
            );
        }

        return $this->statusForLicense($license);
    }

    public function statusForLicense(License $license): LicenseStatus
    {
        $payload = [
            'expires_at' => $license->subscription_expires_at,
            'grace_until' => $license->offline_grace_until,
            'modules' => $license->allowed_modules ?? [],
            'features' => $license->allowed_features ?? [],
            'brands' => $license->allowed_brand_ids ?? [],
            'update_channel' => $license->update_channel,
            'source' => 'database',
        ];

        if ($license->status === 'unactivated') {
            return LicenseStatus::problem('unactivated', 'blocked', 'No license has been activated.', $payload);
        }

        if (in_array($license->status, ['suspended', 'blocked'], true)) {
            return LicenseStatus::problem(
                $license->status,
                'blocked',
                'License is ' . $license->status . '.',
                $payload,
            );
        }

        if ($license->license_expires_at && Carbon::now()->greaterThan($license->license_expires_at)) {
            return LicenseStatus::problem(
                'expired',
                'blocked',
                'License has expired.',
                $payload,
            );
        }

        if (
            $license->subscription_status === 'expired'
            || ($license->subscription_expires_at && Carbon::now()->greaterThan($license->subscription_expires_at))
        ) {
            return LicenseStatus::problem(
                'subscription_expired',
                $license->enforcement_mode ?: $this->defaultEnforcementMode(),
                'Subscription has expired. Read-only mode should be used when enforcement is enabled.',
                $payload,
            );
        }

        $installation = $license->installation;

        if (!$installation || $installation->fingerprint_hash !== $this->fingerprints->fingerprintHash()) {
            return LicenseStatus::problem(
                'installation_mismatch',
                'blocked',
                'This app installation does not match the activated license.',
                $payload,
            );
        }

        return LicenseStatus::valid('database', $payload);
    }

    public function activate(string $licenseKey): LicenseStatus
    {
        if (!$this->enabled()) {
            return LicenseStatus::disabled();
        }

        $installation = $this->identity->current();
        $fingerprintHash = $this->fingerprints->fingerprintHash();
        $result = $this->activationClient->activate($licenseKey, $fingerprintHash);

        if (!($result['ok'] ?? false)) {
            $status = LicenseStatus::problem(
                'activation_failed',
                'none',
                $result['message'] ?? 'License activation failed.',
            );
            $this->recordCheck($installation, null, 'activation', $status);

            return $status;
        }

        return LicenseStatus::valid('server', [
            'message' => 'License activation response received.',
        ]);
    }

    public function statusFromSignedCache(): LicenseStatus
    {
        $result = $this->signedFiles->read();

        if (($result['valid'] ?? false) === true) {
            return LicenseStatus::valid('signed_cache', [
                'message' => 'Signed license cache is valid.',
            ]);
        }

        $reason = (string) ($result['reason'] ?? 'invalid');
        if ($reason === 'missing') {
            return LicenseStatus::problem(
                'unactivated',
                'blocked',
                'No signed license cache is present.',
                ['source' => 'signed_cache'],
            );
        }

        return LicenseStatus::problem(
            'tampered',
            'blocked',
            'Signed license cache is invalid or tampered.',
            ['source' => 'signed_cache'],
        );
    }

    public function recordCheck(mixed $installation, ?License $license, string $type, LicenseStatus $status): void
    {
        LicenseCheck::create([
            'app_installation_id' => $installation?->id,
            'license_id' => $license?->id,
            'check_type' => $type,
            'result' => $status->state,
            'enforcement' => $status->enforcement,
            'checked_at' => now(),
            'message' => $status->message,
            'context' => [
                'source' => $status->source,
            ],
            'user_id' => Auth::id(),
        ]);
    }

    protected function defaultEnforcementMode(): string
    {
        return (string) config('licensing.default_enforcement_mode', 'readonly');
    }
}
