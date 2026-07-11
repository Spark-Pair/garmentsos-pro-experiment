@extends('app')

@section('title', 'Backup & Restore | ' . $client_company->name)

@section('content')
    @php
        $panel = 'bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-7 border border-[var(--h-bg-color)] pt-12 relative overflow-hidden';
        $softPanel = 'rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/50 p-4';
        $badge = 'inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-semibold';
        $primaryButton = 'px-4 py-2 bg-[var(--primary-color)] text-[var(--text-color)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $secondaryButton = 'px-4 py-2 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg hover:bg-[var(--secondary-bg-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $disabledButton = 'px-4 py-2 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg opacity-60 cursor-not-allowed';
        $restoreEnabled = (bool) ($restoreRequirements['restore_enabled'] ?? false);
        $foundationReady = $foundationReady ?? true;
        $missingTables = $missingTables ?? [];
    @endphp

    <div class="mb-5 max-w-6xl mx-auto">
        <x-search-header heading="Backup & Restore" />
    </div>

    <div class="max-w-6xl mx-auto space-y-4">
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

        @if ($errors->any())
            <div class="rounded-lg border border-[var(--border-error)] bg-[var(--bg-error)] p-4 text-sm text-[var(--text-error)]">
                <div class="font-semibold">Please fix the highlighted fields before continuing.</div>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="{{ $panel }}">
            <x-form-title-bar title="Backup & Restore" />

            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
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

            @if (!$foundationReady)
                <div class="mt-4 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
                    <div class="font-semibold">Backup setup is pending</div>
                    <p class="mt-1">Backup foundation tables are not available in this local database yet.</p>
                    @if ($missingTables)
                        <p class="mt-2 font-mono text-xs">Missing: {{ implode(', ', $missingTables) }}</p>
                    @endif
                </div>
            @endif

            <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="{{ $softPanel }} xl:col-span-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">Backup Workflow</h2>
                            <p class="mt-1 text-sm text-[var(--secondary-text)]">Standalone SQLite copy, checksum, metadata, backup log, and audit log.</p>
                        </div>
                        <span class="{{ $badge }} border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]">Private storage</span>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
                        <div class="rounded-lg border border-gray-600 bg-[var(--secondary-bg-color)] p-3">
                            <div class="font-semibold">Create</div>
                            <div class="mt-1 text-[var(--secondary-text)]">Managed backup only.</div>
                        </div>
                        <div class="rounded-lg border border-gray-600 bg-[var(--secondary-bg-color)] p-3">
                            <div class="font-semibold">Verify</div>
                            <div class="mt-1 text-[var(--secondary-text)]">SQLite validity and checksum.</div>
                        </div>
                        <div class="rounded-lg border border-gray-600 bg-[var(--secondary-bg-color)] p-3">
                            <div class="font-semibold">Download</div>
                            <div class="mt-1 text-[var(--secondary-text)]">Developer/admin only.</div>
                        </div>
                    </div>
                </div>

                <div class="{{ $softPanel }} xl:col-span-2">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">Restore Status</h2>
                            <p class="mt-1 text-sm text-[var(--secondary-text)]">Restore is separate and disabled by default.</p>
                        </div>
                        <span class="{{ $badge }} {{ $restoreEnabled ? 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' : 'border-gray-600 bg-[var(--secondary-bg-color)] text-[var(--secondary-text)]' }}">
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
                </div>
            </div>
        </section>

        <section class="{{ $panel }}">
            <x-form-title-bar title="Restore Old SQLite Database" />

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div class="{{ $softPanel }}">
                    <h2 class="font-semibold">Business data only</h2>
                    <p class="mt-2 text-sm text-[var(--secondary-text)]">
                        Upload an old GarmentsOS SQLite database to restore business records. This does not restore .env, install ID, license cache, device approval, request cache, or update markers.
                    </p>
                    <p class="mt-2 text-sm font-semibold text-[var(--text-warning)]">
                        License/device approval remains tied to this installation after restore.
                    </p>
                </div>

                <form method="POST" action="{{ route('developer.backups.restore-upload') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium">SQLite database file</label>
                        <input type="file" name="sqlite_file" accept=".sqlite,.db" class="w-full rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)] px-3 py-2 text-sm text-[var(--text-color)]" @disabled(!$restoreEnabled)>
                        <p class="mt-1 text-xs text-[var(--secondary-text)]">Allowed extensions: .sqlite, .db</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Type confirmation phrase</label>
                        <input name="confirmation_phrase" placeholder="RESTORE BUSINESS DATA" class="w-full rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)] px-3 py-2 text-sm text-[var(--text-color)]" @disabled(!$restoreEnabled)>
                    </div>
                    <label class="flex items-start gap-2 text-sm text-[var(--secondary-text)]">
                        <input type="checkbox" name="staging_tested" value="1" class="mt-1" @disabled(!$restoreEnabled)>
                        <span>I tested this restore on a staging/copy database and confirmed this old database is correct.</span>
                    </label>
                    <button type="submit" class="{{ $restoreEnabled ? $primaryButton : $disabledButton }}" @disabled(!$restoreEnabled) onclick="return confirm('Restore uploaded business database now? Current database will be backed up first.')">
                        Restore Uploaded Database
                    </button>
                </form>
            </div>
        </section>

        <section class="{{ $panel }}">
            <x-form-title-bar title="Backup Logs" />

            <div class="mb-4 flex items-center justify-between gap-3">
                <p class="text-sm text-[var(--secondary-text)]">Filenames are shown for identification; private storage paths are not shown.</p>
                <span class="{{ $badge }} border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]">{{ $logs->count() }} records</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] text-sm">
                    <thead class="bg-[var(--h-bg-color)]">
                        <tr>
                            <th class="p-3 text-left">Started</th>
                            <th class="p-3 text-left">Action</th>
                            <th class="p-3 text-left">Status</th>
                            <th class="p-3 text-left">File</th>
                            <th class="p-3 text-left">Size</th>
                            <th class="p-3 text-left">Checksum</th>
                            <th class="p-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-600/40">
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
                                            class="{{ $restoreEnabled ? $secondaryButton : $disabledButton }}"
                                            @if (!$restoreEnabled) aria-disabled="true" @endif>
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
@endsection
