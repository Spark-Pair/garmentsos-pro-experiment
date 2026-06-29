<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\Updater\UpdateApplyService;
use App\Services\Updater\InstalledVersionService;
use App\Services\Updater\UpdateManifestService;
use Illuminate\Http\RedirectResponse;

class UpdateController extends Controller
{
    public function index(InstalledVersionService $versions)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        return view('developer.updater.index', [
            'enabled' => (bool) config('updater.enabled', false),
            'currentVersion' => $versions->currentVersion(),
            'currentVersionSource' => $versions->source(),
            'currentVersionSourceLabel' => $versions->isDeveloperSourceMode()
                ? 'Developer/source fallback'
                : ucfirst(str_replace('_', ' ', $versions->source())),
            'developerSourceMode' => $versions->isDeveloperSourceMode(),
            'runtimeModeLabel' => $versions->isDeveloperSourceMode()
                ? 'Developer source run'
                : 'Installed client package',
            'channel' => config('updater.channel', 'stable'),
            'manifestUrlConfigured' => (string) config('updater.manifest_url', '') !== '',
            'installedManifestConfigured' => $versions->manifestConfigured(),
            'signatureRequired' => (bool) config('updater.require_signature', true),
            'updateModeStatus' => 'normal',
            'lastCheckTime' => null,
            'skippedOptionalVersions' => [],
            'mandatoryPostponeDeadline' => null,
            'developerApprovalRequired' => false,
            'rollbackAvailable' => false,
            'lastUpdateResult' => null,
            'result' => session('updater_result'),
            'applyResult' => session('updater_apply_result'),
        ]);
    }

    public function check(UpdateManifestService $manifests): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $result = $manifests->checkConfigured();

        return redirect()
            ->route('developer.updater')
            ->with($result['success'] ? 'success' : 'error', $result['message'])
            ->with('updater_result', $result);
    }

    public function apply(UpdateApplyService $updates): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $result = $updates->applyConfigured();

        return redirect()
            ->route('developer.updater')
            ->with($result['success'] ? 'success' : 'error', $result['message'])
            ->with('updater_apply_result', $result);
    }
}
