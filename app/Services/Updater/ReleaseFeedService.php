<?php

namespace App\Services\Updater;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ReleaseFeedService
{
    protected array $requiredFields = [
        'version',
        'package_sha256',
        'package_url',
    ];

    public function __construct(protected InstalledVersionService $versions)
    {
    }

    public function checkConfigured(): array
    {
        $feedUrl = trim((string) config('updater.feed_url', ''));
        $currentVersion = $this->versions->currentVersion();
        $fallbackFeedUrl = trim((string) config('updater.fallback_feed_url', ''));

        if ($feedUrl === '') {
            if ($fallbackFeedUrl !== '') {
                return $this->checkUrlWithFallbackContext($fallbackFeedUrl, $currentVersion, null);
            }

            return $this->result('feed_not_configured', 'Update feed is not configured.', [
                'success' => false,
                'feed_url' => '',
                'current_version' => $currentVersion,
                'update_available' => false,
            ]);
        }

        $primary = $this->checkUrl($feedUrl, $currentVersion);
        if (!empty($primary['success'])) {
            return $primary;
        }

        if ($fallbackFeedUrl !== '' && !hash_equals($feedUrl, $fallbackFeedUrl)) {
            return $this->checkUrlWithFallbackContext($fallbackFeedUrl, $currentVersion, $primary);
        }

        return $primary;
    }

    protected function checkUrlWithFallbackContext(string $feedUrl, string $currentVersion, ?array $primaryFailure): array
    {
        $fallback = $this->checkUrl($feedUrl, $currentVersion);

        if (!empty($fallback['success'])) {
            return array_merge($fallback, [
                'code' => !empty($fallback['update_available']) ? 'update_available' : 'up_to_date',
                'message' => $primaryFailure
                    ? 'Primary feed failed, using fallback feed.'
                    : ($fallback['message'] ?? 'Fallback feed used.'),
                'fallback_used' => true,
                'fallback_feed_url' => $feedUrl,
                'primary_feed_failed' => $primaryFailure,
            ]);
        }

        if ($primaryFailure) {
            return array_merge($primaryFailure, [
                'fallback_used' => false,
                'fallback_feed_url' => $feedUrl,
                'fallback_feed_failed' => $fallback,
            ]);
        }

        return $fallback;
    }

    protected function checkUrl(string $feedUrl, string $currentVersion): array
    {
        try {
            $response = Http::timeout((int) config('updater.feed_timeout', 8))
                ->acceptJson()
                ->get($feedUrl);
        } catch (\Throwable $exception) {
            $diagnostics = $this->curlDiagnostics();
            $isMissingCertificateBundle = $this->isMissingCertificateBundleError($exception);

            return $this->result('feed_unreachable', $isMissingCertificateBundle
                ? 'HTTPS certificate bundle is missing. Please configure PHP curl.cainfo.'
                : 'Update feed could not be reached.', [
                'success' => false,
                'feed_url' => $feedUrl,
                'current_version' => $currentVersion,
                'error' => $exception->getMessage(),
                'diagnostic_code' => $isMissingCertificateBundle ? 'curl_ca_missing' : 'network_error',
                'diagnostics' => $diagnostics,
                'update_available' => false,
            ]);
        }

        if (!$response->ok()) {
            $status = $response->status();
            $message = $status === 404
                ? 'Feed not reachable. If the GitHub repo is private, use a public update feed URL or SparkPair update server.'
                : 'Update feed returned an HTTP error.';

            return $this->result('feed_unreachable', $message, [
                'success' => false,
                'feed_url' => $feedUrl,
                'current_version' => $currentVersion,
                'http_status' => $status,
                'update_available' => false,
            ]);
        }

        $feed = $response->json();
        if (!is_array($feed)) {
            return $this->invalidFeed($feedUrl, $currentVersion, 'Feed response is not valid JSON.');
        }

        return $this->validateFeed($feed, $feedUrl, $currentVersion);
    }

    public function checkConfiguredCached(int $seconds = 900): array
    {
        if ($seconds <= 0) {
            return $this->checkConfigured();
        }

        $feedUrl = trim((string) config('updater.feed_url', ''));
        $currentVersion = $this->versions->currentVersion();
        $key = 'updater.release_feed.' . sha1($feedUrl . '|' . $currentVersion);

        return Cache::remember($key, $seconds, fn () => $this->checkConfigured());
    }

