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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class UpdateController extends Controller
{
    private const SPARKPAIR_STABLE_FEED = 'https://sparkpair.dev/api/updates/garmentsos-pro/stable/latest.json';

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
            'curlDiagnostics' => $releaseFeed->curlDiagnostics(),
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
            'databaseDiagnostics' => $this->databaseDiagnostics(),
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

    public function signedUpdateRequest(Request $request, ReleaseFeedService $releaseFeed): JsonResponse
    {
        $result = $releaseFeed->prepareUpdateRequest(requestId: (string) $request->query('request_id', ''));
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
            'request_id' => $result['request_id'] ?? null,
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

    public function signedUpdateFailed(Request $request, UpdateLockService $locks): JsonResponse
    {
        return response()->json($locks->fail(
            requestId: (string) $request->query('request_id', ''),
            message: 'Launcher handoff failed before update started.'
        ));
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

    public function setStableFeed(): RedirectResponse
    {
        if ($resp = $this->denyIfNoRole(['developer', 'admin'])) {
            return $resp;
        }

        try {
            $this->setEnvValue('UPDATE_FEED_URL', self::SPARKPAIR_STABLE_FEED);

            config(['updater.feed_url' => self::SPARKPAIR_STABLE_FEED]);
            Artisan::call('config:clear');

            return redirect()
                ->route('developer.updater')
                ->with('success', 'Update feed set to SparkPair stable. Existing unrelated .env values were preserved.');
        } catch (\Throwable $exception) {
            return redirect()
                ->route('developer.updater')
                ->with('error', 'Could not update feed URL: ' . $exception->getMessage());
        }
    }

    protected function setEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            throw new \RuntimeException('.env file was not found.');
        }

        $content = File::get($envPath);
        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);
        File::put($backupDir . DIRECTORY_SEPARATOR . 'env_' . now()->format('Ymd_His') . '.env', $content);

        $line = $key . '=' . $this->quoteEnvValue($value);
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $line, $content, 1);
        } else {
            $ending = str_contains($content, "\r\n") ? "\r\n" : "\n";
            $content = rtrim($content, "\r\n") . $ending . $line . $ending;
        }

        File::put($envPath, $content);
    }

    protected function quoteEnvValue(string $value): string
    {
        return preg_match('/\s|#|"|\'/', $value)
            ? '"' . str_replace('"', '\"', $value) . '"'
            : $value;
    }

    protected function databaseDiagnostics(): array
    {
        $connection = (string) config('database.default', '');
        $databasePath = (string) config('database.connections.sqlite.database', env('DB_DATABASE', ''));
        $hostPath = '';

        if ($databasePath !== '' && !str_starts_with($databasePath, '/')) {
            $candidate = base_path($databasePath);
            if (File::exists($candidate)) {
                $hostPath = $candidate;
            }
        }

        $lastBackupPath = storage_path('app/private/update-backup-status.json');
        $lastBackup = [];
        if (File::exists($lastBackupPath)) {
            $decoded = json_decode((string) File::get($lastBackupPath), true);
            $lastBackup = is_array($decoded) ? $decoded : [];
        }

        return [
            'connection' => $connection,
            'container_database_path' => $databasePath,
            'host_database_path' => $hostPath,
            'database_volume' => 'garmentsos-pro_garmentsos_database',
            'storage_volume' => 'garmentsos-pro_garmentsos_storage',
            'last_backup_path' => (string) ($lastBackup['backup_path'] ?? ''),
            'last_backup_status' => (string) ($lastBackup['backup_status'] ?? ''),
            'last_backup_timestamp' => (string) ($lastBackup['timestamp'] ?? ''),
            'last_backup_database_path' => (string) ($lastBackup['database_path'] ?? ''),
        ];
    }
}
