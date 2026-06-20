@extends('app')

@section('title', 'Activate License | ' . $client_company->name)

@section('content')
    <div class="w-full max-w-2xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Activate License</h1>
            <p class="text-[var(--secondary-text)] mt-1">This skeleton is safe while enforcement is disabled.</p>
        </div>

        <form method="POST" action="{{ route('developer.license.activate.post') }}"
              class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg p-5 space-y-4">
            @csrf

            <div>
                <label for="license_key" class="block text-sm font-medium mb-2">License Key</label>
                <input id="license_key" name="license_key" type="text" value="{{ old('license_key') }}"
                       class="w-full rounded border border-gray-700 bg-[var(--bg-color)] px-3 py-2 outline-none focus:border-[var(--primary-color)]"
                       autocomplete="off" required>
                @error('license_key')
                    <p class="mt-2 text-sm text-[var(--text-error)]">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2 rounded bg-[var(--primary-color)] hover:bg-[var(--h-primary-color)] text-white">
                    Activate
                </button>
                <a href="{{ route('developer.license.status') }}"
                   class="inline-flex items-center justify-center px-4 py-2 rounded bg-[var(--h-bg-color)] hover:bg-[var(--h-secondary-bg-color)]">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
