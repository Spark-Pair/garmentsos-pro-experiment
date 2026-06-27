@extends('app')

@section('title', 'Developer Settings | ' . $client_company->name)

@section('content')
    @php
        $routeBlockedModules = ['articles', 'customers', 'suppliers', 'reports', 'rates'];
        $panel = 'bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-7 border border-[var(--h-bg-color)] pt-12 relative overflow-hidden';
        $softPanel = 'rounded-lg border border-[var(--h-bg-color)] bg-[var(--h-bg-color)]/50 p-4';
        $badge = 'inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-semibold';
        $primaryButton = 'px-4 py-2 bg-[var(--primary-color)] text-[var(--text-color)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $secondaryButton = 'px-4 py-2 bg-[var(--h-bg-color)] border border-gray-600 text-[var(--secondary-text)] font-medium text-nowrap rounded-lg hover:bg-[var(--secondary-bg-color)] hover:scale-95 transition-all duration-300 ease-in-out cursor-pointer';
        $inputClass = 'w-full rounded-lg bg-[var(--h-bg-color)] border border-gray-600 text-[var(--text-color)] px-3 py-2 transition-all duration-300 ease-in-out focus:ring-1 focus:ring-primary focus:border-transparent';
    @endphp

    <div class="mb-5 max-w-6xl mx-auto">
        <x-search-header heading="Developer Settings" />
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

        @if ($errors->any())
            <div class="rounded-lg border border-[var(--border-error)] bg-[var(--bg-error)] px-4 py-3 text-sm text-[var(--text-error)]">
                {{ $errors->first() }}
            </div>
        @endif

        <section id="branding" class="{{ $panel }}">
            <x-form-title-bar title="Branding" />

            <div class="mb-4 flex items-center justify-between gap-3">
                <p class="text-sm text-[var(--secondary-text)]">Text/color values use current config defaults when no override exists.</p>
                <span class="{{ $badge }} border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]">Safe text/color only</span>
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach ($branding as $item)
                        @php
                            $isColor = str_contains($item['key'], 'color');
                        @endphp
                        <div class="{{ $softPanel }}">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold capitalize">{{ str_replace('_', ' ', $item['key']) }}</h3>
                                    <p class="font-mono text-xs text-[var(--secondary-text)]">{{ $item['key'] }}</p>
                                </div>
                                <span class="{{ $badge }} border-gray-600 bg-[var(--secondary-bg-color)] text-[var(--secondary-text)]">{{ str_replace('_', ' ', $item['source']) }}</span>
                            </div>

                            <div class="mb-3 grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <div class="text-[var(--secondary-text)]">Default</div>
                                    <div>{{ $item['default'] ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-[var(--secondary-text)]">Effective</div>
                                    <div class="flex items-center gap-2">
                                        @if ($isColor)
                                            <span class="inline-block h-4 w-4 rounded border border-gray-600" style="background: {{ $item['effective_value'] ?: '#000000' }}"></span>
                                        @endif
                                        <span>{{ $item['effective_value'] ?: '-' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 sm:flex-row">
                                <form method="POST" action="{{ route('developer.settings.branding.save') }}" class="flex flex-1 flex-col gap-2 sm:flex-row">
                                    @csrf
                                    <input type="hidden" name="key" value="{{ $item['key'] }}">
                                    <input
                                        type="{{ $isColor ? 'color' : 'text' }}"
                                        name="value"
                                        value="{{ $item['value'] }}"
                                        maxlength="120"
                                        class="{{ $inputClass }} {{ $isColor ? 'h-10 w-20 p-1' : '' }}"
                                    >
                                    <button type="submit" class="{{ $primaryButton }}">Save</button>
                                </form>
                                <form method="POST" action="{{ route('developer.settings.branding.reset', $item['key']) }}">
                                    @csrf
                                    <button type="submit" class="{{ $secondaryButton }}">Reset</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>

                <aside class="{{ $softPanel }}">
                    <h3 class="font-semibold">Preview</h3>
                    <p class="mt-1 text-xs text-[var(--secondary-text)]">Safe text/color overrides only. Logo upload and print templates are separate phases.</p>
                    <div class="mt-4 rounded-lg border border-gray-600 bg-[var(--secondary-bg-color)] p-4">
                        <div class="font-semibold">{{ $branding['app_name']['effective_value'] ?? 'GarmentsOS PRO' }}</div>
                        <div class="text-sm text-[var(--secondary-text)]">{{ $branding['company_name']['effective_value'] ?? $client_company->name }}</div>
                        <div class="mt-4 flex gap-2">
                            <span class="inline-block h-6 w-6 rounded-lg border border-gray-600" style="background: {{ $branding['theme_primary_color']['effective_value'] ?? '#2563eb' }}"></span>
                            <span class="inline-block h-6 w-6 rounded-lg border border-gray-600" style="background: {{ $branding['theme_secondary_color']['effective_value'] ?? '#1f2937' }}"></span>
                            <span class="inline-block h-6 w-6 rounded-lg border border-gray-600" style="background: {{ $branding['theme_accent_color']['effective_value'] ?? '#2563eb' }}"></span>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section id="modules" class="{{ $panel }}">
            <x-form-title-bar title="Modules" />

            <p class="mb-4 text-sm text-[var(--secondary-text)]">
                Only reviewed modules have active route blocking. Foundation-only modules are shown for planning and are not enforced yet.
            </p>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($modules as $module)
                    @php
                        $isGuarded = in_array($module['key'], $routeBlockedModules, true);
                        $enabledClass = $module['effective_enabled']
                            ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]'
                            : 'border-[var(--border-error)] bg-[var(--bg-error)] text-[var(--text-error)]';
                    @endphp
                    <article class="{{ $softPanel }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">{{ $module['label'] }}</h3>
                                <p class="font-mono text-xs text-[var(--secondary-text)]">{{ $module['key'] }}</p>
                            </div>
                            <span class="{{ $badge }} {{ $enabledClass }}">{{ $module['effective_enabled'] ? 'Enabled' : 'Disabled' }}</span>
                        </div>

                        <p class="mt-3 text-sm text-[var(--secondary-text)]">{{ $module['description'] }}</p>

                        <dl class="mt-4 grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <dt class="text-[var(--secondary-text)]">Sidebar</dt>
                                <dd>{{ $module['effective_visible_in_sidebar'] ? 'Visible' : 'Hidden' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[var(--secondary-text)]">License</dt>
                                <dd>{{ is_null($module['license_allowed']) ? 'Unrestricted' : ($module['license_allowed'] ? 'Allowed' : 'Restricted') }}</dd>
                            </div>
                            <div>
                                <dt class="text-[var(--secondary-text)]">Local</dt>
                                <dd>{{ is_null($module['local_enabled']) ? 'Default' : ($module['local_enabled'] ? 'Enabled' : 'Disabled') }}</dd>
                            </div>
                            <div>
                                <dt class="text-[var(--secondary-text)]">Route block</dt>
                                <dd>{{ $isGuarded ? 'Reviewed: route blocking active' : 'Foundation only' }}</dd>
                            </div>
                        </dl>

                        <p class="mt-3 text-xs text-[var(--secondary-text)]">Reason: {{ str_replace('_', ' ', $module['reason']) }}</p>

                        @if ($isGuarded)
                            <form method="POST" action="{{ route('developer.settings.modules.save') }}" class="mt-4 space-y-3">
                                @csrf
                                <input type="hidden" name="module_key" value="{{ $module['key'] }}">
                                <input type="hidden" name="enabled" value="0">
                                <input type="hidden" name="visible_in_sidebar" value="0">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="enabled" value="1" @checked($module['enabled']) class="h-4 w-4 rounded border-gray-600 bg-[var(--bg-color)]">
                                    Enabled
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="visible_in_sidebar" value="1" @checked($module['visible_in_sidebar']) class="h-4 w-4 rounded border-gray-600 bg-[var(--bg-color)]">
                                    Show in sidebar
                                </label>
                                <button type="submit" class="{{ $primaryButton }}">Save {{ $module['label'] }}</button>
                            </form>
                        @else
                            <p class="mt-4 rounded-lg border border-gray-600 px-3 py-2 text-xs text-[var(--secondary-text)]">
                                Foundation only / not enforced yet.
                            </p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section id="labels" class="{{ $panel }}">
            <x-form-title-bar title="Labels" />

            <div class="overflow-x-auto">
                <table class="w-full min-w-[48rem] text-sm">
                    <thead class="bg-[var(--h-bg-color)]">
                        <tr>
                            <th class="p-3 text-left">Key</th>
                            <th class="p-3 text-left">Default</th>
                            <th class="p-3 text-left">Current</th>
                            <th class="p-3 text-left">Override</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-600/40">
                        @foreach ($labels as $key => $default)
                            <tr>
                                <td class="p-3 font-mono text-xs">{{ $key }}</td>
                                <td class="p-3">{{ $default }}</td>
                                <td class="p-3">{{ label_text($key, $default) }}</td>
                                <td class="p-3">
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <form method="POST" action="{{ route('developer.settings.labels.save') }}" class="flex flex-1 flex-col gap-2 sm:flex-row">
                                            @csrf
                                            <input type="hidden" name="label_key" value="{{ $key }}">
                                            <input type="text" name="override_text" value="{{ label_text($key, $default) }}" maxlength="80" class="{{ $inputClass }}">
                                            <button type="submit" class="{{ $primaryButton }}">Save</button>
                                        </form>
                                        <form method="POST" action="{{ route('developer.settings.labels.reset', $key) }}">
                                            @csrf
                                            <button type="submit" class="{{ $secondaryButton }}">Reset</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section id="features" class="{{ $panel }}">
            <x-form-title-bar title="Feature Flags" />

            <p class="mb-4 text-sm text-[var(--secondary-text)]">
                Feature flags are foundation-only unless a route/action explicitly uses them.
            </p>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($features as $feature)
                    @php
                        $featureClass = $feature['effective_enabled']
                            ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]'
                            : 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]';
                    @endphp
                    <article class="{{ $softPanel }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">{{ $feature['label'] }}</h3>
                                <p class="font-mono text-xs text-[var(--secondary-text)]">{{ $feature['key'] }}</p>
                            </div>
                            <span class="{{ $badge }} {{ $featureClass }}">{{ $feature['effective_enabled'] ? 'Enabled' : 'Off' }}</span>
                        </div>
                        <p class="mt-3 text-sm text-[var(--secondary-text)]">{{ $feature['description'] }}</p>
                        <dl class="mt-4 grid grid-cols-3 gap-2 text-xs">
                            <div>
                                <dt class="text-[var(--secondary-text)]">License</dt>
                                <dd>{{ is_null($feature['license_allowed']) ? 'Unrestricted' : ($feature['license_allowed'] ? 'Allowed' : 'Restricted') }}</dd>
                            </div>
                            <div>
                                <dt class="text-[var(--secondary-text)]">Local</dt>
                                <dd>{{ is_null($feature['local_enabled']) ? 'Default' : ($feature['local_enabled'] ? 'Enabled' : 'Disabled') }}</dd>
                            </div>
                            <div>
                                <dt class="text-[var(--secondary-text)]">Reason</dt>
                                <dd>{{ str_replace('_', ' ', $feature['reason']) }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
@endsection
