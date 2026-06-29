@extends('app')

@section('title', 'First-Run Setup | ' . $client_company->name)

@section('content')
    @php
        $panel = 'bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg border border-[var(--h-bg-color)] relative overflow-hidden';
        $softPanel = 'rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/50';
        $label = 'block text-sm font-medium mb-1.5';
        $input = 'w-full rounded-lg border border-gray-600 bg-[var(--bg-color)] px-3 py-2 text-[var(--text-color)] outline-none focus:border-[var(--primary-color)]';
        $statusText = fn ($item) => !empty($item['ok']) ? 'text-[var(--text-success)]' : 'text-[var(--text-error)]';
        $statusDot = fn ($item) => !empty($item['ok']) ? 'bg-[var(--border-success)]' : 'bg-[var(--border-error)]';
        $hasExistingData = !empty($existing['has_existing_data']);
        $setupForceEnabled = $setupForceEnabled ?? false;
    @endphp

    <div class="mx-auto w-full max-w-6xl space-y-4">
        <section class="{{ $panel }} p-5 md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-xs font-semibold uppercase text-[var(--secondary-text)]">GarmentsOS PRO</p>
                        @if ($setupForceEnabled)
                            <span class="rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] px-2 py-1 text-xs font-semibold text-[var(--text-warning)]">
                                Developer setup test mode
                            </span>
                        @endif
                    </div>
                    <h1 class="mt-1 text-2xl font-semibold text-[var(--primary-color)]">First-Run Setup</h1>
                    <p class="mt-2 max-w-3xl text-[var(--secondary-text)]">
                        Prepare this local server installation for browser access over HTTP at http://localhost:8000 or http://SERVER-IP:8000.
                    </p>
                </div>

                <div class="grid min-w-0 grid-cols-2 gap-3 text-xs sm:min-w-[360px]">
                    <div class="{{ $softPanel }} p-3">
                        <div class="text-[var(--secondary-text)]">Version</div>
                        <div class="mt-1 truncate font-semibold">{{ $system['app_version'] }}</div>
                    </div>
                    <div class="{{ $softPanel }} p-3">
                        <div class="text-[var(--secondary-text)]">Mode / URL</div>
                        <div class="mt-1 truncate font-semibold">{{ str_replace('_', ' / ', $system['mode'] ?? 'local_lan') }}</div>
                    </div>
                    <div class="{{ $softPanel }} p-3">
                        <div class="text-[var(--secondary-text)]">Installation</div>
                        <div class="mt-1 truncate font-semibold">{{ $installationPreview }}</div>
                    </div>
                    <div class="{{ $softPanel }} p-3">
                        <div class="text-[var(--secondary-text)]">Fingerprint</div>
                        <div class="mt-1 truncate font-semibold">{{ $fingerprintPreview }}</div>
                    </div>
                </div>
            </div>

            @if ($setupForceEnabled)
                <div class="mt-4 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)]/80 p-3 text-sm text-[var(--text-warning)]">
                    Developer setup test mode is enabled. This allows setup testing on an existing/copied database.
                </div>
            @endif
        </section>

        <section class="grid grid-cols-1 gap-3 md:grid-cols-4">
            @foreach ([
                ['label' => 'Database', 'item' => $system['database']],
                ['label' => 'Storage', 'item' => $system['storage']],
                ['label' => 'Backup', 'item' => $system['backup']],
            ] as $status)
                <div class="{{ $panel }} p-4">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full {{ $statusDot($status['item']) }}"></span>
                        <div class="text-[var(--secondary-text)]">{{ $status['label'] }}</div>
                    </div>
                    <div class="mt-2 font-semibold {{ $statusText($status['item']) }}">{{ $status['item']['label'] }}</div>
                </div>
            @endforeach
            <div class="{{ $panel }} p-4">
                <div class="text-[var(--secondary-text)]">Local URL</div>
                <div class="mt-2 truncate font-semibold">{{ $system['local_url_example'] ?? 'http://SERVER-IP:8000' }}</div>
            </div>
        </section>

        @if ($hasExistingData)
            <section class="rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-sm text-[var(--text-warning)] shadow-lg">
                <div class="font-semibold">Existing users/data were found.</div>
                <p class="mt-1">
                    This setup will not reset business data. It will only update the fixed dev/defuser accounts and installation settings after you confirm.
                </p>
            </section>
        @endif

        @if ($errors->any())
            <section class="rounded-lg border border-[var(--border-error)] bg-[var(--bg-error)] p-4 text-sm text-[var(--text-error)] shadow-lg">
                <div class="font-semibold">Please fix the highlighted fields before continuing.</div>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <form method="POST" action="{{ route('setup.store') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-[0.8fr_1.2fr]" data-readonly-allow>
            @csrf

            <aside class="{{ $panel }} p-5 md:p-6">
                <h2 class="text-lg font-semibold text-[var(--primary-color)]">Setup Steps</h2>
                <div class="mt-4 space-y-3">
                    <div class="{{ $softPanel }} p-3">
                        <div class="font-semibold">1. Accounts</div>
                        <p class="mt-1 text-xs text-[var(--secondary-text)]">Set passwords for dev and defuser. Passwords are saved only when you finish setup.</p>
                    </div>
                    <div class="{{ $softPanel }} p-3">
                        <div class="font-semibold">2. Business Details</div>
                        <p class="mt-1 text-xs text-[var(--secondary-text)]">Store company name, phone, and address using existing app settings.</p>
                    </div>
                    <div class="{{ $softPanel }} p-3">
                        <div class="font-semibold">3. License</div>
                        <p class="mt-1 text-xs text-[var(--secondary-text)]">
                            {{ $licensingEnabled ? 'Activation can be completed after setup.' : 'This build can continue without license activation.' }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-[var(--h-bg-color)] bg-[var(--bg-color)] p-3 text-xs text-[var(--secondary-text)]">
                    Setup is local/LAN only. No HTTPS or local DNS is required for this installation.
                </div>
            </aside>

            <section class="{{ $panel }} p-5 md:p-6">
                <div class="grid grid-cols-1 gap-6">
                    <section>
                        <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-[var(--primary-color)]">Developer Account</h2>
                                <p class="text-sm text-[var(--secondary-text)]">Username: <strong>dev</strong></p>
                            </div>
                            <span class="text-xs text-[var(--secondary-text)]">Minimum 4 characters</span>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="{{ $label }}" for="dev_password">Password</label>
                                <input class="{{ $input }}" id="dev_password" name="dev_password" type="password" required minlength="4">
                                @error('dev_password') <div class="mt-1 text-[var(--text-error)]">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}" for="dev_password_confirmation">Confirm Password</label>
                                <input class="{{ $input }}" id="dev_password_confirmation" name="dev_password_confirmation" type="password" required minlength="4">
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-[var(--primary-color)]">Default App Admin</h2>
                                <p class="text-sm text-[var(--secondary-text)]">Username: <strong>defuser</strong> | Role: <strong>owner</strong></p>
                            </div>
                            <span class="text-xs text-[var(--secondary-text)]">Minimum 4 characters</span>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="{{ $label }}" for="admin_password">Password</label>
                                <input class="{{ $input }}" id="admin_password" name="admin_password" type="password" required minlength="4">
                                @error('admin_password') <div class="mt-1 text-[var(--text-error)]">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}" for="admin_password_confirmation">Confirm Password</label>
                                <input class="{{ $input }}" id="admin_password_confirmation" name="admin_password_confirmation" type="password" required minlength="4">
                            </div>
                        </div>
                    </section>

                    <section>
                        <h2 class="text-lg font-semibold text-[var(--primary-color)]">Company / Business Details</h2>
                        <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="{{ $label }}" for="company_name">Company Name</label>
                                <input class="{{ $input }}" id="company_name" name="company_name" value="{{ old('company_name', $client_company->name) }}" required maxlength="120">
                                @error('company_name') <div class="mt-1 text-[var(--text-error)]">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="{{ $label }}" for="phone">Phone</label>
                                <input class="{{ $input }}" id="phone" name="phone" value="{{ old('phone', $client_company->phone_number ?? '') }}" maxlength="120">
                            </div>
                            <div class="md:col-span-2">
                                <label class="{{ $label }}" for="address">Address</label>
                                <textarea class="{{ $input }}" id="address" name="address" rows="2" maxlength="500">{{ old('address') }}</textarea>
                            </div>
                        </div>
                    </section>

                    <section class="{{ $softPanel }} p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-[var(--primary-color)]">License Check</h2>
                                <p class="mt-1 text-sm text-[var(--secondary-text)]">
                                    @if (!$licensingEnabled)
                                        This installation can continue without license activation. Licensing is prepared in the app, but enforcement is currently disabled for this build.
                                    @else
                                        License activation is required for this installation. You can activate online/offline from the developer license page after setup if activation is not available here yet.
                                    @endif
                                </p>
                                @if (!$licensingEnabled)
                                    <p class="mt-2 text-xs text-[var(--secondary-text)]">
                                        When SparkPair enables licensing later, expired licenses will put the app into read-only mode instead of blocking existing data access.
                                    </p>
                                @endif
                            </div>
                            <span class="rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-2.5 py-1 text-xs font-semibold text-[var(--secondary-text)]">
                                {{ $licensingEnabled ? 'Required' : 'Off for this build' }}
                            </span>
                        </div>
                    </section>

                    @if ($hasExistingData)
                        <div class="rounded-lg border-2 border-[var(--border-warning)] bg-[var(--bg-warning)] p-4 text-[var(--text-warning)]">
                            <label class="flex items-start gap-3">
                                <input id="existing_install_confirmed" type="checkbox" name="existing_install_confirmed" value="1" class="mt-1" required>
                                <span>
                                    <span class="block font-semibold">Required confirmation for existing data</span>
                                    <span class="mt-1 block">I understand existing data was found. Continue safe setup without resetting or overwriting business records.</span>
                                </span>
                            </label>
                            @error('existing_install_confirmed')
                                <div class="mt-2 rounded-lg border border-[var(--border-error)] bg-[var(--bg-error)] px-3 py-2 text-sm text-[var(--text-error)]">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="flex flex-col gap-2 border-t border-[var(--h-bg-color)] pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-[var(--secondary-text)]">This will not reset business data. It only saves setup settings and dev/defuser passwords.</p>
                        <button type="submit" class="rounded-lg bg-[var(--primary-color)] px-5 py-2 font-medium text-[var(--text-color)] transition hover:bg-[var(--h-primary-color)]">
                            Finish Setup &amp; Go to Login
                        </button>
                    </div>
                </div>
            </section>
        </form>
    </div>
@endsection
