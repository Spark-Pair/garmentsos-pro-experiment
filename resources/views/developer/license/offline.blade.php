@extends('app')

@section('title', 'Offline Activation | ' . $client_company->name)

@section('content')
    <div class="w-full max-w-3xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Offline Activation</h1>
            <p class="text-[var(--secondary-text)] mt-1">Export a request code for manual signing, import a signed license response, or generate a reactivation request.</p>
        </div>

        <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg p-5 space-y-4">
            <div>
                <label class="block text-sm font-medium mb-2">Request Code</label>
                <textarea readonly rows="6"
                          class="w-full rounded border border-gray-700 bg-[var(--bg-color)] px-3 py-2 outline-none">{{ $requestCode }}</textarea>
            </div>

            <form method="POST" action="{{ route('developer.license.offline.import') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="signed_license" class="block text-sm font-medium mb-2">Signed License Response</label>
                    <textarea id="signed_license" name="signed_license" rows="6"
                              class="w-full rounded border border-gray-700 bg-[var(--bg-color)] px-3 py-2 outline-none"
                              required>{{ old('signed_license') }}</textarea>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 rounded bg-[var(--primary-color)] hover:bg-[var(--h-primary-color)] text-white">
                        Import Signed License
                    </button>
                    <a href="{{ route('developer.license.status') }}"
                       class="inline-flex items-center justify-center px-4 py-2 rounded bg-[var(--h-bg-color)] hover:bg-[var(--h-secondary-bg-color)]">
                        Back
                    </a>
                </div>
            </form>

            <form method="POST" action="{{ route('developer.license.reactivation-request') }}" class="space-y-3 border-t border-gray-700 pt-4">
                @csrf
                <div>
                    <label for="reason" class="block text-sm font-medium mb-2">Reactivation Reason</label>
                    <textarea id="reason" name="reason" rows="3"
                              class="w-full rounded border border-gray-700 bg-[var(--bg-color)] px-3 py-2 outline-none"
                              required>{{ old('reason') }}</textarea>
                    @error('reason')
                        <p class="mt-2 text-sm text-[var(--text-error)]">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2 rounded bg-[var(--h-bg-color)] hover:bg-[var(--h-secondary-bg-color)]">
                    Generate Reactivation Request
                </button>
            </form>

            @if (!empty($reactivationCode))
                <div class="border-t border-gray-700 pt-4">
                    <label class="block text-sm font-medium mb-2">Reactivation Request Code</label>
                    <textarea readonly rows="6"
                              class="w-full rounded border border-gray-700 bg-[var(--bg-color)] px-3 py-2 outline-none">{{ $reactivationCode }}</textarea>
                </div>
            @endif
        </div>
    </div>
@endsection
