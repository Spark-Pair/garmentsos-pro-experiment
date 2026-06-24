@extends('app')

@section('title', 'Developer Settings | ' . $client_company->name)

@section('content')
    <div class="p-4 md:p-6 space-y-6 text-[var(--text-color)]">
        <div>
            <h1 class="text-xl md:text-2xl font-semibold">Developer Settings</h1>
            <p class="text-sm text-[var(--secondary-text)] mt-1">
                Local settings foundation. Phase 5B route enforcement is wired only for Articles.
            </p>
        </div>

        @if (session('success'))
            <div class="rounded-md bg-green-100 text-green-800 px-4 py-3 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-md bg-red-100 text-red-800 px-4 py-3 text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md bg-red-100 text-red-800 px-4 py-3 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Labels</h2>
            <div class="overflow-x-auto rounded-md border border-gray-300 dark:border-gray-700">
                <table class="min-w-full text-sm">
                    <thead class="bg-[var(--secondary-bg-color)] text-left">
                        <tr>
                            <th class="px-3 py-2 font-semibold">Key</th>
                            <th class="px-3 py-2 font-semibold">Default</th>
                            <th class="px-3 py-2 font-semibold">Current</th>
                            <th class="px-3 py-2 font-semibold">Override</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($labels as $key => $default)
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">{{ $key }}</td>
                                <td class="px-3 py-2">{{ $default }}</td>
                                <td class="px-3 py-2">{{ label_text($key, $default) }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-col gap-2 md:flex-row">
                                        <form method="POST" action="{{ route('developer.settings.labels.save') }}" class="flex gap-2">
                                            @csrf
                                            <input type="hidden" name="label_key" value="{{ $key }}">
                                            <input
                                                type="text"
                                                name="override_text"
                                                value="{{ label_text($key, $default) }}"
                                                maxlength="80"
                                                class="w-48 rounded-md border border-gray-300 bg-[var(--bg-color)] px-3 py-2 text-sm text-[var(--text-color)]"
                                            >
                                            <button type="submit" class="rounded-md bg-[var(--primary-color)] px-3 py-2 text-white">
                                                Save
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('developer.settings.labels.reset', $key) }}">
                                            @csrf
                                            <button type="submit" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                                                Reset
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Branding Text</h2>
            <div class="overflow-x-auto rounded-md border border-gray-300 dark:border-gray-700">
                <table class="min-w-full text-sm">
                    <thead class="bg-[var(--secondary-bg-color)] text-left">
                        <tr>
                            <th class="px-3 py-2 font-semibold">Key</th>
                            <th class="px-3 py-2 font-semibold">Default</th>
                            <th class="px-3 py-2 font-semibold">Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($branding as $item)
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs">{{ $item['key'] }}</td>
                                <td class="px-3 py-2">{{ $item['default'] }}</td>
                                <td class="px-3 py-2">
                                    <form method="POST" action="{{ route('developer.settings.branding.save') }}" class="flex gap-2">
                                        @csrf
                                        <input type="hidden" name="key" value="{{ $item['key'] }}">
                                        <input
                                            type="text"
                                            name="value"
                                            value="{{ $item['value'] }}"
                                            maxlength="120"
                                            class="w-56 rounded-md border border-gray-300 bg-[var(--bg-color)] px-3 py-2 text-sm text-[var(--text-color)]"
                                        >
                                        <button type="submit" class="rounded-md bg-[var(--primary-color)] px-3 py-2 text-white">
                                            Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Modules</h2>
            <div class="grid gap-3 md:grid-cols-3">
                @foreach ($modules as $module)
                    <div class="rounded-md border border-gray-300 dark:border-gray-700 p-4">
                        <div class="font-semibold">{{ $module['label'] }}</div>
                        <div class="mt-1 text-xs font-mono">{{ $module['key'] }}</div>
                        <div class="mt-2 text-sm text-[var(--secondary-text)]">{{ $module['description'] }}</div>
                        <div class="mt-3 text-sm">Effective enabled: {{ $module['effective_enabled'] ? 'Yes' : 'No' }}</div>
                        <div class="text-sm">Sidebar visible: {{ $module['effective_visible_in_sidebar'] ? 'Yes' : 'No' }}</div>
                        <div class="text-sm">License: {{ is_null($module['license_allowed']) ? 'Unrestricted' : ($module['license_allowed'] ? 'Allowed' : 'Restricted') }}</div>
                        <div class="text-sm">Local: {{ is_null($module['local_enabled']) ? 'Default' : ($module['local_enabled'] ? 'Enabled' : 'Disabled') }}</div>
                        <div class="text-xs text-[var(--secondary-text)]">Reason: {{ $module['reason'] }}</div>
                        @if ($module['reason'] === 'disabled_by_license' && $module['local_enabled'] === true)
                            <div class="mt-2 rounded-md bg-yellow-100 px-3 py-2 text-xs text-yellow-800">
                                Local settings cannot enable a module that is restricted by the active license.
                            </div>
                        @endif
                        <div class="text-xs text-[var(--secondary-text)]">
                            {{ in_array($module['key'], ['articles', 'customers', 'suppliers'], true) ? 'Route blocking is enabled for this module.' : 'Route blocking is not wired for this module yet.' }}
                        </div>
                        @if (in_array($module['key'], ['articles', 'customers', 'suppliers'], true))
                            <form method="POST" action="{{ route('developer.settings.modules.save') }}" class="mt-3 space-y-2">
                                @csrf
                                <input type="hidden" name="module_key" value="{{ $module['key'] }}">
                                <input type="hidden" name="enabled" value="0">
                                <input type="hidden" name="visible_in_sidebar" value="0">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="enabled" value="1" @checked($module['enabled'])>
                                    Enabled
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="visible_in_sidebar" value="1" @checked($module['visible_in_sidebar'])>
                                    Show in sidebar
                                </label>
                                <button type="submit" class="rounded-md bg-[var(--primary-color)] px-3 py-2 text-sm text-white">
                                    Save {{ $module['label'] }} Module
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Feature Flags</h2>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($features as $feature)
                    <div class="rounded-md border border-gray-300 dark:border-gray-700 p-4">
                        <div class="font-semibold">{{ $feature['label'] }}</div>
                        <div class="mt-1 text-xs font-mono">{{ $feature['key'] }}</div>
                        <div class="mt-2 text-sm text-[var(--secondary-text)]">{{ $feature['description'] }}</div>
                        <div class="mt-3 text-sm">Effective enabled: {{ $feature['effective_enabled'] ? 'Yes' : 'No' }}</div>
                        <div class="text-sm">License: {{ is_null($feature['license_allowed']) ? 'Unrestricted' : ($feature['license_allowed'] ? 'Allowed' : 'Restricted') }}</div>
                        <div class="text-sm">Local: {{ is_null($feature['local_enabled']) ? 'Default' : ($feature['local_enabled'] ? 'Enabled' : 'Disabled') }}</div>
                        <div class="text-xs text-[var(--secondary-text)]">Reason: {{ $feature['reason'] }}</div>
                        <div class="text-xs text-[var(--secondary-text)]">Feature enforcement is foundation-only unless explicitly wired to a reviewed route.</div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection
