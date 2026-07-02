<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\Updater\UpdateApplyService;
use App\Services\Updater\InstalledVersionService;
use App\Services\Updater\ReleaseFeedService;
use App\Services\Updater\UpdateLockService;
use App\Services\Updater\UpdateManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class UpdateController extends Controller
{
    public function index(InstalledVersionService $versions, ReleaseFeedService $releaseFeed, UpdateLockService $locks)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        $feedUrl = (string) config('updater.feed_url', '');

        $releaseFeedStatus = $releaseFeed->checkConfigured();
        $launcherHandoff = $releaseFeed->launcherHandoff($releaseFeedStatus);

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
            'updateFeedUrl' => $feedUrl,
            'updateFeedUrlConfigured' => $feedUrl !== '',
            'releaseFeedStatus' => $releaseFeedStatus,
            'launcherProtocolUrl' => $releaseFeed->launcherProtocolUrl(),
            'launcherHandoff' => $launcherHandoff,
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
            'updateLockStatus' => $locks->status(),
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

    public function updateRequest(ReleaseFeedService $releaseFeed): JsonResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            abort(403);
        }

        $result = $releaseFeed->prepareUpdateRequest();
        $payload = $result['request'] ?? [
            'app' => config('updater.app_id', 'garmentsos-pro'),
            'error' => $result['code'] ?? 'update_request_unavailable',
            'message' => $result['message'] ?? 'Update request is not available.',
            'requested_at' => now()->utc()->toIso8601String(),
            'apply_method' => 'windows-launcher-required',
        ];

        $status = !empty($result['success']) ? 200 : 409;

        return response()
            ->json($payload, $status)
            ->header('Content-Disposition', 'attachment; filename="garmentsos-update-request.json"');
    }

    public function signedUpdateRequest(ReleaseFeedService $releaseFeed): JsonResponse
    {
        $result = $releaseFeed->prepareUpdateRequest();
        $payload = $result['request'] ?? [
            'app' => config('updater.app_id', 'garmentsos-pro'),
            'error' => $result['code'] ?? 'update_request_unavailable',
            'message' => $result['message'] ?? 'Update request is not available.',
            'requested_at' => now()->utc()->toIso8601String(),
            'apply_method' => 'windows-launcher-required',
        ];

        return response()->json($payload, !empty($result['success']) ? 200 : 409);
    }

    public function launcherHandoff(ReleaseFeedService $releaseFeed): JsonResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            abort(403);
        }

        $result = $releaseFeed->launcherHandoff();

        return response()->json($result, !empty($result['success']) ? 200 : 409);
    }

    public function startLauncherHandoff(ReleaseFeedService $releaseFeed, UpdateLockService $locks): JsonResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            abort(403);
        }

        $status = $releaseFeed->checkConfigured();
        $result = $releaseFeed->launcherHandoff($status);

        if (empty($result['success']) || empty($result['protocol_url'])) {
            return response()->json($result, 409);
        }

        $user = Auth::user();
        $lock = $locks->start([
            'started_by' => $user ? [
                'id' => $user->id,
                'name' => $user->name ?? $user->username ?? null,
            ] : null,
            'target_version' => $status['latest_version'] ?? ($status['feed']['version'] ?? null),
        ]);

        return response()->json(array_merge($result, [
            'update_lock' => $locks->status(),
            'update_lock_path' => $locks->path(),
            'request_id' => $lock['request_id'] ?? null,
        ]));
    }

    public function updateLockStatus(UpdateLockService $locks): JsonResponse
    {
        return response()->json($locks->status());
    }

    public function clearUpdateLock(Request $request, UpdateLockService $locks): JsonResponse|RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            abort(403);
        }

        $locks->clear();

        $payload = [
            'updating' => false,
            'message' => 'Update lock cleared.',
        ];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return redirect()
            ->route('developer.updater')
            ->with('success', $payload['message']);
    }
}
