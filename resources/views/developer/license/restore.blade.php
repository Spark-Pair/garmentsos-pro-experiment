@extends('app')

@section('title', 'Restore Backup | ' . $client_company->name)

@section('content')
    @php
        $panel = 'rounded-2xl border border-[var(--h-bg-color)] bg-[var(--secondary-bg-color)] shadow-sm';
        $badge = 'inline-flex items-center rounded-xl border px-3 py-1.5 text-xs font-semibold';
        $primaryButton = 'inline-flex items-center justify-center rounded-lg bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-in-out hover:bg-[var(--h-primary-color)] hover:scale-[0.98]';
        $secondaryButton = 'inline-flex items-center justify-center rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--h-bg-color)] px-4 py-2 text-sm text-[var(--text-color)] transition-all duration-300 ease-in-out hover:bg-[var(--secondary-bg-color)] hover:scale-[0.98]';
        $dangerButton = 'inline-flex items-center justify-center rounded-lg bg-[var(--danger-color)] px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-in-out hover:bg-[var(--h-danger-color)] hover:scale-[0.98]';
        $restoreEnabled = (bool) ($requirements['restore_enabled'] ?? false);
    @endphp

    <div class="w-full max-w-5xl mx-auto p-4 md:p-6 space-y-6 text-[var(--text-color)]">
        <header class="{{ $panel }} p-5">
            <x-developer-panel-title title="Restore Backup" description="Restore is intentionally disabled unless explicitly enabled for a staged, verified recovery procedure." class="mb-0 border-b-0 pb-0">
                <span class="{{ $badge }} {{ $restoreEnabled ? 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' : 'border-[var(--glass-border-color)]/20 bg-[var(--glass-border-color)]/5 text-[var(--secondary-text)]' }}">
                    {{ $restoreEnabled ? 'Restore enabled' : 'Restore disabled' }}
                </span>
            </x-developer-panel-title>
        </header>

        @if (session('success'))
            <div class="rounded-xl border border-[var(--border-success)] bg-[var(--bg-success)] px-4 py-3 text-sm text-[var(--text-success)]">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-[var(--border-error)] bg-[var(--bg-error)] px-4 py-3 text-sm text-[var(--text-error)]">
                {{ session('error') }}
            </div>
        @endif

        <section class="rounded-xl border border-[var(--border-warning)] bg-[var(--bg-warning)] p-5 text-[var(--text-warning)]">
            <h2 class="text-sm font-semibold uppercase tracking-wide">Restore safety notice</h2>
            <p class="mt-2 text-sm">
                Restoring replaces the current SQLite database only after verification and an emergency backup. Test the backup on a staging/client-copy database before enabling restore.
            </p>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="{{ $panel }} p-5 space-y-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide">Selected Backup</h2>
                <dl class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                    <div>
                        <dt class="text-[var(--secondary-text)]">Backup ID</dt>
                        <dd>{{ $backupLog->id }}</dd>
                    </div>
                    <div>
                        <dt class="text-[var(--secondary-text)]">Created</dt>
                        <dd>{{ $backupLog->started_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[var(--secondary-text)]">File</dt>
                        <dd>{{ $backupLog->filename ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[var(--secondary-text)]">Size</dt>
                        <dd>{{ $backupLog->size_bytes ? number_format($backupLog->size_bytes / 1024, 1) . ' KB' : '-' }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-[var(--secondary-text)]">Checksum</dt>
                        <dd class="break-all font-mono text-xs">{{ $backupLog->checksum ?? '-' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="{{ $panel }} p-5 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wide">Verification</h2>
                        <p class="mt-1 text-sm text-[var(--secondary-text)]">The selected backup must verify before restore can proceed.</p>
                    </div>
                    <span class="{{ $badge }} {{ $inspection['valid'] ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]' : 'border-[var(--border-error)] bg-[var(--bg-error)] text-[var(--text-error)]' }}">
                        {{ $inspection['valid'] ? 'Valid' : 'Blocked' }}
                    </span>
                </div>
                <p class="text-sm">{{ $inspection['message'] }}</p>
                <p class="text-xs text-[var(--secondary-text)]">Code: {{ $inspection['verification']['code'] ?? 'unknown' }}</p>
            </section>
        </div>

        <section class="{{ $panel }} p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide">Restore Requirements</h2>
            <div class="mt-4 grid gap-3 text-sm md:grid-cols-2">
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">Typed confirmation: required</div>
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">Staging/copy test: {{ $requirements['staging_tested_required'] ? 'required' : 'not required' }}</div>
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">Emergency backup: required</div>
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">Public backups: not allowed</div>
            </div>
        </section>

        <form method="POST" action="{{ route('developer.backups.restore.store', $backupLog) }}"
            class="{{ $panel }} p-5 space-y-4">
            @csrf

            <div>
                <label class="block text-sm text-[var(--secondary-text)] mb-1">Type this phrase exactly</label>
                <div class="mb-2 rounded-lg bg-[var(--h-bg-color)] p-2 font-mono text-sm">{{ $confirmationPhrase }}</div>
                <input type="text" name="confirmation_phrase"
                    class="w-full rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--h-bg-color)] px-3 py-2 outline-none focus:border-[var(--primary-color)] disabled:cursor-not-allowed disabled:opacity-60"
                    autocomplete="off" @disabled(!$restoreEnabled)>
            </div>

            <label class="flex items-center justify-between gap-3 rounded-xl border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/35 px-3 py-2 text-sm {{ $restoreEnabled ? '' : 'opacity-60' }}">
                <span>I tested this restore on a staging/copy database and confirmed the backup is correct.</span>
                <x-toggle-switch name="staging_tested" :disabled="!$restoreEnabled" />
            </label>

            @if (!$restoreEnabled)
                <div class="rounded-xl border border-[var(--border-warning)] bg-[var(--bg-warning)] p-3 text-sm text-[var(--text-warning)]">
                    Restore is disabled by configuration. The restore action is unavailable until BACKUP_RESTORE_ENABLED is explicitly enabled after staging verification.
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="{{ $restoreEnabled ? $dangerButton : $secondaryButton . ' cursor-not-allowed opacity-60' }}" @disabled(!$restoreEnabled)>
                    Restore Backup
                </button>
                <a href="{{ route('developer.backups') }}" class="{{ $secondaryButton }}">Back to Backups</a>
            </div>
        </form>
    </div>
@endsection
