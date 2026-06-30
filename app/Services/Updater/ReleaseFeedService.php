<?php

namespace App\Services\Updater;

use Illuminate\Support\Facades\Http;

class ReleaseFeedService
{
    protected array $requiredFields = [
        'app',
        'version',
        'channel',
        'mandatory',
        'released_at',
        'package_file',
        'package_sha256_file',
        'package_sha256',
        'package_url',
        'min_launcher_version',
        'notes',
    ];

    public function __construct(protected InstalledVersionService $versions)
    {
    }

    public function checkConfigured(): array
    {
        $feedUrl = trim((string) config('updater.feed_url', ''));
        $currentVersion = $this->versions->currentVersion();

        if ($feedUrl === '') {
            return $this->result('feed_not_configured', 'Update feed is not configured.', [
                'success' => false,
                'feed_url' => '',
                'current_version' => $currentVersion,
                'update_available' => false,
            ]);
        }

        try {
            $response = Http::timeout((int) config('updater.feed_timeout', 8))
                ->acceptJson()
                ->get($feedUrl);
        } catch (\Throwable $exception) {
            return $this->result('feed_unreachable', 'Update feed could not be reached.', [
                'success' => false,
                'feed_url' => $feedUrl,
                'current_version' => $currentVersion,
                'error' => $exception->getMessage(),
                'update_available' => false,
            ]);
        }

        if (!$response->ok()) {
            return $this->result('feed_unreachable', 'Update feed returned an HTTP error.', [
                'success' => false,
                'feed_url' => $feedUrl,
                'current_version' => $currentVersion,
                'http_status' => $response->status(),
                'update_available' => false,
            ]);
        }

        $feed = $response->json();
        if (!is_array($feed)) {
            return $this->invalidFeed($feedUrl, $currentVersion, 'Feed response is not valid JSON.');
        }

        return $this->validateFeed($feed, $feedUrl, $currentVersion);
    }

    public function validateFeed(array $feed, string $feedUrl = '', ?string $currentVersion = null): array
    {
        $currentVersion ??= $this->versions->currentVersion();

        foreach ($this->requiredFields as $field) {
            if (!array_key_exists($field, $feed)) {
                return $this->invalidFeed($feedUrl, $currentVersion, "Feed is missing required field: {$field}.");
            }
        }

        if ((string) ($feed['app'] ?? '') !== config('updater.app_id', 'garmentsos-pro')) {
            return $this->invalidFeed($feedUrl, $currentVersion, 'Feed is for a different app.');
        }

        $latestVersion = trim((string) ($feed['version'] ?? ''));
        if ($latestVersion === '') {
            return $this->invalidFeed($feedUrl, $currentVersion, 'Feed version is empty.');
        }

        $updateAvailable = version_compare($latestVersion, $currentVersion, '>');

        return $this->result($updateAvailable ? 'update_available' : 'up_to_date', $updateAvailable ? 'Update available.' : 'App is up to date.', [
            'success' => true,
            'feed_url' => $feedUrl,
            'feed' => $this->sanitizeFeed($feed),
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'update_available' => $updateAvailable,
        ]);
    }

    public function prepareUpdateRequest(): array
    {
        $status = $this->checkConfigured();

        if (empty($status['update_available']) || empty($status['feed'])) {
            return $this->result('update_not_available', 'No update is available to prepare.', [
                'success' => false,
                'feed_status' => $status['code'] ?? 'unknown',
                'current_version' => $status['current_version'] ?? $this->versions->currentVersion(),
            ]);
        }

        $feed = $status['feed'];

        return $this->result('update_request_prepared', 'Update request prepared for Windows launcher handoff.', [
            'success' => true,
            'request' => [
                'app' => config('updater.app_id', 'garmentsos-pro'),
                'target_version' => $feed['version'],
                'package_url' => $feed['package_url'],
                'package_sha256' => $feed['package_sha256'],
                'mandatory' => (bool) $feed['mandatory'],
                'requested_at' => now()->utc()->toIso8601String(),
                'apply_method' => 'windows-launcher-required',
                'notes' => $feed['notes'],
            ],
        ]);
    }

    protected function invalidFeed(string $feedUrl, string $currentVersion, string $message): array
    {
        return $this->result('invalid_feed', $message, [
            'success' => false,
            'feed_url' => $feedUrl,
            'current_version' => $currentVersion,
            'update_available' => false,
        ]);
    }

    protected function sanitizeFeed(array $feed): array
    {
        return [
            'app' => (string) ($feed['app'] ?? ''),
            'version' => (string) ($feed['version'] ?? ''),
            'channel' => (string) ($feed['channel'] ?? ''),
            'mandatory' => (bool) ($feed['mandatory'] ?? false),
            'released_at' => (string) ($feed['released_at'] ?? ''),
            'package_file' => (string) ($feed['package_file'] ?? ''),
            'package_sha256_file' => (string) ($feed['package_sha256_file'] ?? ''),
            'package_sha256' => (string) ($feed['package_sha256'] ?? ''),
            'package_url' => (string) ($feed['package_url'] ?? ''),
            'setup_url' => (string) ($feed['setup_url'] ?? ''),
            'min_launcher_version' => (string) ($feed['min_launcher_version'] ?? ''),
            'notes' => (string) ($feed['notes'] ?? ''),
        ];
    }

    protected function result(string $code, string $message, array $extra = []): array
    {
        return array_merge([
            'code' => $code,
            'message' => $message,
        ], $extra);
    }
}
