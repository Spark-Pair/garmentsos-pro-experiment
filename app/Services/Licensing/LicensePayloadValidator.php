<?php

namespace App\Services\Licensing;

use Carbon\Carbon;
use Throwable;

class LicensePayloadValidator
{
    protected array $requiredFields = [
        'server_license_id',
        'license_key_hash',
        'client_name',
        'business_name',
        'installation_uuid',
        'fingerprint_hash',
        'installation_mode',
        'license_status',
        'subscription_status',
        'subscription_expires_at',
        'license_expires_at',
        'update_channel',
        'issued_at',
        'signature_version',
        'payload_hash',
    ];

    public function __construct(
        protected InstallationIdentityService $identity,
        protected InstallationFingerprintService $fingerprints,
        protected CanonicalJsonVerifier $verifier,
    ) {
    }

    public function validateDocument(array $document): array
    {
        if (!isset($document['payload'], $document['signature']) || !is_array($document['payload'])) {
            return ['valid' => false, 'reason' => 'invalid_schema'];
        }

        $signatureResult = $this->verifier->verify($document['payload'], (string) $document['signature']);
        if (!($signatureResult['valid'] ?? false)) {
            return $signatureResult;
        }

        return $this->validatePayload($document['payload']);
    }

    public function validatePayload(array $payload): array
    {
        foreach ($this->requiredFields as $field) {
            if (!array_key_exists($field, $payload)) {
                return ['valid' => false, 'reason' => 'missing_' . $field];
            }
        }

        if (!array_key_exists('cache_until', $payload) && !array_key_exists('offline_grace_until', $payload)) {
            return ['valid' => false, 'reason' => 'missing_cache_until'];
        }

        $expectedPayloadHash = $this->verifier->payloadHash($payload);
        if (!hash_equals($expectedPayloadHash, (string) $payload['payload_hash'])) {
            return ['valid' => false, 'reason' => 'payload_hash_mismatch'];
        }

        $installation = $this->identity->current();
        if (!hash_equals($installation->installation_uuid, (string) $payload['installation_uuid'])) {
            return ['valid' => false, 'reason' => 'installation_uuid_mismatch'];
        }

        if (!hash_equals($this->fingerprints->fingerprintHash(), (string) $payload['fingerprint_hash'])) {
            return ['valid' => false, 'reason' => 'fingerprint_mismatch'];
        }

        if (!in_array((string) $payload['installation_mode'], ['local_lan', 'cloud'], true)) {
            return ['valid' => false, 'reason' => 'invalid_installation_mode'];
        }

        if (!$this->validStatus((string) $payload['license_status'], ['active', 'expired', 'suspended', 'blocked'])) {
            return ['valid' => false, 'reason' => 'invalid_license_status'];
        }

        if (!$this->validStatus((string) $payload['subscription_status'], ['active', 'expired', 'grace', 'inactive'])) {
            return ['valid' => false, 'reason' => 'invalid_subscription_status'];
        }

        $dateCheck = $this->validateDates($payload);
        if (!($dateCheck['valid'] ?? false)) {
            return $dateCheck;
        }

        return [
            'valid' => true,
            'reason' => 'verified',
            'payload' => $payload,
            'payload_hash' => hash('sha256', $this->verifier->canonicalJson($payload)),
        ];
    }

    protected function validateDates(array $payload): array
    {
        try {
            $issuedAt = Carbon::parse($payload['issued_at']);
            $cacheUntil = Carbon::parse($payload['cache_until'] ?? $payload['offline_grace_until']);
        } catch (Throwable) {
            return ['valid' => false, 'reason' => 'invalid_dates'];
        }

        if ($issuedAt->greaterThan(now()->addMinutes(10))) {
            return ['valid' => false, 'reason' => 'issued_at_in_future'];
        }

        if ($cacheUntil->lessThan($issuedAt)) {
            return ['valid' => false, 'reason' => 'cache_before_issue'];
        }

        if ($cacheUntil->lessThan(now())) {
            return ['valid' => false, 'reason' => 'cache_expired'];
        }

        foreach (['subscription_expires_at', 'license_expires_at', 'offline_grace_until'] as $field) {
            if (($payload[$field] ?? null) === null) {
                continue;
            }

            try {
                Carbon::parse($payload[$field]);
            } catch (Throwable) {
                return ['valid' => false, 'reason' => 'invalid_' . $field];
            }
        }

        return ['valid' => true];
    }

    protected function validStatus(string $status, array $allowed): bool
    {
        return in_array($status, $allowed, true);
    }
}
