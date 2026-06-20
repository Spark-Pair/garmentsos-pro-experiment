<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\Http;
use Throwable;

class LicenseActivationClient
{
    public function activate(string $licenseKey, string $fingerprintHash): array
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
                ->post($serverUrl . '/api/licenses/activate', [
                    'license_key' => $licenseKey,
                    'installation_fingerprint_hash' => $fingerprintHash,
                    'app' => 'garmentsos-pro',
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
}
