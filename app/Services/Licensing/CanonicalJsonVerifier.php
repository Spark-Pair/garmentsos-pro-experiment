<?php

namespace App\Services\Licensing;

class CanonicalJsonVerifier
{
    public function canonicalJson(array $payload): string
    {
        $normalized = $this->sortKeysRecursive($payload);

        return json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function verify(array $payload, string $signature): array
    {
        $publicKey = trim((string) config('licensing.public_key', ''));
        if ($publicKey === '') {
            return ['valid' => false, 'reason' => 'missing_public_key'];
        }

        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            return ['valid' => false, 'reason' => 'invalid_signature_encoding'];
        }

        $result = openssl_verify($this->canonicalJson($payload), $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($result !== 1) {
            return ['valid' => false, 'reason' => 'signature_mismatch'];
        }

        return ['valid' => true, 'reason' => 'verified'];
    }

    public function payloadHash(array $payload): string
    {
        unset($payload['payload_hash']);

        return hash('sha256', $this->canonicalJson($payload));
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
}