    public function validateFeed(array $feed, string $feedUrl = '', ?string $currentVersion = null): array
    {
        $currentVersion ??= $this->versions->currentVersion();

        foreach ($this->requiredFields as $field) {
            if (!array_key_exists($field, $feed)) {
                return $this->invalidFeed($feedUrl, $currentVersion, "Feed is missing required field: {$field}.");
            }
        }

        $app = trim((string) ($feed['app'] ?? ''));
        if ($app !== '' && $app !== config('updater.app_id', 'garmentsos-pro')) {
            return $this->invalidFeed($feedUrl, $currentVersion, 'Feed is for a different app.');
        }

        $latestVersion = trim((string) ($feed['version'] ?? ''));
        if ($latestVersion === '') {
            return $this->invalidFeed($feedUrl, $currentVersion, 'Feed version is empty.');
        }

        if (trim((string) ($feed['package_url'] ?? '')) === '') {
            return $this->invalidFeed($feedUrl, $currentVersion, 'Feed package_url is empty.');
        }

        if (trim((string) ($feed['package_sha256'] ?? '')) === '') {
            return $this->invalidFeed($feedUrl, $currentVersion, 'Feed package_sha256 is empty.');
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

    public function prepareUpdateRequest(?array $status = null, ?string $requestId = null): array
    {
        $status ??= $this->checkConfigured();
        $requestId = $requestId ?: (string) Str::uuid();

        if (empty($status['update_available']) || empty($status['feed'])) {
            return $this->result('update_not_available', 'No update is available to prepare.', [
                'success' => false,
                'feed_status' => $status['code'] ?? 'unknown',
                'current_version' => $status['current_version'] ?? $this->versions->currentVersion(),
            ]);
        }

        $feed = $status['feed'];
        $expiresAt = now()->addMinutes(max(1, (int) config('updater.update_request_ttl_minutes', 10)));

        return $this->result('update_request_prepared', 'Update request prepared for Windows launcher handoff.', [
            'success' => true,
            'request' => [
                'app' => config('updater.app_id', 'garmentsos-pro'),
                'current_version' => $status['current_version'] ?? $this->versions->currentVersion(),
                'target_version' => $feed['version'],
                'channel' => $feed['channel'],
                'package_file' => $feed['package_file'],
                'package_url' => $feed['package_url'],
                'package_sha256' => $feed['package_sha256'],
                'setup_url' => $this->validSetupUrl($feed['setup_url']) ? $feed['setup_url'] : null,
                'mandatory' => (bool) $feed['mandatory'],
                'notes' => $feed['notes'],
                'request_id' => $requestId,
                'requested_at' => now()->utc()->toIso8601String(),
                'apply_method' => 'windows-launcher-required',
                'launcher_protocol_url' => $this->launcherProtocolUrl(),
                'update_lock_failed_url' => URL::temporarySignedRoute('developer.updater.update-lock-failed.signed', $expiresAt, [
                    'request_id' => $requestId,
                ]),
            ],
        ]);
    }

    public function launcherHandoff(?array $status = null): array
    {
        $status ??= $this->checkConfigured();

        if (empty($status['update_available']) || empty($status['feed'])) {
            return $this->result('update_not_available', 'No update is available for launcher handoff.', [
                'success' => false,
                'feed_status' => $status['code'] ?? 'unknown',
            ]);
        }

        $expiresAt = now()->addMinutes(max(1, (int) config('updater.update_request_ttl_minutes', 10)));
        $requestId = (string) Str::uuid();
        $signedUrl = URL::temporarySignedRoute('developer.updater.update-request.signed', $expiresAt, [
            'request_id' => $requestId,
        ]);
        $protocolUrl = $this->launcherProtocolUrl();

        return $this->result('launcher_handoff_prepared', 'Launcher handoff URL prepared.', [
            'success' => true,
            'protocol_url' => $protocolUrl ? $protocolUrl . '?request=' . rawurlencode($signedUrl) . '&autoStart=1&requestId=' . rawurlencode($requestId) : null,
            'signed_request_url' => $signedUrl,
            'request_id' => $requestId,
            'expires_at' => $expiresAt->utc()->toIso8601String(),
        ]);
    }

    public function validSetupUrl(?string $url): bool
    {
        $url = trim((string) $url);

        return $url !== ''
            && !str_starts_with($url, 'PLACEHOLDER_')
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function launcherProtocolUrl(): ?string
    {
        $protocol = trim((string) config('updater.launcher_protocol', ''));
        if ($protocol === '') {
            return null;
        }

        return rtrim($protocol, ':\\/') . '://update';
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

    public function curlDiagnostics(): array
    {
        $curlCaInfo = (string) ini_get('curl.cainfo');
        $opensslCaFile = (string) ini_get('openssl.cafile');
        $candidate = $curlCaInfo !== '' ? $curlCaInfo : $opensslCaFile;

        return [
            'php_curl_available' => extension_loaded('curl'),
            'curl_cainfo' => $curlCaInfo,
            'openssl_cafile' => $opensslCaFile,
            'certificate_file_exists' => $candidate !== '' && is_file($candidate),
        ];
    }

    protected function isMissingCertificateBundleError(\Throwable $exception): bool
    {
        for ($current = $exception; $current; $current = $current->getPrevious()) {
            $message = $current->getMessage();
            if (str_contains($message, 'cURL error 77')
                || str_contains($message, 'error setting certificate file')
                || str_contains($message, 'cacert.pem')
            ) {
                return true;
            }
        }

        return false;
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
