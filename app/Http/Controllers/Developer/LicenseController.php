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

        if (!$this->licenseTablesReady()) {
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
            ]);
        }

        $installation = $identity->current();
        $installation->update([
            'fingerprint_hash' => $fingerprints->fingerprintHash(),
            'last_seen_at' => now(),
        ]);

        return view('developer.license.status', [
            'status' => $licenses->currentStatus(),
            'fingerprintPreview' => $fingerprints->fingerprintPreview(),
            'installationPreview' => $identity->maskedUuid($installation),
            'installationMode' => $installation->installation_mode,
            'licensingEnabled' => $licenses->enabled(),
            'cacheStatus' => $licenses->statusFromSignedCache(),
            'foundationReady' => true,
            'missingTables' => [],
        ]);
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
}
