<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\File;
use JsonException;

class SignedLicenseFileService
{
    public function read(): array
    {
        $path = $this->path();

        if (!File::exists($path)) {
            return ['valid' => false, 'reason' => 'missing'];
        }

        try {
            $document = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['valid' => false, 'reason' => 'invalid_json'];
        }

        return $this->verifyDocument($document);
    }

    public function write(array $payload, string $signature): void
    {
        $path = $this->path();
        File::ensureDirectoryExists(dirname($path));

        File::put($path, json_encode([
            'payload' => $payload,
            'signature' => $signature,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function verifyDocument(array $document): array
    {
        if (!isset($document['payload'], $document['signature']) || !is_array($document['payload'])) {
            return ['valid' => false, 'reason' => 'invalid_schema'];
        }

        $publicKey = trim((string) config('licensing.public_key', ''));
        if ($publicKey === '') {
            return ['valid' => false, 'reason' => 'missing_public_key'];
        }

        $signature = base64_decode((string) $document['signature'], true);
        if ($signature === false) {
            return ['valid' => false, 'reason' => 'invalid_signature_encoding'];
        }

        $canonicalPayload = $this->canonicalJson($document['payload']);
        $result = openssl_verify($canonicalPayload, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($result !== 1) {
            return ['valid' => false, 'reason' => 'signature_mismatch'];
        }

        return [
            'valid' => true,
            'reason' => 'verified',
            'payload' => $document['payload'],
            'payload_hash' => hash('sha256', $canonicalPayload),
        ];
    }

    public function canonicalJson(array $payload): string
    {
        $normalized = $this->sortKeysRecursive($payload);

        return json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function sortKeysRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeysRecursive($item);
            }
        }

        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    protected function path(): string
    {
        return (string) config('licensing.cache_path');
    }
}
