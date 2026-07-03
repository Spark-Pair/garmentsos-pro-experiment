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
        $inactiveButNotEnforced = !$licensingEnabled;
        $bannerType = $inactiveButNotEnforced
            ? 'border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]'
            : match ($status->state) {
                'active' => 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]',
                'expiring_soon', 'grace_period', 'offline_grace', 'expired_readonly' => 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]',
                default => 'border-[var(--border-error)] bg-[var(--bg-error)] text-[var(--text-error)]',
            };
        $displayMessage = $inactiveButNotEnforced
            ? 'License enforcement is disabled. App is not blocked by missing license.'
            : ($status->message ?: 'License status calculated.');
        $displayState = $inactiveButNotEnforced ? 'Not enforced' : ucfirst(str_replace('_', ' ', $status->state));
        $displayEnforcement = $inactiveButNotEnforced ? 'Not enforced' : ucfirst($status->enforcement);
        $pendingApproval = $status->state === 'pending';
    @endphp

    <div class="mb-5 max-w-6xl mx-auto">
        <x-search-header heading="License Status" />
    </div>

    <div class="max-w-6xl mx-auto space-y-4">
        <section class="{{ $panel }}">
            <x-form-title-bar title="License Status" />

            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <p class="max-w-3xl text-sm text-[var(--secondary-text)]">
                    This license is approved from SparkPair by install ID and machine fingerprint. No license key is entered in this app.
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
                @if ($pendingApproval)
                    <div class="mt-2 text-sm font-semibold">
                        This device is registered and waiting for approval from SparkPair.
                    </div>
                @endif
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
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Device approval</div>
                    <div class="mt-1 text-lg font-semibold">{{ $licenseConfig['device_status'] ? ucfirst(str_replace('_', ' ', $licenseConfig['device_status'])) : $displayState }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">App version</div>
                    <div class="mt-1 text-lg font-semibold">{{ $licenseConfig['app_version'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Machine name</div>
                    <div class="mt-1 break-all text-lg font-semibold">{{ $licenseConfig['machine_name'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Machine hash</div>
                    <div class="mt-1 break-all text-lg font-semibold">{{ $licenseConfig['machine_hash_preview'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Install ID</div>
                    <div class="mt-1 break-all text-sm font-semibold" id="license-install-id">{{ $licenseConfig['install_id'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Customer / license</div>
                    <div class="mt-1 text-lg font-semibold">{{ $licenseConfig['customer_name'] ?: ($licenseConfig['client_name'] ?: '-') }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Expires</div>
                    <div class="mt-1 text-lg font-semibold">{{ $status->expiresAt?->format('Y-m-d') ?? '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Grace until</div>
                    <div class="mt-1 text-lg font-semibold">{{ $status->graceUntil?->format('Y-m-d') ?? '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Last check</div>
                    <div class="mt-1 text-lg font-semibold">{{ $licenseConfig['last_check_at'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Last registration</div>
                    <div class="mt-1 text-lg font-semibold">{{ $licenseConfig['last_registration_at'] ?: '-' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Check URL</div>
                    <div class="mt-1 break-all text-sm font-semibold">{{ $licenseConfig['check_url_configured'] ? $licenseConfig['check_url'] : 'Not configured' }}</div>
                </div>
                <div class="{{ $softPanel }}">
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Register URL</div>
                    <div class="mt-1 break-all text-sm font-semibold">{{ $licenseConfig['register_url'] ?: '-' }}</div>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('developer.license.register') }}">
                    @csrf
                    <button type="submit" class="{{ $primaryButton }}">Register this device</button>
                </form>
                <form method="POST" action="{{ route('developer.license.check') }}">
                    @csrf
                    <button type="submit" class="{{ $secondaryButton }}">Check license now</button>
                </form>
                <button type="button" class="{{ $secondaryButton }}" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('license-install-id').innerText)">
                    Copy Install ID
                </button>
                <a href="{{ route('developer.audit-logs') }}" class="{{ $secondaryButton }}">Audit Logs</a>
            </div>
        </section>
    </div>
@endsection
