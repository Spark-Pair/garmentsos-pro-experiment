<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class BackupVerifier
{
    public function __construct(protected BackupStorageService $storage)
    {
    }

    public function verify(string $path, ?string $expectedChecksum = null): array
    {
        try {
            $this->storage->assertInsideBasePath($path);

            if (!File::exists($path) || !File::isFile($path)) {
                return $this->result(false, 'missing', 'Backup file was not found.');
            }

            if (File::size($path) < 100) {
                return $this->result(false, 'too_small', 'Backup file is too small to be a valid SQLite database.');
            }

            $handle = fopen($path, 'rb');
            $header = $handle ? fread($handle, 16) : false;
            if ($handle) {
                fclose($handle);
            }

            if ($header !== "SQLite format 3\0") {
                return $this->result(false, 'invalid_header', 'Backup file is not a valid SQLite database.');
            }

            $checksum = hash_file((string) config('backup.checksum_algorithm', 'sha256'), $path);
            if ($expectedChecksum && !hash_equals($expectedChecksum, $checksum)) {
                return $this->result(false, 'checksum_mismatch', 'Backup checksum does not match the recorded value.', $checksum);
            }

            $metadataResult = $this->verifyMetadata($path, $checksum);
            if (!$metadataResult['valid']) {
                return $metadataResult;
            }

            return $this->result(true, 'valid', 'Backup verified successfully.', $checksum);
        } catch (RuntimeException $e) {
            return $this->result(false, 'unsafe_path', $e->getMessage());
        }
    }

    protected function verifyMetadata(string $path, string $checksum): array
    {
        if (!config('backup.metadata_enabled', true)) {
            return $this->result(true, 'metadata_disabled', 'Metadata verification is disabled.', $checksum);
        }

        $metadataPath = $this->storage->metadataPathFor($path);
        if (!File::exists($metadataPath)) {
            return $this->result(false, 'metadata_missing', 'Backup metadata file is missing.', $checksum);
        }

        $metadata = json_decode(File::get($metadataPath), true);
        if (!is_array($metadata)) {
            return $this->result(false, 'metadata_invalid', 'Backup metadata file is invalid.', $checksum);
        }

        if (($metadata['checksum'] ?? null) !== $checksum) {
            return $this->result(false, 'metadata_checksum_mismatch', 'Backup metadata checksum does not match the file.', $checksum);
        }

        return $this->result(true, 'metadata_valid', 'Backup metadata verified.', $checksum);
    }

    protected function result(bool $valid, string $code, string $message, ?string $checksum = null): array
    {
        return [
            'valid' => $valid,
            'code' => $code,
            'message' => $message,
            'checksum' => $checksum,
        ];
    }
}
