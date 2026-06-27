@extends('app')

@section('title', 'License Status | ' . $client_company->name)

@section('content')
    @php
        $panel = 'bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow-sm';
        $badge = 'inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-semibold';
        $primaryButton = 'inline-flex items-center justify-center rounded-lg bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-in-out hover:bg-[var(--h-primary-color)] hover:scale-[0.98]';
        $secondaryButton = 'inline-flex items-center justify-center rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--h-bg-color)] px-4 py-2 text-sm text-[var(--text-color)] transition-all duration-300 ease-in-out hover:bg-[var(--secondary-bg-color)] hover:scale-[0.98]';
        $disabledButton = 'inline-flex items-center justify-center rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--h-bg-color)] px-4 py-2 text-sm text-[var(--secondary-text)] opacity-60 cursor-not-allowed';
        $foundationReady = $foundationReady ?? true;
        $missingTables = $missingTables ?? [];
        $bannerType = match ($status->state) {
            'valid' => 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]',
            'subscription_expired', 'offline_grace' => 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]',
            default => 'border-[var(--border-error)] bg-[var(--bg-error)] text-[var(--text-error)]',
        };
    @endphp

    <div class="w-full max-w-5xl mx-auto p-4 md:p-6 space-y-6 text-[var(--text-color)]">
        <header class="{{ $panel }} p-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-[var(--secondary-text)]">Installation licensing</p>
                    <h1 class="mt-1 text-2xl font-semibold">License Status</h1>
                    <p class="mt-2 max-w-3xl text-sm text-[var(--secondary-text)]">
                        This license foundation binds the installed app/server, not each LAN browser or user PC. Raw machine details are not shown.
                    </p>
                </div>
                <span class="{{ $badge }} {{ $licensingEnabled ? 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' : 'border-[var(--glass-border-color)]/20 bg-[var(--glass-border-color)]/5 text-[var(--secondary-text)]' }}">
                    Enforcement {{ $licensingEnabled ? 'enabled' : 'disabled' }}
                </span>
            </div>
        </header>

        @if (!$foundationReady)
            <section class="rounded-xl border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
                <div class="font-semibold">Licensing setup is pending</div>
                <p class="mt-1">
                    The licensing foundation tables are not available in this local database yet. Run migrations only on a verified staging/client-copy database before using activation.
                </p>
                @if ($missingTables)
                    <p class="mt-2 font-mono text-xs">Missing: {{ implode(', ', $missingTables) }}</p>
                @endif
            </section>
        @endif

        <section class="{{ $panel }} p-5 space-y-5">
            <div class="rounded-xl border p-4 {{ $bannerType }}">
                {{ $status->message ?: 'License status calculated.' }}
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Current state</div>
                    <div class="mt-1 text-lg font-semibold">{{ ucfirst(str_replace('_', ' ', $status->state)) }}</div>
                </div>
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Action mode</div>
                    <div class="mt-1 text-lg font-semibold">{{ ucfirst($status->enforcement) }}</div>
                </div>
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Signed cache</div>
                    <div class="mt-1 text-lg font-semibold">{{ ucfirst(str_replace('_', ' ', $cacheStatus->state)) }}</div>
                </div>
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Installation</div>
                    <div class="mt-1 text-lg font-semibold">{{ $installationPreview }}</div>
                </div>
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Mode</div>
                    <div class="mt-1 text-lg font-semibold">{{ str_replace('_', ' / ', $installationMode) }}</div>
                </div>
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Fingerprint hash</div>
                    <div class="mt-1 text-lg font-semibold">{{ $fingerprintPreview }}</div>
                </div>
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Subscription expires</div>
                    <div class="mt-1 text-lg font-semibold">{{ $status->expiresAt?->format('Y-m-d') ?? '-' }}</div>
                </div>
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Offline grace until</div>
                    <div class="mt-1 text-lg font-semibold">{{ $status->graceUntil?->format('Y-m-d') ?? '-' }}</div>
                </div>
            </div>

            <div class="border-t border-[var(--glass-border-color)]/10 pt-4">
                <div class="text-xs uppercase text-[var(--secondary-text)]">Message</div>
                <div class="mt-1">{{ $status->message ?: 'No license message.' }}</div>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ $foundationReady ? route('developer.license.activate') : '#' }}" class="{{ $foundationReady ? $primaryButton : $disabledButton }}" @if (!$foundationReady) aria-disabled="true" @endif>Activate License</a>
                <a href="{{ route('developer.license.offline') }}" class="{{ $secondaryButton }}">Offline / Reactivation</a>
                <form method="POST" action="{{ route('developer.license.refresh') }}">
                    @csrf
                    <button type="submit" class="{{ $foundationReady ? $secondaryButton : $disabledButton }}" @disabled(!$foundationReady)>Refresh Subscription</button>
                </form>
                <a href="{{ route('developer.audit-logs') }}" class="{{ $secondaryButton }}">Audit Logs</a>
                <a href="{{ route('home') }}" class="{{ $secondaryButton }}">Back</a>
            </div>
        </section>
    </div>
@endsection
