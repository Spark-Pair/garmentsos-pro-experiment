@extends('app')

@section('title', 'Updater | ' . $client_company->name)

@section('content')
    <div class="w-full max-w-4xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">Updater</h1>
            <p class="text-[var(--secondary-text)] mt-1">Signed manifest and package verification foundation only.</p>
        </div>

        @if (session('success'))
            <div class="bg-green-900/40 border border-green-700 rounded-lg p-3 text-green-100">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-900/40 border border-red-700 rounded-lg p-3 text-red-100">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg p-5">
            <h2 class="text-lg font-semibold mb-3">Status</h2>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-[var(--secondary-text)]">Current version</dt>
                    <dd>{{ $currentVersion }}</dd>
                </div>
                <div>
                    <dt class="text-[var(--secondary-text)]">Channel</dt>
                    <dd>{{ $channel }}</dd>
                </div>
                <div>
                    <dt class="text-[var(--secondary-text)]">Updater enabled</dt>
                    <dd>{{ $enabled ? 'Yes' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="text-[var(--secondary-text)]">Manifest configured</dt>
                    <dd>{{ $manifestUrlConfigured ? 'Yes' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="text-[var(--secondary-text)]">Signature required</dt>
                    <dd>{{ $signatureRequired ? 'Yes' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="text-[var(--secondary-text)]">Apply/install</dt>
                    <dd>Not implemented in Phase 4A</dd>
                </div>
            </dl>
        </div>

        <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg p-5 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Check Update</h2>
                <p class="text-[var(--secondary-text)] mt-1">Checks a signed manifest only. It will not download or apply files automatically.</p>
            </div>
            <form method="POST" action="{{ route('developer.updater.check') }}">
                @csrf
                <button type="submit" class="px-4 py-2 bg-[var(--secondary-color)] text-white rounded-lg hover:opacity-90">
                    Check
                </button>
            </form>
        </div>

        @if ($result)
            <div class="bg-[var(--secondary-bg-color)] border border-gray-700 rounded-lg p-5 space-y-3">
                <h2 class="text-lg font-semibold">Manifest Result</h2>
                <p>{{ $result['message'] }}</p>
                <p class="text-sm text-[var(--secondary-text)]">Code: {{ $result['code'] }}</p>

                @if (!empty($result['manifest']))
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
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
                            <div class="font-mono text-xs break-all">{{ $result['manifest']['package_checksum'] ?? '-' }}</div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-[var(--secondary-text)]">Release notes</div>
                            <div class="whitespace-pre-line">{{ $result['manifest']['release_notes'] ?? '-' }}</div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div class="bg-yellow-900/40 border border-yellow-700 rounded-lg p-4 text-yellow-100">
            Apply/install update is not implemented in this phase. Future apply must create a verified backup first and must never overwrite client database, `.env`, backups, logs, private storage, or secrets.
        </div>
    </div>
@endsection
