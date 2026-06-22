<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\Updater\UpdateManifestService;
use Illuminate\Http\RedirectResponse;

class UpdateController extends Controller
{
    public function index()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        return view('developer.updater.index', [
            'enabled' => (bool) config('updater.enabled', false),
            'currentVersion' => config('updater.current_version', '0.0.0'),
            'channel' => config('updater.channel', 'stable'),
            'manifestUrlConfigured' => (string) config('updater.manifest_url', '') !== '',
            'signatureRequired' => (bool) config('updater.require_signature', true),
            'result' => session('updater_result'),
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
}
