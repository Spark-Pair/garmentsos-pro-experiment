@extends('app')

@section('title', 'Backup & Restore | ' . $client_company->name)

@section('content')
    @php
        $panel = 'bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow-sm';
        $badge = 'inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-semibold';
        $primaryButton = 'inline-flex items-center justify-center rounded-lg bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-in-out hover:bg-[var(--h-primary-color)] hover:scale-[0.98]';
        $secondaryButton = 'inline-flex items-center justify-center rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--h-bg-color)] px-3 py-2 text-sm text-[var(--text-color)] transition-all duration-300 ease-in-out hover:bg-[var(--secondary-bg-color)] hover:scale-[0.98]';
        $disabledButton = 'inline-flex items-center justify-center rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--h-bg-color)] px-4 py-2 text-sm text-[var(--secondary-text)] opacity-60 cursor-not-allowed';
        $restoreEnabled = (bool) ($restoreRequirements['restore_enabled'] ?? false);
        $foundationReady = $foundationReady ?? true;
        $missingTables = $missingTables ?? [];
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Backup & Restore" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="Backup & Restore" />

            <div class="h-full overflow-y-auto my-scrollbar-2 px-4 pb-5 pt-12 text-left text-[var(--text-color)]">
                <div class="mb-4 flex flex-col gap-3 rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-4 md:flex-row md:items-start md:justify-between">
                    <p class="max-w-3xl text-sm text-[var(--secondary-text)]">
                        Private, verified SQLite backups for developer/admin use. Backup files stay in private storage and downloads are permission protected.
                    </p>
                    <form method="POST" action="{{ route('developer.backups.store') }}">
                        @csrf
                        <button type="submit" class="{{ $foundationReady ? $primaryButton : $disabledButton }}" @disabled(!$foundationReady)>
                            <i class="fas fa-database mr-2"></i>Create Backup
                        </button>
                    </form>
                </div>

                <div class="space-y-4">

        @if (session('success'))
            <div class="rounded-lg border border-[var(--border-success)] bg-[var(--bg-success)] px-4 py-3 text-sm text-[var(--text-success)]">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-lg border border-[var(--border-error)] bg-[var(--bg-error)] px-4 py-3 text-sm text-[var(--text-error)]">
                {{ session('error') }}
            </div>
        @endif

        @if (!$foundationReady)
            <section class="rounded-xl border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
                <div class="font-semibold">Backup setup is pending</div>
                <p class="mt-1">
                    Backup foundation tables are not available in this local database yet. Run migrations only on a verified staging/client-copy database before creating backups.
                </p>
                @if ($missingTables)
                    <p class="mt-2 font-mono text-xs">Missing: {{ implode(', ', $missingTables) }}</p>
                @endif
            </section>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <section class="{{ $panel }} p-5 xl:col-span-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Backup Workflow</h2>
                        <p class="mt-1 text-sm text-[var(--secondary-text)]">
                            A backup creates a standalone SQLite copy, checksum, metadata, backup log, and audit log.
                        </p>
                    </div>
                    <span class="{{ $badge }} border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]">Private storage</span>
                </div>
                <div class="mt-4 grid gap-3 text-sm md:grid-cols-3">
                    <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                        <div class="font-semibold">Create</div>
                        <div class="mt-1 text-[var(--secondary-text)]">Managed backup only.</div>
                    </div>
                    <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                        <div class="font-semibold">Verify</div>
                        <div class="mt-1 text-[var(--secondary-text)]">SQLite validity and checksum.</div>
                    </div>
                    <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                        <div class="font-semibold">Download</div>
                        <div class="mt-1 text-[var(--secondary-text)]">Developer/admin only.</div>
                    </div>
                </div>
            </section>

            <section class="{{ $panel }} p-5 xl:col-span-2">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Restore Status</h2>
                        <p class="mt-1 text-sm text-[var(--secondary-text)]">Restore is separate from backup creation.</p>
                    </div>
                    <span class="{{ $badge }} {{ $restoreEnabled ? 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' : 'border-[var(--glass-border-color)]/20 bg-[var(--glass-border-color)]/5 text-[var(--secondary-text)]' }}">
                        {{ $restoreEnabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <dl class="mt-4 grid grid-cols-1 gap-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--secondary-text)]">Typed confirmation</dt>
                        <dd>{{ $restoreRequirements['confirmation_required'] ? 'Required' : 'Not required' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--secondary-text)]">Staging/copy test</dt>
                        <dd>{{ $restoreRequirements['staging_tested_required'] ? 'Required' : 'Not required' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--secondary-text)]">Emergency backup</dt>
                        <dd>{{ $restoreRequirements['emergency_backup_before_restore'] ? 'Required' : 'Not required' }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="{{ $panel }} overflow-hidden">
            <div class="flex items-center justify-between border-b border-[var(--glass-border-color)]/10 p-5">
                <div>
                    <h2 class="text-lg font-semibold">Backup Logs</h2>
                    <p class="mt-1 text-sm text-[var(--secondary-text)]">Filenames are shown for identification; private storage paths are not shown.</p>
                </div>
                <span class="{{ $badge }} border-[var(--glass-border-color)]/20 bg-[var(--glass-border-color)]/5 text-[var(--secondary-text)]">
                    {{ $logs->count() }} records
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] text-sm">
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
                    <tbody class="divide-y divide-[var(--glass-border-color)]/10">
                        @forelse ($logs as $log)
                            @php
                                $statusClass = match ($log->status) {
                                    'success' => 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]',
                                    'failed' => 'border-[var(--border-error)] bg-[var(--bg-error)] text-[var(--text-error)]',
                                    default => 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]',
                                };
                            @endphp
                            <tr>
                                <td class="p-3">{{ $log->started_at?->format('Y-m-d H:i') }}</td>
                                <td class="p-3">{{ str_replace('_', ' ', $log->action) }}</td>
                                <td class="p-3"><span class="{{ $badge }} {{ $statusClass }}">{{ ucfirst($log->status) }}</span></td>
                                <td class="p-3">{{ $log->filename ?? '-' }}</td>
                                <td class="p-3">{{ $log->size_bytes ? number_format($log->size_bytes / 1024, 1) . ' KB' : '-' }}</td>
                                <td class="p-3 font-mono text-xs">{{ $log->checksum ? substr($log->checksum, 0, 16) . '...' : '-' }}</td>
                                <td class="p-3">
                                    <div class="flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('developer.backups.verify', $log) }}">
                                            @csrf
                                            <button type="submit" class="{{ $secondaryButton }}">Verify</button>
                                        </form>
                                        <a href="{{ route('developer.backups.download', $log) }}" class="{{ $secondaryButton }}">Download</a>
                                        <a href="{{ route('developer.backups.restore.show', $log) }}"
                                            class="inline-flex items-center justify-center rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] px-3 py-2 text-sm text-[var(--text-warning)] transition-all duration-300 ease-in-out hover:scale-[0.98]">
                                            Restore
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-8 text-center text-[var(--secondary-text)]">
                                    No managed backups yet. Create one before testing restore workflows.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
                </div>
            </div>
        </div>
    </section>
@endsection
