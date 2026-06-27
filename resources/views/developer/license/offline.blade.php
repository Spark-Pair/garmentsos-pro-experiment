@extends('app')

@section('title', 'Offline Activation | ' . $client_company->name)

@section('content')
    @php
        $panel = 'bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow-sm';
        $primaryButton = 'inline-flex items-center justify-center rounded-lg bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-in-out hover:bg-[var(--h-primary-color)] hover:scale-[0.98]';
        $secondaryButton = 'inline-flex items-center justify-center rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--h-bg-color)] px-4 py-2 text-sm text-[var(--text-color)] transition-all duration-300 ease-in-out hover:bg-[var(--secondary-bg-color)] hover:scale-[0.98]';
        $textareaClass = 'w-full rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--bg-color)] px-3 py-2 text-sm outline-none focus:border-[var(--primary-color)]';
    @endphp

    <div class="w-full max-w-5xl mx-auto p-4 md:p-6 space-y-6 text-[var(--text-color)]">
        <header class="{{ $panel }} p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-[var(--secondary-text)]">Offline license flow</p>
            <h1 class="mt-1 text-2xl font-semibold">Offline Activation</h1>
            <p class="mt-2 max-w-3xl text-sm text-[var(--secondary-text)]">
                Export a safe installation request code, import a signed license response, or generate a manual reactivation request. LAN browsers are not separate licensed devices.
            </p>
        </header>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="{{ $panel }} p-5 space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">Request Code</h2>
                    <p class="mt-1 text-sm text-[var(--secondary-text)]">
                        Share this code with the license issuer. It does not include raw machine details, `.env`, APP_KEY, database credentials, or tokens.
                    </p>
                </div>
                <textarea readonly rows="8" class="{{ $textareaClass }}">{{ $requestCode }}</textarea>
            </section>

            <section class="{{ $panel }} p-5 space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">Import Signed License</h2>
                    <p class="mt-1 text-sm text-[var(--secondary-text)]">
                        Signed payloads are verified before local persistence.
                    </p>
                </div>
                <form method="POST" action="{{ route('developer.license.offline.import') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label for="signed_license" class="block text-sm font-medium mb-2">Signed License Response</label>
                        <textarea id="signed_license" name="signed_license" rows="8" class="{{ $textareaClass }}" required>{{ old('signed_license') }}</textarea>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="{{ $primaryButton }}">Import Signed License</button>
                        <a href="{{ route('developer.license.status') }}" class="{{ $secondaryButton }}">Back</a>
                    </div>
                </form>
            </section>
        </div>

        <section class="{{ $panel }} p-5 space-y-4">
            <div>
                <h2 class="text-lg font-semibold">Reactivation Request</h2>
                <p class="mt-1 text-sm text-[var(--secondary-text)]">
                    Use this only for legitimate server hardware or installation changes. It generates a request and does not self-approve activation.
                </p>
            </div>
            <form method="POST" action="{{ route('developer.license.reactivation-request') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="reason" class="block text-sm font-medium mb-2">Reason</label>
                    <textarea id="reason" name="reason" rows="3" class="{{ $textareaClass }}" required>{{ old('reason') }}</textarea>
                    @error('reason')
                        <p class="mt-2 text-sm text-[var(--text-error)]">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="{{ $secondaryButton }}">Generate Reactivation Request</button>
            </form>

            @if (!empty($reactivationCode))
                <div class="border-t border-[var(--glass-border-color)]/10 pt-4">
                    <label class="block text-sm font-medium mb-2">Reactivation Request Code</label>
                    <textarea readonly rows="6" class="{{ $textareaClass }}">{{ $reactivationCode }}</textarea>
                </div>
            @endif
        </section>
    </div>
@endsection
