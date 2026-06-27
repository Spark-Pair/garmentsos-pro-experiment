@extends('app')

@section('title', 'License Status | ' . $client_company->name)

@section('content')
    @php
        $panel = 'bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-7 border border-[var(--h-bg-color)] pt-12 relative overflow-hidden';
        $softPanel = 'rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/50 p-4';
        $badge = 'inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-semibold';
        $primaryButton = 'px-4 py-2 bg-[var(--primary-color)] text-[var(--text-color)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $secondaryButton = 'px-4 py-2 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg hover:bg-[var(--secondary-bg-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $disabledButton = 'px-4 py-2 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg opacity-60 cursor-not-allowed';
        $foundationReady = $foundationReady ?? true;
        $missingTables = $missingTables ?? [];
        $inactiveButNotEnforced = !$licensingEnabled && in_array($status->state, ['no_license', 'blocked', 'tampered', 'setup_pending'], true);
        $bannerType = $inactiveButNotEnforced
            ? 'border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]'
            : match ($status->state) {
                'valid' => 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]',
                'subscription_expired', 'offline_grace' => 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]',
                default => 'border-[var(--border-error)] bg-[var(--bg-error)] text-[var(--text-error)]',
            };
        $displayMessage = $inactiveButNotEnforced
            ? 'License not activated yet. Enforcement is disabled, so the app is not blocked.'
            : ($status->message ?: 'License status calculated.');
        $displayState = $inactiveButNotEnforced ? 'Pending activation' : ucfirst(str_replace('_', ' ', $status->state));
        $displayEnforcement = $inactiveButNotEnforced ? 'Not enforced' : ucfirst($status->enforcement);
    @endphp

    <div class="mb-5 max-w-6xl mx-auto">
        <x-search-header heading="License Status" />
    </div>

    <div class="max-w-6xl mx-auto space-y-4">
        <section class="{{ $panel }}">
            <x-form-title-bar title="License Status" />

            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <p class="max-w-3xl text-sm text-[var(--secondary-text)]">
                    This license binds the installed server/app, not each LAN browser PC. Raw machine details are not shown.
                </p>
                <span class="{{ $badge }} {{ $licensingEnabled ? 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' : 'border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]' }}">
                    Enforcement {{ $licensingEnabled ? 'enabled' : 'disabled' }}
                </span>
            </div>

            @if (!$foundationReady)
                <div class="mb-4 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
                    <div class="font-semibold">Licensing setup is pending</div>
                    <p class="mt-1">The licensing foundation tables are not available in this local database yet.</p>
                    @if ($missingTables)
                        <p class="mt-2 font-mono text-xs">Missing: {{ implode(', ', $missingTables) }}</p>
                    @endif
                </div>
            @endif

            <div class="mb-4 rounded-lg border p-4 {{ $bannerType }}">
                {{ $displayMessage }}
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Current state</div>
                    <div class="mt-1 text-lg font-semibold">{{ $displayState }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Action mode</div>
                    <div class="mt-1 text-lg font-semibold">{{ $displayEnforcement }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Signed cache</div>
                    <div class="mt-1 text-lg font-semibold">{{ ucfirst(str_replace('_', ' ', $cacheStatus->state)) }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Installation</div>
                    <div class="mt-1 text-lg font-semibold">{{ $installationPreview }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Mode</div>
                    <div class="mt-1 text-lg font-semibold">{{ str_replace('_', ' / ', $installationMode) }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Fingerprint hash</div>
                    <div class="mt-1 text-lg font-semibold">{{ $fingerprintPreview }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Subscription expires</div>
                    <div class="mt-1 text-lg font-semibold">{{ $status->expiresAt?->format('Y-m-d') ?? '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Offline grace until</div>
                    <div class="mt-1 text-lg font-semibold">{{ $status->graceUntil?->format('Y-m-d') ?? '-' }}</div>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ $foundationReady ? route('developer.license.activate') : '#' }}" class="{{ $foundationReady ? $primaryButton : $disabledButton }}" @if (!$foundationReady) aria-disabled="true" @endif>Activate License</a>
                <a href="{{ route('developer.license.offline') }}" class="{{ $secondaryButton }}">Offline / Reactivation</a>
                <form method="POST" action="{{ route('developer.license.refresh') }}">
                    @csrf
                    <button type="submit" class="{{ $foundationReady ? $secondaryButton : $disabledButton }}" @disabled(!$foundationReady)>Refresh Subscription</button>
                </form>
                <a href="{{ route('developer.audit-logs') }}" class="{{ $secondaryButton }}">Audit Logs</a>
            </div>
        </section>
    </div>
@endsection
