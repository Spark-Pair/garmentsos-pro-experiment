<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateLicenseRequest;
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

        if (!$licenses->enabled()) {
            return view('developer.license.status', [
                'status' => LicenseStatus::notEnforced(),
                'fingerprintPreview' => $this->safeValue(fn () => $fingerprints->fingerprintPreview(), 'Pending'),
                'installationPreview' => $this->safeValue(fn () => $identity->maskedUuid(), 'Pending'),
                'installationMode' => config('licensing.installation_mode', 'local_lan'),
                'licensingEnabled' => false,
                'cacheStatus' => LicenseStatus::notEnforced(),
                'foundationReady' => $this->licenseTablesReady(),
                'missingTables' => $this->missingLicenseTables(),
                'licenseConfig' => $this->licenseConfigSummary(),
            ]);
        }

        if (!$this->licenseTablesReady() && !$licenses->usesConfiguredLicense()) {
            return view('developer.license.status', [
                'status' => LicenseStatus::problem(
                    'setup_pending',
                    'none',
                    'Licensing tables are not available yet. Run migrations on a verified staging/client-copy database before using activation.',
                    ['source' => 'setup_check'],
                ),
                'fingerprintPreview' => $fingerprints->fingerprintPreview(),
                'installationPreview' => $identity->maskedUuid(),
                'installationMode' => $identity->installationMode(),
                'licensingEnabled' => $licenses->enabled(),
                'cacheStatus' => LicenseStatus::problem(
                    'setup_pending',
                    'none',
                    'Signed cache checks are waiting for licensing tables.',
                    ['source' => 'setup_check'],
                ),
                'foundationReady' => false,
                'missingTables' => $this->missingLicenseTables(),
                'licenseConfig' => $this->licenseConfigSummary(),
            ]);
        }

        $installation = null;
        if (!$licenses->usesConfiguredLicense()) {
            $installation = $identity->current();
            $installation->update([
                'fingerprint_hash' => $fingerprints->fingerprintHash(),
                'last_seen_at' => now(),
            ]);
        }

        return view('developer.license.status', [
            'status' => $licenses->currentStatus(),
            'fingerprintPreview' => $fingerprints->fingerprintPreview(),
            'installationPreview' => $installation ? $identity->maskedUuid($installation) : $licenses->installId(),
            'installationMode' => $installation?->installation_mode ?? config('licensing.installation_mode', 'local_lan'),
            'licensingEnabled' => $licenses->enabled(),
            'cacheStatus' => $licenses->statusFromSignedCache(),
            'foundationReady' => $this->licenseTablesReady(),
            'missingTables' => $this->missingLicenseTables(),
            'licenseConfig' => $this->licenseConfigSummary(),
        ]);
    }

    protected function licenseConfigSummary(): array
    {
        return [
            'client_id' => (string) config('licensing.client_id', ''),
            'client_name' => (string) config('licensing.client_name', ''),
            'expires_at' => (string) config('licensing.expires_at', ''),
            'grace_days' => (int) config('licensing.offline_grace_days', 7),
            'check_url_configured' => trim((string) config('licensing.server_url', '')) !== '',
            'last_check_at' => (string) config('licensing.last_check_at', ''),
            'env_status' => (string) config('licensing.status', 'active'),
            'license_key_configured' => trim((string) config('licensing.license_key', '')) !== '',
            'license_key_masked' => $this->maskedLicenseKey((string) config('licensing.license_key', '')),
            'check_url' => (string) config('licensing.server_url', ''),
            'install_id' => app(LicenseService::class)->installId(),
        ];
    }

    protected function maskedLicenseKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }

        return strlen($key) <= 8
            ? str_repeat('*', strlen($key))
            : substr($key, 0, 4) . str_repeat('*', max(4, strlen($key) - 8)) . substr($key, -4);
    }

    public function activate()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        return view('developer.license.activate');
    }

    public function activatePost(ActivateLicenseRequest $request, LicenseService $licenses)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        if (!$this->licenseTablesReady()) {
            return redirect()
                ->route('developer.license.status')
                ->with('error', 'Licensing tables are not available yet. Run migrations on a verified staging/client-copy database before activation.');
        }

        $data = $request->validated();

        $status = $licenses->activate($data['license_key']);

        if (!$status->isAllowed()) {
            return redirect()
                ->back()
                ->with('error', $status->message);
        }

        return redirect()
            ->route('developer.license.status')
            ->with('success', $status->message);
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
