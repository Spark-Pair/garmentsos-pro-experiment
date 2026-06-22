<?php

namespace App\Services\Updater;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class UpdateManifestService
{
    protected array $requiredFields = [
        'app',
        'latest_version',
        'minimum_required_version',
        'update_channel',
        'mandatory',
        'release_notes',
        'package_url',
        'package_checksum',
        'created_at',
        'expires_at',
        'supported_installation_modes',
    ];

    public function __construct(protected UpdateLogService $logs)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('updater.enabled', false);
    }

    public function checkConfigured(): array
    {
        if (!$this->enabled()) {
            return $this->result(false, 'disabled', 'Updater is disabled by configuration.');
        }

        $url = (string) config('updater.manifest_url', '');
        if ($url === '') {
            return $this->result(false, 'missing_manifest_url', 'No update manifest URL is configured.');
        }

        try {
            $response = Http::timeout(10)->acceptJson()->get($url);
        } catch (\Throwable) {
            $this->logs->record('check_failed', ['reason' => 'manifest_request_failed']);

            return $this->result(false, 'request_failed', 'Could not request update manifest.');
        }

        if (!$response->ok()) {
            $this->logs->record('check_failed', ['reason' => 'manifest_http_error', 'status' => $response->status()]);

            return $this->result(false, 'http_error', 'Update manifest request failed.');
        }

        return $this->validateManifest($response->json() ?? []);
    }

    public function validateManifest(array $manifest): array
    {
        foreach ($this->requiredFields as $field) {
            if (!array_key_exists($field, $manifest)) {
                return $this->result(false, 'missing_field', 'Update manifest is missing required fields.', ['field' => $field]);
            }
        }

        if (($manifest['app'] ?? null) !== config('updater.app_id', 'garmentsos-pro')) {
            return $this->result(false, 'wrong_app', 'Update manifest is for a different app.');
        }

        if (($manifest['update_channel'] ?? null) !== config('updater.channel', 'stable')) {
            return $this->result(false, 'wrong_channel', 'Update manifest is for a different update channel.');
        }

        $modes = Arr::wrap($manifest['supported_installation_modes'] ?? []);
        if (!in_array(config('updater.installation_mode', 'local_lan'), $modes, true)) {
            return $this->result(false, 'unsupported_installation_mode', 'Update manifest does not support this installation mode.');
        }

        if (Carbon::parse($manifest['expires_at'])->isPast()) {
            return $this->result(false, 'expired', 'Update manifest has expired.');
        }

        $signature = (string) ($manifest['signature'] ?? '');
        if ((bool) config('updater.require_signature', true) && !$this->verifySignature($manifest, $signature)) {
            return $this->result(false, 'invalid_signature', 'Update manifest signature is invalid.');
        }

        $current = (string) config('updater.current_version', '0.0.0');
        $latest = (string) $manifest['latest_version'];
        $available = version_compare($latest, $current, '>');
        $mandatory = (bool) $manifest['mandatory'] || version_compare($current, (string) $manifest['minimum_required_version'], '<');

        $this->logs->record('check_succeeded', [
            'current_version' => $current,
            'latest_version' => $latest,
            'update_available' => $available,
            'mandatory' => $mandatory,
            'channel' => $manifest['update_channel'],
        ]);

        return $this->result(true, $available ? 'update_available' : 'up_to_date', $available ? 'Update is available.' : 'App is up to date.', [
            'manifest' => $manifest,
            'current_version' => $current,
            'latest_version' => $latest,
            'update_available' => $available,
            'mandatory' => $mandatory,
        ]);
    }

    public function canonicalJson(array $payload): string
    {
        unset($payload['signature']);
        ksort($payload);

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sortRecursive($value);
            }
        }

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function sortRecursive(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(fn ($item) => is_array($item) ? $this->sortRecursive($item) : $item, $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }

        return $value;
    }

    protected function verifySignature(array $manifest, string $signature): bool
    {
        $publicKey = (string) config('updater.public_key', '');
        if ($publicKey === '' || $signature === '') {
            return false;
        }

        return openssl_verify($this->canonicalJson($manifest), base64_decode($signature, true) ?: '', $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    protected function result(bool $success, string $code, string $message, array $extra = []): array
    {
        return array_merge([
            'success' => $success,
            'code' => $code,
            'message' => $message,
        ], $extra);
    }
}
