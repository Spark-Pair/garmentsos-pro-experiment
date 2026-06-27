@extends('app')

@section('title', 'Updater | ' . $client_company->name)

@section('content')
    @php
        $panel = 'bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow-sm';
        $badge = 'inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-semibold';
        $primaryButton = 'inline-flex items-center justify-center rounded-lg bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-in-out hover:bg-[var(--h-primary-color)] hover:scale-[0.98]';
        $secondaryButton = 'inline-flex items-center justify-center rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--h-bg-color)] px-4 py-2 text-sm text-[var(--text-color)] transition-all duration-300 ease-in-out hover:bg-[var(--secondary-bg-color)] hover:scale-[0.98]';
    @endphp

    <div class="w-full max-w-5xl mx-auto p-4 md:p-6 space-y-6 text-[var(--text-color)]">
        <header class="{{ $panel }} p-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-[var(--secondary-text)]">Verification foundation</p>
                    <h1 class="mt-1 text-2xl font-semibold">Updater</h1>
                    <p class="mt-2 max-w-3xl text-sm text-[var(--secondary-text)]">
                        Signed manifest and package verification only. Apply/install is intentionally not implemented.
                    </p>
                </div>
                <span class="{{ $badge }} {{ $enabled ? 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' : 'border-[var(--glass-border-color)]/20 bg-[var(--glass-border-color)]/5 text-[var(--secondary-text)]' }}">
                    {{ $enabled ? 'Updater enabled' : 'Updater disabled' }}
                </span>
            </div>
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

        <div class="grid gap-4 lg:grid-cols-3">
            <section class="{{ $panel }} p-5 lg:col-span-2">
                <h2 class="text-lg font-semibold">Status</h2>
                <dl class="mt-4 grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                    <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                        <dt class="text-[var(--secondary-text)]">Current version</dt>
                        <dd class="mt-1 font-semibold">{{ $currentVersion }}</dd>
                    </div>
                    <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                        <dt class="text-[var(--secondary-text)]">Channel</dt>
                        <dd class="mt-1 font-semibold">{{ $channel }}</dd>
                    </div>
                    <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                        <dt class="text-[var(--secondary-text)]">Manifest configured</dt>
                        <dd class="mt-1 font-semibold">{{ $manifestUrlConfigured ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3">
                        <dt class="text-[var(--secondary-text)]">Signature required</dt>
                        <dd class="mt-1 font-semibold">{{ $signatureRequired ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-3 md:col-span-2">
                        <dt class="text-[var(--secondary-text)]">Apply/install</dt>
                        <dd class="mt-1 font-semibold">Not implemented</dd>
                    </div>
                </dl>
            </section>

            <section class="{{ $panel }} p-5">
                <h2 class="text-lg font-semibold">Check Update</h2>
                <p class="mt-2 text-sm text-[var(--secondary-text)]">
                    When enabled, this checks a signed manifest. It does not download, replace files, run migrations, or apply updates.
                </p>
                @if (!$enabled)
                    <div class="mt-4 rounded-xl border border-[var(--border-warning)] bg-[var(--bg-warning)] p-3 text-sm text-[var(--text-warning)]">
                        Updater is disabled by configuration. No manifest request is made.
                    </div>
                @endif
                <form method="POST" action="{{ route('developer.updater.check') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="{{ $enabled ? $primaryButton : $secondaryButton . ' cursor-not-allowed opacity-60' }}" @disabled(!$enabled)>
                        Check Manifest
                    </button>
                </form>
            </section>
        </div>

        @if ($result)
            <section class="{{ $panel }} p-5 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Manifest Result</h2>
                        <p class="mt-1 text-sm text-[var(--secondary-text)]">{{ $result['message'] }}</p>
                    </div>
                    <span class="{{ $badge }} {{ !empty($result['success']) ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]' : 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' }}">
                        {{ $result['code'] }}
                    </span>
                </div>

                @if (!empty($result['manifest']))
                    <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2">
                        <div>
                            <div class="text-[var(--secondary-text)]">Latest version</div>
                            <div>{{ $result['latest_version'] ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-[var(--secondary-text)]">Mandatory</div>
                            <div>{{ !empty($result['mandatory']) ? 'Yes' : 'No' }}</div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-[var(--secondary-text)]">Package checksum</div>
                            <div class="break-all font-mono text-xs">{{ $result['manifest']['package_checksum'] ?? '-' }}</div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-[var(--secondary-text)]">Release notes</div>
                            <div class="whitespace-pre-line">{{ $result['manifest']['release_notes'] ?? '-' }}</div>
                        </div>
                    </div>
                @endif
            </section>
        @endif

        <section class="rounded-xl border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
            Future update apply must create a verified backup first and must never overwrite client database, `.env`, backups, logs, private storage, or secrets.
        </section>
    </div>
@endsection
