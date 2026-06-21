<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\File;
use JsonException;

class SignedLicenseFileService
{
    public function __construct(
        protected CanonicalJsonVerifier $verifier,
    ) {
    }

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

    public function decodeDocument(string $signedLicense): array
    {
        $signedLicense = trim($signedLicense);

        $decoded = base64_decode($signedLicense, true);
        if ($decoded !== false) {
            $signedLicense = $decoded;
        }

        try {
            $document = json_decode($signedLicense, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['valid' => false, 'reason' => 'invalid_json'];
        }

        if (!is_array($document)) {
            return ['valid' => false, 'reason' => 'invalid_schema'];
        }

        return ['valid' => true, 'document' => $document];
    }

    public function verifyDocument(array $document): array
    {
        if (!isset($document['payload'], $document['signature']) || !is_array($document['payload'])) {
            return ['valid' => false, 'reason' => 'invalid_schema'];
        }

        $signatureResult = $this->verifier->verify($document['payload'], (string) $document['signature']);
        if (!($signatureResult['valid'] ?? false)) {
            return $signatureResult;
        }

        $canonicalPayload = $this->canonicalJson($document['payload']);

        return [
            'valid' => true,
            'reason' => 'verified',
            'payload' => $document['payload'],
            'payload_hash' => hash('sha256', $canonicalPayload),
        ];
    }

    public function canonicalJson(array $payload): string
    {
        return $this->verifier->canonicalJson($payload);
    }

    protected function path(): string
    {
        return (string) config('licensing.cache_path');
    }
}
