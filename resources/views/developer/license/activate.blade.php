@extends('app')

@section('title', 'Activate License | ' . $client_company->name)

@section('content')
    @php
        $panel = 'bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow-sm';
        $primaryButton = 'inline-flex items-center justify-center rounded-lg bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-in-out hover:bg-[var(--h-primary-color)] hover:scale-[0.98]';
        $secondaryButton = 'inline-flex items-center justify-center rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--h-bg-color)] px-4 py-2 text-sm text-[var(--text-color)] transition-all duration-300 ease-in-out hover:bg-[var(--secondary-bg-color)] hover:scale-[0.98]';
    @endphp

    <div class="w-full max-w-3xl mx-auto p-4 md:p-6 space-y-6 text-[var(--text-color)]">
        <header class="{{ $panel }} p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-[var(--secondary-text)]">License activation</p>
            <h1 class="mt-1 text-2xl font-semibold">Activate License</h1>
            <p class="mt-2 text-sm text-[var(--secondary-text)]">
                Activates this app installation/server. The raw license key is sent for activation and is not stored locally.
            </p>
        </header>

        <form method="POST" action="{{ route('developer.license.activate.post') }}"
              class="{{ $panel }} p-5 space-y-4">
            @csrf

            <div>
                <label for="license_key" class="block text-sm font-medium mb-2">License Key</label>
                <input id="license_key" name="license_key" type="text"
                       class="w-full rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--bg-color)] px-3 py-2 outline-none focus:border-[var(--primary-color)]"
                       autocomplete="off" required>
                <p class="mt-2 text-xs text-[var(--secondary-text)]">
                    Do not paste private signing keys or server secrets here.
                </p>
                @error('license_key')
                    <p class="mt-2 text-sm text-[var(--text-error)]">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="{{ $primaryButton }}">Activate</button>
                <a href="{{ route('developer.license.status') }}" class="{{ $secondaryButton }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
