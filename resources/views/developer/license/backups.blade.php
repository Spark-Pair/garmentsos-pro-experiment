@extends('app')

@section('title', 'Backup & Restore | ' . $client_company->name)

@section('content')
    <div class="w-full max-w-5xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Backup & Restore</h1>
            <p class="text-[var(--secondary-text)] mt-1">Private, verified database backups for developer/admin use.</p>
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

        <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg p-5 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Create Backup</h2>
                <p class="text-[var(--secondary-text)] mt-1">Creates a private SQLite backup, checksum, metadata file, backup log, and audit log.</p>
            </div>
            <form method="POST" action="{{ route('developer.backups.store') }}">
                @csrf
                <button type="submit"
                    class="px-4 py-2 bg-[var(--secondary-color)] text-white rounded-lg hover:opacity-90">
                    Create Backup
                </button>
            </form>
        </div>

        <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg p-5">
            <h2 class="text-lg font-semibold mb-3">Restore Status</h2>
            <ul class="space-y-2 text-[var(--secondary-text)]">
                <li>Restore enabled: {{ $restoreRequirements['restore_enabled'] ? 'Yes' : 'No' }}</li>
                <li>Confirmation required: {{ $restoreRequirements['confirmation_required'] ? 'Yes' : 'No' }}</li>
                <li>Staging/copy test confirmation required: {{ $restoreRequirements['staging_tested_required'] ? 'Yes' : 'No' }}</li>
                <li>Emergency backup before restore: {{ $restoreRequirements['emergency_backup_before_restore'] ? 'Yes' : 'No' }}</li>
                <li>Public backups allowed: {{ $restoreRequirements['public_backups_allowed'] ? 'Yes' : 'No' }}</li>
            </ul>
        </div>

        <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-[var(--h-bg-color)]">
                    <tr>
                        <th class="text-left p-3">Started</th>
                        <th class="text-left p-3">Action</th>
                        <th class="text-left p-3">Status</th>
                        <th class="text-left p-3">File</th>
                        <th class="text-left p-3">Size</th>
                        <th class="text-left p-3">Checksum</th>
                        <th class="text-left p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-t border-gray-700">
                            <td class="p-3">{{ $log->started_at?->format('Y-m-d H:i') }}</td>
                            <td class="p-3">{{ $log->action }}</td>
                            <td class="p-3">{{ $log->status }}</td>
                            <td class="p-3">{{ $log->filename ?? '-' }}</td>
                            <td class="p-3">{{ $log->size_bytes ? number_format($log->size_bytes / 1024, 1) . ' KB' : '-' }}</td>
                            <td class="p-3 font-mono text-xs">{{ $log->checksum ? substr($log->checksum, 0, 16) . '...' : '-' }}</td>
                            <td class="p-3">
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('developer.backups.verify', $log) }}">
                                        @csrf
                                        <button type="submit"
                                            class="px-3 py-1 bg-[var(--h-bg-color)] rounded hover:opacity-90">
                                            Verify
                                        </button>
                                    </form>
                                    <a href="{{ route('developer.backups.download', $log) }}"
                                        class="px-3 py-1 bg-[var(--secondary-color)] text-white rounded hover:opacity-90">
                                        Download
                                    </a>
                                    <a href="{{ route('developer.backups.restore.show', $log) }}"
                                        class="px-3 py-1 bg-red-900/70 text-white rounded hover:opacity-90">
                                        Restore
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-3 text-[var(--secondary-text)]">No verified backup files yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
