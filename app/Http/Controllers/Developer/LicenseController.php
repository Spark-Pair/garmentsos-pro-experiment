<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportOfflineLicenseRequest;
use App\Http\Requests\ReactivateLicenseRequest;
use App\Http\Requests\RefreshLicenseRequest;
use App\Models\AuditLog;
use App\Models\License;
use App\Services\Licensing\InstallationFingerprintService;
use App\Services\Licensing\InstallationIdentityService;
use App\Services\Licensing\LicenseService;
use App\Services\Licensing\LicenseStatus;
use App\Services\Licensing\OfflineActivationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;

class LicenseController extends Controller
{
    public function status(
        LicenseService $licenses,
        InstallationIdentityService $identity,
        InstallationFingerprintService $fingerprints,
    )
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $registrationResult = null;
        if ($licenses->enabled()) {
            $registrationResult = $licenses->autoRegisterIfEnabled();
        }

        $status = $licenses->enabled()
            ? $licenses->currentStatus()
            : LicenseStatus::notEnforced();

        return view('developer.license.status', [
            'status' => $status,
            'fingerprintPreview' => $licenses->machineHashPreview(),
            'installationPreview' => $licenses->installId(),
            'installationMode' => config('licensing.installation_mode', 'local_lan'),
            'licensingEnabled' => $licenses->enabled(),
            'cacheStatus' => $licenses->verifyCache()
                ? LicenseStatus::valid('verify_cache', ['message' => 'Verify cache is present.'])
                : LicenseStatus::problem('cache_missing', 'none', 'No device verify cache is present.', ['source' => 'verify_cache']),
            'foundationReady' => $this->licenseTablesReady(),
            'missingTables' => $this->missingLicenseTables(),
            'licenseConfig' => $this->licenseConfigSummary(),
            'registrationResult' => $registrationResult,
        ]);
    }

    protected function licenseConfigSummary(): array
    {
        $licenses = app(LicenseService::class);
        $verifyCache = $licenses->verifyCache();
        $registrationCache = $licenses->registrationCache();

        return [
            'client_id' => (string) ($verifyCache['client_id'] ?? config('licensing.client_id', '')),
            'client_name' => (string) ($verifyCache['client_name'] ?? config('licensing.client_name', '')),
            'expires_at' => (string) config('licensing.expires_at', ''),
            'grace_days' => (int) config('licensing.offline_grace_days', 7),
            'check_url_configured' => trim((string) config('licensing.server_url', '')) !== '',
            'last_check_at' => (string) config('licensing.last_check_at', ''),
            'env_status' => (string) config('licensing.status', 'active'),
            'check_url' => (string) config('licensing.server_url', ''),
            'register_url' => (string) config('licensing.register_url', ''),
            'auto_register' => (bool) config('licensing.auto_register', true),
            'enforcement_enabled' => (bool) config('licensing.enforcement_enabled', false),
            'install_id' => $licenses->installId(),
            'machine_name' => $licenses->machineName(),
            'machine_hash' => $licenses->machineHash(),
            'machine_hash_preview' => $licenses->machineHashPreview(),
            'app_version' => app(\App\Services\Updater\InstalledVersionService::class)->currentVersion(),
            'last_check_at' => (string) ($verifyCache['checked_at'] ?? config('licensing.last_check_at', '')),
            'last_registration_at' => (string) ($registrationCache['registered_at'] ?? ''),
            'device_status' => (string) ($registrationCache['status'] ?? $verifyCache['status'] ?? ''),
            'customer_name' => (string) ($verifyCache['client_name'] ?? $registrationCache['client_name'] ?? ''),
        ];
    }

    public function activate()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        return redirect()
            ->route('developer.license.status')
            ->with('info', 'Manual license-key activation is no longer used. Register this device from the license status page.');
    }

    public function activatePost()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        return redirect()
            ->route('developer.license.status')
            ->with('info', 'Manual license-key activation is no longer used. Register this device from the license status page.');
    }

    public function registerDevice(LicenseService $licenses): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $result = $licenses->registerInstall();
        $body = $result['body'] ?? [];
        $message = $body['message'] ?? $result['message'] ?? 'Device registration request sent.';

        return redirect()
            ->route('developer.license.status')
            ->with(($result['ok'] ?? false) ? 'success' : 'error', $message);
    }

    public function checkDevice(LicenseService $licenses): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $status = $licenses->verifyNow();

        return redirect()
            ->route('developer.license.status')
            ->with($status->shouldBlock() ? 'error' : 'success', $status->message);
    }

    public function offline(OfflineActivationService $offline)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        return view('developer.license.offline', [
            'requestCode' => $offline->requestCode(),
            'reactivationCode' => null,
        ]);
    }

    public function importOffline(ImportOfflineLicenseRequest $request, LicenseService $licenses)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        if (!$this->licenseTablesReady()) {
            return redirect()
                ->route('developer.license.status')
                ->with('error', 'Licensing tables are not available yet. Run migrations on a verified staging/client-copy database before importing a license.');
        }

        $status = $licenses->importSignedLicense($request->validated('signed_license'));

        if (!$status->isAllowed()) {
            return redirect()
                ->back()
                ->with('error', $status->message);
        }

        return redirect()
            ->route('developer.license.status')
            ->with('success', $status->message);
    }

    public function refresh(RefreshLicenseRequest $request, LicenseService $licenses)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        if (!$this->licenseTablesReady()) {
            return redirect()
                ->route('developer.license.status')
                ->with('error', 'Licensing tables are not available yet. Run migrations on a verified staging/client-copy database before refreshing.');
        }

        $status = $licenses->refresh();

        if (!$status->isAllowed()) {
            return redirect()
                ->route('developer.license.status')
                ->with('error', $status->message);
        }

        return redirect()
            ->route('developer.license.status')
            ->with('success', $status->message);
    }

    public function reactivationRequest(ReactivateLicenseRequest $request, OfflineActivationService $offline)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $license = $this->licenseTablesReady() ? License::latest('id')->first() : null;
        $code = $offline->reactivationRequestCode($request->validated('reason'), $license?->metadata ?? null);

        return view('developer.license.offline', [
            'requestCode' => $offline->requestCode(),
            'reactivationCode' => $code,
        ]);
    }

    public function auditLogs()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        return view('developer.license.audit-logs', [
            'logs' => Schema::hasTable('audit_logs') ? AuditLog::latest('occurred_at')->limit(50)->get() : collect(),
            'foundationReady' => Schema::hasTable('audit_logs'),
        ]);
    }

    protected function licenseTablesReady(): bool
    {
        return $this->missingLicenseTables() === [];
    }

    protected function missingLicenseTables(): array
    {
        return array_values(array_filter([
            'app_installations',
            'licenses',
            'license_checks',
            'audit_logs',
        ], fn (string $table) => !Schema::hasTable($table)));
    }

    protected function safeValue(callable $callback, string $fallback): string
    {
        try {
            return (string) $callback();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
