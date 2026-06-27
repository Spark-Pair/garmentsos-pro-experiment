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
        $inactiveButNotEnforced = !$licensingEnabled && in_array($status->state, ['no_license', 'blocked', 'tampered', 'setup_pending'], true);
        $bannerType = $inactiveButNotEnforced
            ? 'border-[var(--glass-border-color)]/20 bg-[var(--glass-border-color)]/5 text-[var(--secondary-text)]'
            : match ($status->state) {
                'valid' => 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]',
                'subscription_expired', 'offline_grace' => 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]',
                default => 'border-[var(--border-error)] bg-[var(--bg-error)] text-[var(--text-error)]',
            };
        $displayMessage = $inactiveButNotEnforced
            ? 'License not activated yet. Enforcement is disabled, so the app is not blocked.'
            : ($status->message ?: 'License status calculated.');
        $displayEnforcement = $inactiveButNotEnforced ? 'Not enforced' : ucfirst($status->enforcement);
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="License Status" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="License Status" />

            <div class="h-full overflow-y-auto my-scrollbar-2 px-4 pb-5 pt-12 text-left text-[var(--text-color)]">
                <div class="mb-4 flex flex-col gap-3 rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-4 md:flex-row md:items-start md:justify-between">
                    <p class="max-w-3xl text-sm text-[var(--secondary-text)]">
                        This license foundation binds the installed app/server, not each LAN browser or user PC. Raw machine details are not shown.
                    </p>
                    <span class="{{ $badge }} {{ $licensingEnabled ? 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' : 'border-[var(--glass-border-color)]/20 bg-[var(--glass-border-color)]/5 text-[var(--secondary-text)]' }}">
                        Enforcement {{ $licensingEnabled ? 'enabled' : 'disabled' }}
                    </span>
                </div>

                <div class="space-y-4">

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
                {{ $displayMessage }}
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Current state</div>
                    <div class="mt-1 text-lg font-semibold">{{ ucfirst(str_replace('_', ' ', $status->state)) }}</div>
                </div>
                <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Action mode</div>
                    <div class="mt-1 text-lg font-semibold">{{ $displayEnforcement }}</div>
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
                <div class="mt-1">{{ $displayMessage }}</div>
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
            </div>
        </div>
    </section>
@endsection
