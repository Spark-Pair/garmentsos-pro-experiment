<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\Http;
use Throwable;

class LicenseActivationClient
{
    public function verify(array $payload): array
    {
        $verifyUrl = trim((string) config('licensing.server_url', ''));

        if ($verifyUrl === '') {
            return [
                'ok' => false,
                'message' => 'License verify URL is not configured.',
            ];
        }

        try {
            $response = Http::timeout((int) config('licensing.request_timeout_seconds', 10))
                ->acceptJson()
                ->post($verifyUrl, $payload);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'License server is not reachable.',
                'error' => $e->getMessage(),
            ];
        }

        if (!$response->successful()) {
            return [
                'ok' => false,
                'message' => $response->json('message') ?: 'License verification failed.',
                'status' => $response->status(),
            ];
        }

        $body = $response->json();

        return [
            'ok' => is_array($body),
            'message' => is_array($body) ? ($body['message'] ?? 'License verification completed.') : 'License verification response was invalid.',
            'body' => is_array($body) ? $body : null,
        ];
    }

    public function activate(string $licenseKey, array $installationContext): array
    {
        $serverUrl = rtrim((string) config('licensing.server_url', ''), '/');

        if ($serverUrl === '') {
            return [
                'ok' => false,
                'message' => 'License server URL is not configured.',
            ];
        }

        try {
            $response = Http::timeout((int) config('licensing.request_timeout_seconds', 10))
                ->acceptJson()
                ->post($this->endpoint('/api/licenses/activate'), [
                    'license_key' => $licenseKey,
                    'installation_uuid' => $installationContext['installation_uuid'],
                    'fingerprint_hash' => $installationContext['fingerprint_hash'],
                    'installation_mode' => $installationContext['installation_mode'],
                    'app' => 'garmentsos-pro',
                    'app_version' => config('app.version', 'local'),
                ]);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'License server is not reachable.',
                'error' => $e->getMessage(),
            ];
        }

        if (!$response->successful()) {
            return [
                'ok' => false,
                'message' => $response->json('message') ?: 'License activation failed.',
                'status' => $response->status(),
            ];
        }

        return [
            'ok' => true,
            'payload' => $response->json('payload'),
            'signature' => $response->json('signature'),
        ];
    }

    public function refresh(array $licenseContext): array
    {
        $serverUrl = rtrim((string) config('licensing.server_url', ''), '/');

        if ($serverUrl === '') {
            return [
                'ok' => false,
                'message' => 'License server URL is not configured.',
            ];
        }

        try {
            $response = Http::timeout((int) config('licensing.request_timeout_seconds', 10))
                ->acceptJson()
                ->post($this->endpoint('/api/licenses/refresh'), $licenseContext);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'License server is not reachable.',
                'error' => $e->getMessage(),
            ];
        }

        if (!$response->successful()) {
            return [
                'ok' => false,
                'message' => $response->json('message') ?: 'License refresh failed.',
                'status' => $response->status(),
            ];
        }

        return [
            'ok' => true,
            'payload' => $response->json('payload'),
            'signature' => $response->json('signature'),
        ];
    }

    protected function endpoint(string $path): string
    {
        $serverUrl = rtrim((string) config('licensing.server_url', ''), '/');
        if (str_ends_with($serverUrl, '/api/licenses/verify')) {
            return substr($serverUrl, 0, -strlen('/api/licenses/verify')) . $path;
        }

        return $serverUrl . $path;
    }
}
