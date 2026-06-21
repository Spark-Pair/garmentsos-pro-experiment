<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateLicenseRequest;
use App\Http\Requests\ImportOfflineLicenseRequest;
use App\Http\Requests\ReactivateLicenseRequest;
use App\Http\Requests\RefreshLicenseRequest;
use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Models\License;
use App\Services\BackupService;
use App\Services\Licensing\InstallationFingerprintService;
use App\Services\Licensing\InstallationIdentityService;
use App\Services\Licensing\LicenseService;
use App\Services\Licensing\OfflineActivationService;
use App\Services\RestoreService;

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

        $license = License::latest('id')->first();
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
            'logs' => AuditLog::latest('occurred_at')->limit(50)->get(),
        ]);
    }

    public function backups(BackupService $backups, RestoreService $restore)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        return view('developer.license.backups', [
            'logs' => BackupLog::latest('started_at')->limit(50)->get(),
            'restoreRequirements' => $restore->requirements(),
        ]);
    }
}
