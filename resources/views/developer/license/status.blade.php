@extends('app')

@section('title', 'License Status | ' . $client_company->name)

@section('content')
    <div class="w-full max-w-4xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">License Status</h1>
            <p class="text-[var(--secondary-text)] mt-1">Developer-only licensing foundation status.</p>
        </div>

        <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg p-5 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Enforcement</div>
                    <div class="text-lg font-semibold">{{ $licensingEnabled ? 'Enabled' : 'Disabled' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Current State</div>
                    <div class="text-lg font-semibold">{{ ucfirst(str_replace('_', ' ', $status->state)) }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Action Mode</div>
                    <div class="text-lg font-semibold">{{ ucfirst($status->enforcement) }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Installation</div>
                    <div class="text-lg font-semibold">{{ $installationPreview }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Mode</div>
                    <div class="text-lg font-semibold">{{ str_replace('_', ' / ', $installationMode) }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase text-[var(--secondary-text)]">Installation Fingerprint</div>
                    <div class="text-lg font-semibold">{{ $fingerprintPreview }}</div>
                </div>
            </div>

            <div class="border-t border-gray-700 pt-4">
                <div class="text-xs uppercase text-[var(--secondary-text)]">Message</div>
                <div class="mt-1">{{ $status->message ?: 'No license message.' }}</div>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('developer.license.activate') }}"
                   class="inline-flex items-center justify-center px-4 py-2 rounded bg-[var(--primary-color)] hover:bg-[var(--h-primary-color)] text-white">
                    Activate License
                </a>
                <a href="{{ route('developer.license.offline') }}"
                   class="inline-flex items-center justify-center px-4 py-2 rounded bg-[var(--h-bg-color)] hover:bg-[var(--h-secondary-bg-color)]">
                    Offline Activation
                </a>
                <form method="POST" action="{{ route('developer.license.refresh') }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 rounded bg-[var(--h-bg-color)] hover:bg-[var(--h-secondary-bg-color)]">
                        Refresh Subscription
                    </button>
                </form>
                <a href="{{ route('home') }}"
                   class="inline-flex items-center justify-center px-4 py-2 rounded bg-[var(--h-bg-color)] hover:bg-[var(--h-secondary-bg-color)]">
                    Back
                </a>
            </div>
        </div>
    </div>
@endsection
