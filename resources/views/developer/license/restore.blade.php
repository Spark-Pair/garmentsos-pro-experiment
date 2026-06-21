@extends('app')

@section('title', 'Restore Backup | ' . $client_company->name)

@section('content')
    <div class="w-full max-w-3xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Restore Backup</h1>
            <p class="text-[var(--secondary-text)] mt-1">Restore is guarded and disabled unless explicitly enabled by configuration.</p>
        </div>

        @if (session('success'))
            <div class="bg-green-900/40 border border-green-700 rounded-lg p-3 text-green-100">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-900/40 border border-red-700 rounded-lg p-3 text-red-100">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-red-950/40 border border-red-700 rounded-lg p-5">
            <h2 class="text-lg font-semibold text-red-100">Danger Zone</h2>
            <p class="text-red-100/90 mt-2">
                Restoring replaces the current SQLite database after creating and verifying an emergency backup.
                Test the backup on a staging/copy database before enabling restore.
            </p>
        </div>

        <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg p-5 space-y-3">
            <h2 class="text-lg font-semibold">Selected Backup</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div>
                    <div class="text-[var(--secondary-text)]">Backup ID</div>
                    <div>{{ $backupLog->id }}</div>
                </div>
                <div>
                    <div class="text-[var(--secondary-text)]">Created</div>
                    <div>{{ $backupLog->started_at?->format('Y-m-d H:i') ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-[var(--secondary-text)]">File</div>
                    <div>{{ $backupLog->filename ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-[var(--secondary-text)]">Size</div>
                    <div>{{ $backupLog->size_bytes ? number_format($backupLog->size_bytes / 1024, 1) . ' KB' : '-' }}</div>
                </div>
                <div class="md:col-span-2">
                    <div class="text-[var(--secondary-text)]">Checksum</div>
                    <div class="font-mono text-xs break-all">{{ $backupLog->checksum ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg p-5 space-y-2">
            <h2 class="text-lg font-semibold">Verification</h2>
            <p class="{{ $inspection['valid'] ? 'text-green-300' : 'text-red-300' }}">
                {{ $inspection['message'] }}
            </p>
            <p class="text-[var(--secondary-text)] text-sm">
                Code: {{ $inspection['verification']['code'] ?? 'unknown' }}
            </p>
        </div>

        <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg p-5">
            <h2 class="text-lg font-semibold mb-3">Restore Requirements</h2>
            <ul class="space-y-2 text-[var(--secondary-text)]">
                <li>Restore enabled: {{ $requirements['restore_enabled'] ? 'Yes' : 'No' }}</li>
                <li>Typed confirmation required: Yes</li>
                <li>Staging/copy tested checkbox required: {{ $requirements['staging_tested_required'] ? 'Yes' : 'No' }}</li>
                <li>Emergency backup before restore: Yes</li>
                <li>Public backups allowed: No</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('developer.backups.restore.store', $backupLog) }}"
            class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg p-5 space-y-4">
            @csrf

            <div>
                <label class="block text-sm text-[var(--secondary-text)] mb-1">Type this phrase exactly</label>
                <div class="font-mono text-sm bg-[var(--h-bg-color)] rounded p-2 mb-2">{{ $confirmationPhrase }}</div>
                <input type="text" name="confirmation_phrase"
                    class="w-full rounded bg-[var(--h-bg-color)] border border-gray-700 px-3 py-2"
                    autocomplete="off">
            </div>

            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="staging_tested" value="1" class="mt-1">
                <span>I tested this restore on a staging/copy database and confirmed the backup is correct.</span>
            </label>

            @if (!$requirements['restore_enabled'])
                <div class="bg-yellow-900/40 border border-yellow-700 rounded-lg p-3 text-yellow-100">
                    Restore is disabled by configuration. Set BACKUP_RESTORE_ENABLED=true only after staging verification.
                </div>
            @endif

            <div class="flex items-center gap-3">
                <button type="submit"
                    class="px-4 py-2 bg-red-800 text-white rounded-lg hover:opacity-90">
                    Restore Backup
                </button>
                <a href="{{ route('developer.backups') }}"
                    class="px-4 py-2 bg-[var(--h-bg-color)] rounded-lg hover:opacity-90">
                    Back
                </a>
            </div>
        </form>
    </div>
@endsection
