<?php

namespace App\Services\Updater;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class UpdateDownloadService
{
    public function __construct(protected UpdateLogService $logs)
    {
    }

    public function download(string $url): array
    {
        $urlParts = parse_url($url);
        $scheme = $urlParts['scheme'] ?? '';
        $host = $urlParts['host'] ?? '';

        if (!in_array($scheme, config('updater.allowed_url_schemes', ['https']), true)) {
            return $this->result(false, 'invalid_scheme', 'Update package URL scheme is not allowed.');
        }

        $allowedDomains = config('updater.allowed_domains', []);
        if ($allowedDomains !== [] && !in_array($host, $allowedDomains, true)) {
            return $this->result(false, 'invalid_domain', 'Update package host is not allowed.');
        }

        try {
            $response = Http::timeout(60)->get($url);
        } catch (\Throwable) {
            $this->logs->record('download_failed', ['reason' => 'request_failed']);

            return $this->result(false, 'request_failed', 'Could not download update package.');
        }

        if (!$response->ok()) {
            $this->logs->record('download_failed', ['reason' => 'http_error', 'status' => $response->status()]);

            return $this->result(false, 'http_error', 'Update package download failed.');
        }

        $directory = storage_path('app/' . trim((string) config('updater.temp_path', 'private/updater'), '/'));
        File::ensureDirectoryExists($directory);
        $path = $directory . DIRECTORY_SEPARATOR . 'update_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(8)) . '.zip';
        File::put($path, $response->body());

        $this->logs->record('download_succeeded', [
            'filename' => basename($path),
            'size_bytes' => File::size($path),
        ]);

        return $this->result(true, 'downloaded', 'Update package downloaded to private storage.', [
            'path' => $path,
            'filename' => basename($path),
            'size_bytes' => File::size($path),
        ]);
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
