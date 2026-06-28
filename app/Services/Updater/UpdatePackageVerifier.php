<?php

namespace App\Services\Updater;

use Illuminate\Support\Facades\File;

class UpdatePackageVerifier
{
    protected array $forbiddenExact = [
        '.env',
        'database/database.sqlite',
        'public/hot',
    ];

    protected array $forbiddenPrefixes = [
        '.git/',
        '.github/',
        'storage/logs/',
        'storage/app/private/',
        'storage/app/backups/',
        'storage/app/private/backups/',
        'logs/',
        'backups/',
        'backup/',
    ];

    protected array $forbiddenContains = [
        'token',
        'credential',
        'private_key',
        'id_rsa',
        'github',
    ];

    public function __construct(protected UpdateLogService $logs)
    {
    }

    public function verify(string $path, ?string $expectedChecksum = null, ?string $signature = null): array
    {
        if (!File::exists($path) || !File::isFile($path)) {
            return $this->result(false, 'missing', 'Update package was not found.');
        }

        $checksum = hash_file('sha256', $path);
        if ($expectedChecksum && !hash_equals($expectedChecksum, $checksum)) {
            $this->logs->record('package_verify_failed', ['reason' => 'checksum_mismatch']);

            return $this->result(false, 'checksum_mismatch', 'Update package checksum does not match.', ['checksum' => $checksum]);
        }

        $entries = $this->zipEntries($path);
        if ($entries === []) {
            return $this->result(false, 'zip_unreadable', 'Update package ZIP could not be inspected.', ['checksum' => $checksum]);
        }

        foreach ($entries as $entry) {
            $reason = $this->forbiddenReason($entry);
            if ($reason !== null) {
                $this->logs->record('package_verify_failed', ['reason' => $reason, 'entry' => $entry]);

                return $this->result(false, $reason, 'Update package contains a forbidden or unsafe file.', [
                    'checksum' => $checksum,
                    'entry' => $entry,
                ]);
            }
        }

        if ((bool) config('updater.require_signature', true) && !$signature) {
            $this->logs->record('package_verify_failed', ['reason' => 'missing_signature']);

            return $this->result(false, 'missing_signature', 'Update package signature is required.', ['checksum' => $checksum]);
        }

        if ($signature && !$this->verifySignature($checksum, $signature)) {
            $this->logs->record('package_verify_failed', ['reason' => 'signature_mismatch']);

            return $this->result(false, 'signature_mismatch', 'Update package signature is invalid.', ['checksum' => $checksum]);
        }

        $this->logs->record('package_verify_succeeded', [
            'checksum' => $checksum,
            'entry_count' => count($entries),
        ]);

        return $this->result(true, 'valid', 'Update package verified.', [
            'checksum' => $checksum,
            'entries' => $entries,
        ]);
    }

    public function forbiddenReason(string $entry): ?string
    {
        $normalized = str_replace('\\', '/', ltrim($entry, '/'));
        $lower = strtolower($normalized);

        if ($entry !== $normalized || str_starts_with($entry, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $entry) || preg_match('/^[A-Za-z]:\\//', $normalized)) {
            return 'absolute_path';
        }

        if (str_contains($normalized, '../') || str_starts_with($normalized, '../') || $normalized === '..') {
            return 'path_traversal';
        }

        $basename = basename($lower);
        if (in_array($lower, $this->forbiddenExact, true) || $basename === '.env' || str_starts_with($basename, '.env.')) {
            return 'forbidden_file';
        }

        if (
            str_ends_with($lower, '.sqlite-wal')
            || str_ends_with($lower, '.sqlite-shm')
            || str_ends_with($lower, '.sql')
            || str_ends_with($lower, '.dump')
            || str_ends_with($lower, '.bak')
            || str_ends_with($lower, '.pem')
            || str_ends_with($lower, '.key')
            || str_ends_with($lower, '.pfx')
            || str_ends_with($lower, '.crt')
        ) {
            return 'forbidden_file';
        }

        foreach ($this->forbiddenPrefixes as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return 'forbidden_directory';
            }
        }

        foreach ($this->forbiddenContains as $needle) {
            if (str_contains($lower, $needle)) {
                return 'forbidden_sensitive_name';
            }
        }

        if (str_contains($lower, 'backup') && (str_ends_with($lower, '.zip') || str_ends_with($lower, '.sqlite') || str_ends_with($lower, '.sql'))) {
            return 'forbidden_backup';
        }

        return null;
    }

    protected function verifySignature(string $checksum, string $signature): bool
    {
        $publicKey = (string) config('updater.public_key', '');
        if ($publicKey === '') {
            return !(bool) config('updater.require_signature', true);
        }

        return openssl_verify($checksum, base64_decode($signature, true) ?: '', $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    protected function zipEntries(string $path): array
    {
        $data = File::get($path);
        $eocd = strrpos($data, "PK\x05\x06");
        if ($eocd === false || strlen($data) < $eocd + 22) {
            return [];
        }

        $directorySize = unpack('V', substr($data, $eocd + 12, 4))[1] ?? 0;
        $directoryOffset = unpack('V', substr($data, $eocd + 16, 4))[1] ?? 0;
        $directory = substr($data, $directoryOffset, $directorySize);
        $offset = 0;
        $entries = [];

        while ($offset + 46 <= strlen($directory) && substr($directory, $offset, 4) === "PK\x01\x02") {
            $nameLength = unpack('v', substr($directory, $offset + 28, 2))[1] ?? 0;
            $extraLength = unpack('v', substr($directory, $offset + 30, 2))[1] ?? 0;
            $commentLength = unpack('v', substr($directory, $offset + 32, 2))[1] ?? 0;
            $name = substr($directory, $offset + 46, $nameLength);
            if ($name !== '') {
                $entries[] = $name;
            }
            $offset += 46 + $nameLength + $extraLength + $commentLength;
        }

        return $entries;
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
