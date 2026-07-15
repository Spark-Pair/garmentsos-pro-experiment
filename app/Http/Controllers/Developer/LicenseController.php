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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LicenseController extends Controller
{
    public function status(
        LicenseService $licenses,
        InstallationIdentityService $identity,
        InstallationFingerprintService $fingerprints,
    )
    {
        $registrationResult = null;
        try {
            if (!$licenses->developmentBypass()) {
                $registrationResult = $licenses->autoRegisterIfEnabled();
            }
        } catch (Throwable $e) {
            Log::warning('License auto-registration failed while rendering status page.', [
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);
            $registrationResult = [
                'ok' => false,
                'message' => 'Device registration is not reachable right now. You can retry from this page.',
            ];
        }

        try {
            $status = $licenses->currentStatus();
        } catch (Throwable $e) {
            Log::error('License status page failed to calculate current status.', [
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);
            $status = LicenseStatus::problem(
                'activation_required',
                'blocked',
                'License activation status could not be checked. Please review this device registration.',
                ['source' => 'local_error'],
            );
        }

        $licenseConfig = $this->safeLicenseConfigSummary($licenses);
        $requestCache = $this->safeArray(fn () => $licenses->requestCache(), []);
        $verifyCache = $this->safeArray(fn () => $licenses->verifyCache(), []);
        $diagnostics = $this->safeArray(fn () => $licenses->diagnostics(), []);

        return view('developer.license.status', [
            'status' => $status,
            'fingerprintPreview' => $licenseConfig['machine_hash_preview'],
            'installationPreview' => $licenseConfig['install_id'],
            'installationMode' => config('licensing.installation_mode', 'local_lan'),
            'licensingEnabled' => $licenses->enabled(),
            'cacheStatus' => $verifyCache
                ? LicenseStatus::valid('verify_cache', ['message' => 'Verify cache is present.'])
                : LicenseStatus::problem('cache_missing', 'none', 'No device verify cache is present.', ['source' => 'verify_cache']),
            'foundationReady' => $this->licenseTablesReady(),
            'missingTables' => $this->missingLicenseTables(),
            'licenseConfig' => $licenseConfig,
            'registrationResult' => $registrationResult,
            'requestCache' => $requestCache,
            'diagnostics' => $diagnostics,
            'canManageLicense' => auth()->user()?->role === 'developer',
        ]);
    }

    protected function safeLicenseConfigSummary(LicenseService $licenses): array
    {
        try {
            return $this->licenseConfigSummary();
        } catch (Throwable $e) {
            Log::error('License status page failed to build config summary.', [
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return [
                'client_id' => '',
                'client_name' => '',
                'expires_at' => '',
                'grace_days' => (int) config('licensing.offline_grace_days', 7),
                'check_url_configured' => trim((string) config('licensing.server_url', '')) !== '',
                'env_status' => (string) config('licensing.status', 'active'),
                'check_url' => (string) config('licensing.server_url', ''),
                'register_url' => (string) config('licensing.register_url', ''),
                'request_demo_url' => (string) config('licensing.request_demo_url', ''),
                'auto_register' => (bool) config('licensing.auto_register', true),
                'enforcement_enabled' => (bool) config('licensing.enforcement_enabled', false),
                'development_bypass' => (bool) config('licensing.development_bypass', false),
                'install_id' => $this->safeValue(fn () => $licenses->installId(), '-'),
                'machine_name' => $this->safeValue(fn () => $licenses->machineName(), '-'),
                'machine_hash' => '',
                'machine_hash_preview' => $this->safeValue(fn () => $licenses->machineHashPreview(), '-'),
                'previous_machine_hash_preview' => '',
                'fingerprint_source' => 'stable_install_identity',
                'app_version' => $this->safeValue(fn () => app(\App\Services\Updater\InstalledVersionService::class)->currentVersion(), 'local'),
                'last_check_at' => '',
                'last_registration_at' => '',
                'last_request_at' => '',
                'device_status' => '',
                'customer_name' => '',
            ];
        }
    }

    protected function safeArray(callable $callback, array $fallback): array
    {
        try {
            $value = $callback();
            return is_array($value) ? $value : $fallback;
        } catch (Throwable $e) {
            Log::warning('License status page optional cache read failed.', [
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return $fallback;
        }
    }

    protected function licenseConfigSummary(): array
    {
        $licenses = app(LicenseService::class);
        $verifyCache = $licenses->verifyCache();
        $registrationCache = $licenses->registrationCache();
        $requestCache = $licenses->requestCache();
        $diagnostics = $licenses->diagnostics();

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
            'request_demo_url' => (string) config('licensing.request_demo_url', ''),
            'auto_register' => (bool) config('licensing.auto_register', true),
            'enforcement_enabled' => (bool) config('licensing.enforcement_enabled', false),
            'development_bypass' => (bool) config('licensing.development_bypass', false),
            'install_id' => $licenses->installId(),
            'machine_name' => $licenses->machineName(),
            'machine_hash' => $licenses->machineHash(),
            'machine_hash_preview' => $licenses->machineHashPreview(),
            'previous_machine_hash_preview' => (string) ($diagnostics['previous_machine_hash_preview'] ?? ''),
            'fingerprint_source' => (string) ($diagnostics['fingerprint_source'] ?? 'stable_install_identity'),
            'app_version' => app(\App\Services\Updater\InstalledVersionService::class)->currentVersion(),
            'last_check_at' => (string) ($verifyCache['checked_at'] ?? config('licensing.last_check_at', '')),
            'last_registration_at' => (string) ($registrationCache['registered_at'] ?? ''),
            'last_request_at' => (string) ($requestCache['requested_at'] ?? ''),
            'device_status' => (string) ($registrationCache['status'] ?? $verifyCache['status'] ?? $requestCache['status'] ?? ''),
            'customer_name' => (string) ($verifyCache['customer_name'] ?? $verifyCache['client_name'] ?? $registrationCache['client_name'] ?? $requestCache['business_name'] ?? ''),
        ];
    }

    public function requestDemo(Request $request, LicenseService $licenses): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:160'],
            'owner_name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'request_type' => ['required', 'in:demo_trial,paid_activation'],
        ]);

        try {
            $result = $licenses->requestDemo($validated);
            $body = $result['body'] ?? [];
            $message = $body['message'] ?? $result['message'] ?? 'Request sent. Waiting for SparkPair approval.';

            return redirect()
                ->route('developer.license.status')
                ->with(($result['ok'] ?? false) ? 'success' : 'error', $message);
        } catch (Throwable $e) {
            Log::error('License demo request failed inside app.', [
                'error' => $e->getMessage(),
                'type' => $e::class,
                'business_name' => $validated['business_name'] ?? '',
                'request_type' => $validated['request_type'] ?? '',
            ]);

            return redirect()
                ->route('developer.license.status')
                ->with('error', 'Activation request could not be saved locally. Please click Check Status or try again.');
        }
    }

    public function activate()
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        return redirect()
            ->route('developer.license.status')
            ->with('info', 'Manual license-key activation is no longer used. Register this device from the license status page.');
    }

    public function activatePost()
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        return redirect()
            ->route('developer.license.status')
            ->with('info', 'Manual license-key activation is no longer used. Register this device from the license status page.');
    }

    public function registerDevice(LicenseService $licenses): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        try {
            $result = $licenses->registerInstall();
            $body = $result['body'] ?? [];
            $message = $body['message'] ?? $result['message'] ?? 'Device registration request sent.';

            if (($result['ok'] ?? false) && in_array(strtolower((string) ($body['status'] ?? '')), ['active', 'approved'], true)) {
                session()->forget(['readonly', 'license_readonly']);
            }

            return redirect()
                ->route('developer.license.status')
                ->with(($result['ok'] ?? false) ? 'success' : 'error', $message);
        } catch (Throwable $e) {
            Log::error('License device registration failed inside app.', [
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return redirect()
                ->route('developer.license.status')
                ->with('error', 'Device registration could not be completed. Please retry from this page.');
        }
    }

    public function checkDevice(LicenseService $licenses): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        try {
            $status = $licenses->verifyNow();

            if ($status->isAllowed() && !$status->shouldBlock()) {
                session()->forget(['readonly', 'license_readonly']);
            }

            return redirect()
                ->route('developer.license.status')
                ->with($status->shouldBlock() ? 'error' : 'success', $status->shouldBlock()
                    ? $status->message
                    : 'License refreshed. Reloading app may now return normal access.');
        } catch (Throwable $e) {
            Log::error('License status check failed inside app.', [
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return redirect()
                ->route('developer.license.status')
                ->with('error', 'Approval status could not be checked. Please verify internet access and try again.');
        }
    }

    public function runMigrations(Request $request): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $request->validate([
            'confirm_migrations' => ['accepted'],
        ], [
            'confirm_migrations.accepted' => 'Please confirm before running database migrations.',
        ]);

        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());

            Log::info('Developer database migrations executed from license page.', [
                'exit_code' => $exitCode,
                'output' => substr($output, 0, 2000),
            ]);

            return redirect()
                ->route('developer.license.status')
                ->with($exitCode === 0 ? 'success' : 'error', $exitCode === 0
                    ? 'Database migrations completed.'
                    : 'Database migrations finished with errors. Check logs for details.');
        } catch (Throwable $e) {
            Log::error('Developer database migration action failed.', [
                'error' => $e->getMessage(),
                'type' => $e::class,
            ]);

            return redirect()
                ->route('developer.license.status')
                ->with('error', 'Database migrations could not be run. Check logs for details.');
        }
    }

    public function offline(OfflineActivationService $offline)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        return view('developer.license.offline', [
            'requestCode' => $offline->requestCode(),
            'reactivationCode' => null,
        ]);
    }

    public function importOffline(ImportOfflineLicenseRequest $request, LicenseService $licenses)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
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
        if ($resp = $this->denyIfNoRole(['developer'])) {
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
        if ($resp = $this->denyIfNoRole(['developer'])) {
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
        if ($resp = $this->denyIfNoRole(['developer'])) {
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
        ], function (string $table): bool {
            try {
                return !Schema::hasTable($table);
            } catch (Throwable $e) {
                Log::warning('License status page could not inspect table availability.', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);

                return true;
            }
        }));
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
