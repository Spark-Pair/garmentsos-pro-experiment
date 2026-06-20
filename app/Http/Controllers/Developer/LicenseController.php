<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Services\BackupService;
use App\Services\Licensing\InstallationFingerprintService;
use App\Services\Licensing\InstallationIdentityService;
use App\Services\Licensing\LicenseService;
use App\Services\Licensing\OfflineActivationService;
use App\Services\RestoreService;
use Illuminate\Http\Request;

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
        ]);
    }

    public function activate()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        return view('developer.license.activate');
    }

    public function activatePost(Request $request, LicenseService $licenses)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:255'],
        ]);

        $status = $licenses->activate($data['license_key']);

        if ($status->state === 'disabled') {
            return redirect()
                ->route('developer.license.status')
                ->with('info', 'License enforcement is disabled. Activation was not attempted.');
        }

        if (!$status->isAllowed()) {
            return redirect()
                ->back()
                ->withInput()
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
        ]);
    }

    public function importOffline(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $request->validate([
            'signed_license' => ['required', 'string'],
        ]);

        return redirect()
            ->route('developer.license.status')
            ->with('info', 'Offline signed license import skeleton is ready; verification will be wired in a later phase.');
    }

    public function refresh()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        return redirect()
            ->route('developer.license.status')
            ->with('info', 'Subscription refresh skeleton is ready; online refresh will be wired in a later phase.');
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
