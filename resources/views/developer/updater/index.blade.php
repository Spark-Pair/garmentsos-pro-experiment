@extends('app')

@section('title', 'Updater | ' . $client_company->name)

@section('content')
    @php
        $panel = 'bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-7 border border-[var(--h-bg-color)] pt-12 relative overflow-hidden';
        $softPanel = 'rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/50 p-4';
        $badge = 'inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-semibold';
        $primaryButton = 'px-4 py-2 bg-[var(--primary-color)] text-[var(--text-color)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $secondaryButton = 'px-4 py-2 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg hover:bg-[var(--secondary-bg-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $disabledButton = 'px-4 py-2 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg opacity-60 cursor-not-allowed';
    @endphp

    <div class="mb-5 max-w-6xl mx-auto">
        <x-search-header heading="Updater" />
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

        <section class="{{ $panel }}">
            <x-form-title-bar title="Updater" />

            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <p class="max-w-3xl text-sm text-[var(--secondary-text)]">
                    Verification foundation only. Signed manifest/package checks are available when configured; apply/install is not implemented.
                </p>
                <span class="{{ $badge }} {{ $enabled ? 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' : 'border-gray-600 bg-[var(--h-bg-color)] text-[var(--secondary-text)]' }}">
                    {{ $enabled ? 'Updater enabled' : 'Updater disabled' }}
                </span>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:col-span-2">
                    <div class="{{ $softPanel }}">
                        <dt class="text-[var(--secondary-text)]">Current version</dt>
                        <dd class="mt-1 font-semibold">{{ $currentVersion }}</dd>
                    </div>
                    <div class="{{ $softPanel }}">
                        <dt class="text-[var(--secondary-text)]">Channel</dt>
                        <dd class="mt-1 font-semibold">{{ $channel }}</dd>
                    </div>
                    <div class="{{ $softPanel }}">
                        <dt class="text-[var(--secondary-text)]">Manifest configured</dt>
                        <dd class="mt-1 font-semibold">{{ $manifestUrlConfigured ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="{{ $softPanel }}">
                        <dt class="text-[var(--secondary-text)]">Signature required</dt>
                        <dd class="mt-1 font-semibold">{{ $signatureRequired ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="{{ $softPanel }} md:col-span-2">
                        <dt class="text-[var(--secondary-text)]">Apply/install</dt>
                        <dd class="mt-1 font-semibold">Not implemented</dd>
                    </div>
                </div>

                <div class="{{ $softPanel }}">
                    <h2 class="font-semibold">Check Update</h2>
                    <p class="mt-2 text-sm text-[var(--secondary-text)]">
                        Checks a signed manifest only. It does not download, replace files, run migrations, or apply updates.
                    </p>
                    @if (!$enabled)
                        <div class="mt-4 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-3 text-sm text-[var(--text-warning)]">
                            Updater is disabled by configuration. No manifest request is made.
                        </div>
                    @endif
                    <form method="POST" action="{{ route('developer.updater.check') }}" class="mt-4">
                        @csrf
                        <button type="submit" class="{{ $enabled ? $primaryButton : $disabledButton }}" @disabled(!$enabled)>
                            Check Manifest
                        </button>
                    </form>
                </div>
            </div>
        </section>

        @if ($result)
            <section class="{{ $panel }}">
                <x-form-title-bar title="Manifest Result" />

                <div class="mb-4 flex items-start justify-between gap-3">
                    <p class="text-sm text-[var(--secondary-text)]">{{ $result['message'] }}</p>
                    <span class="{{ $badge }} {{ !empty($result['success']) ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]' : 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]' }}">
                        {{ $result['code'] }}
                    </span>
                </div>

                @if (!empty($result['manifest']))
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="{{ $softPanel }}">
                            <div class="text-[var(--secondary-text)]">Latest version</div>
                            <div>{{ $result['latest_version'] ?? '-' }}</div>
                        </div>
                        <div class="{{ $softPanel }}">
                            <div class="text-[var(--secondary-text)]">Mandatory</div>
                            <div>{{ !empty($result['mandatory']) ? 'Yes' : 'No' }}</div>
                        </div>
                        <div class="{{ $softPanel }} md:col-span-2">
                            <div class="text-[var(--secondary-text)]">Package checksum</div>
                            <div class="break-all font-mono text-xs">{{ $result['manifest']['package_checksum'] ?? '-' }}</div>
                        </div>
                        <div class="{{ $softPanel }} md:col-span-2">
                            <div class="text-[var(--secondary-text)]">Release notes</div>
                            <div class="whitespace-pre-line">{{ $result['manifest']['release_notes'] ?? '-' }}</div>
                        </div>
                    </div>
                @endif
            </section>
        @endif

        <section class="rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)]">
            Future update apply must create a verified backup first and must never overwrite client database, `.env`, backups, logs, private storage, or secrets.
        </section>
    </div>
@endsection
