@extends('app')

@section('title', 'Developer Settings | ' . $client_company->name)

@section('content')
    @php
        $routeBlockedModules = ['articles', 'customers', 'suppliers', 'reports', 'rates'];
        $badge = 'inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-semibold';
        $panel = 'bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow-sm';
        $mutedPanel = 'bg-[var(--glass-border-color)]/5 border border-[var(--glass-border-color)]/10 rounded-lg';
        $primaryButton = 'inline-flex items-center justify-center rounded-lg bg-[var(--primary-color)] px-3 py-2 text-sm font-semibold text-white transition-all duration-300 ease-in-out hover:bg-[var(--h-primary-color)] hover:scale-[0.98]';
        $secondaryButton = 'inline-flex items-center justify-center rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--h-bg-color)] px-3 py-2 text-sm text-[var(--text-color)] transition-all duration-300 ease-in-out hover:bg-[var(--secondary-bg-color)] hover:scale-[0.98]';
        $inputClass = 'w-full rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--bg-color)] px-3 py-2 text-sm text-[var(--text-color)] outline-none focus:border-[var(--primary-color)]';
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Developer Settings" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar title="Developer Settings" />

            <div class="h-full overflow-y-auto my-scrollbar-2 px-4 pb-5 pt-12 text-left text-[var(--text-color)]">
                <div class="mb-4 flex flex-col gap-3 rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--glass-border-color)]/5 p-4 md:flex-row md:items-center md:justify-between">
                    <p class="max-w-3xl text-sm text-[var(--secondary-text)]">
                        Local settings for branding, labels, reviewed module route blocking, and feature foundations. Missing settings fall back to the current application defaults.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <a href="#branding" class="{{ $secondaryButton }}">Branding</a>
                        <a href="#modules" class="{{ $secondaryButton }}">Modules</a>
                        <a href="#labels" class="{{ $secondaryButton }}">Labels</a>
                        <a href="#features" class="{{ $secondaryButton }}">Features</a>
                    </div>
                </div>

                @if (session('success'))
                    <div class="mb-4 rounded-lg border border-[var(--border-success)] bg-[var(--bg-success)] px-4 py-3 text-sm text-[var(--text-success)]">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 rounded-lg border border-[var(--border-error)] bg-[var(--bg-error)] px-4 py-3 text-sm text-[var(--text-error)]">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-[var(--border-error)] bg-[var(--bg-error)] px-4 py-3 text-sm text-[var(--text-error)]">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="space-y-4">

        <section id="branding" class="{{ $panel }} overflow-hidden">
            <div class="border-b border-[var(--glass-border-color)]/10 p-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Branding</h2>
                        <p class="mt-1 text-sm text-[var(--secondary-text)]">
                            Text and color overrides only. Logo uploads, arbitrary paths, favicon, app icons, and print templates are intentionally separate.
                        </p>
                    </div>
                    <span class="{{ $badge }} border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]">Safe text/color only</span>
                </div>
            </div>

            <div class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="grid gap-3">
                    @foreach ($branding as $item)
                        @php
                            $isColor = str_contains($item['key'], 'color');
                        @endphp
                        <article class="{{ $mutedPanel }} p-4">
                            <div class="grid gap-4 lg:grid-cols-[minmax(12rem,1fr)_minmax(14rem,1.2fr)] lg:items-center">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-semibold">{{ str_replace('_', ' ', $item['key']) }}</h3>
                                        <span class="{{ $badge }} border-[var(--glass-border-color)]/20 bg-[var(--glass-border-color)]/5 text-[var(--secondary-text)]">
                                            {{ str_replace('_', ' ', $item['source']) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 font-mono text-xs text-[var(--secondary-text)]">{{ $item['key'] }}</p>
                                    <div class="mt-3 grid gap-2 text-xs md:grid-cols-2">
                                        <div>
                                            <div class="text-[var(--secondary-text)]">Default</div>
                                            <div>{{ $item['default'] ?: '-' }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[var(--secondary-text)]">Effective</div>
                                            <div class="flex items-center gap-2">
                                                @if ($isColor)
                                                    <span class="inline-block h-4 w-4 rounded border border-[var(--glass-border-color)]/20" style="background: {{ $item['effective_value'] ?: '#000000' }}"></span>
                                                @endif
                                                <span>{{ $item['effective_value'] ?: '-' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row lg:justify-end">
                                    <form method="POST" action="{{ route('developer.settings.branding.save') }}" class="flex flex-1 flex-col gap-2 sm:flex-row lg:flex-none">
                                        @csrf
                                        <input type="hidden" name="key" value="{{ $item['key'] }}">
                                        <input
                                            type="{{ $isColor ? 'color' : 'text' }}"
                                            name="value"
                                            value="{{ $item['value'] }}"
                                            maxlength="120"
                                            class="{{ $inputClass }} {{ $isColor ? 'h-10 w-20 p-1' : 'sm:w-56' }}"
                                        >
                                        <button type="submit" class="{{ $primaryButton }}">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('developer.settings.branding.reset', $item['key']) }}">
                                        @csrf
                                        <button type="submit" class="{{ $secondaryButton }}">Reset</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <aside class="{{ $mutedPanel }} p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-[var(--secondary-text)]">Preview</p>
                    <div class="mt-3 rounded-xl border border-[var(--glass-border-color)]/20 bg-[var(--secondary-bg-color)] p-4">
                        <div class="text-lg font-semibold">{{ $branding['app_name']['effective_value'] ?? 'GarmentsOS PRO' }}</div>
                        <div class="text-sm text-[var(--secondary-text)]">{{ $branding['company_name']['effective_value'] ?? $client_company->name }}</div>
                        <div class="mt-4 flex items-center gap-2">
                            <span class="inline-block h-6 w-6 rounded-lg border border-[var(--glass-border-color)]/20" style="background: {{ $branding['theme_primary_color']['effective_value'] ?? '#2563eb' }}"></span>
                            <span class="inline-block h-6 w-6 rounded-lg border border-[var(--glass-border-color)]/20" style="background: {{ $branding['theme_secondary_color']['effective_value'] ?? '#1f2937' }}"></span>
                            <span class="inline-block h-6 w-6 rounded-lg border border-[var(--glass-border-color)]/20" style="background: {{ $branding['theme_accent_color']['effective_value'] ?? '#2563eb' }}"></span>
                        </div>
                    </div>
                    <p class="mt-3 text-xs leading-5 text-[var(--secondary-text)]">
                        Invalid colors and HTML are rejected before save. Empty values fall back to config/defaults.
                    </p>
                </aside>
            </div>
        </section>

        <section id="modules" class="{{ $panel }} overflow-hidden">
            <div class="border-b border-[var(--glass-border-color)]/10 p-5">
                <h2 class="text-lg font-semibold">Modules</h2>
                <p class="mt-1 text-sm text-[var(--secondary-text)]">
                    License restrictions win over local settings. Only reviewed modules have route blocking wired.
                </p>
            </div>

            <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($modules as $module)
                    @php
                        $isGuarded = in_array($module['key'], $routeBlockedModules, true);
                        $enabledClass = $module['effective_enabled']
                            ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]'
                            : 'border-[var(--border-error)] bg-[var(--bg-error)] text-[var(--text-error)]';
                    @endphp
                    <article class="{{ $mutedPanel }} p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">{{ $module['label'] }}</h3>
                                <p class="mt-1 font-mono text-xs text-[var(--secondary-text)]">{{ $module['key'] }}</p>
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
                        @if ($module['reason'] === 'disabled_by_license' && $module['local_enabled'] === true)
                            <div class="mt-3 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] px-3 py-2 text-xs text-[var(--text-warning)]">
                                Local settings cannot enable a module restricted by the active license.
                            </div>
                        @endif
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
                            <p class="mt-4 rounded-lg border border-[var(--glass-border-color)]/10 px-3 py-2 text-xs text-[var(--secondary-text)]">
                                Foundation only / not enforced yet. This module is shown for future planning, but route blocking is not active.
                            </p>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section id="labels" class="{{ $panel }} overflow-hidden">
            <div class="border-b border-[var(--glass-border-color)]/10 p-5">
                <h2 class="text-lg font-semibold">Labels</h2>
                <p class="mt-1 text-sm text-[var(--secondary-text)]">
                    Short, plain-text UI label overrides. Missing values use the existing labels.
                </p>
            </div>
            <div class="grid gap-3 p-5 md:grid-cols-2">
                @foreach ($labels as $key => $default)
                    <article class="{{ $mutedPanel }} p-4">
                        <div class="flex flex-col gap-4">
                            <div>
                                <h3 class="font-semibold">{{ label_text($key, $default) }}</h3>
                                <p class="mt-1 font-mono text-xs text-[var(--secondary-text)]">{{ $key }}</p>
                                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <div class="text-[var(--secondary-text)]">Default</div>
                                        <div>{{ $default }}</div>
                                    </div>
                                    <div>
                                        <div class="text-[var(--secondary-text)]">Current</div>
                                        <div>{{ label_text($key, $default) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <form method="POST" action="{{ route('developer.settings.labels.save') }}" class="flex flex-1 flex-col gap-2 sm:flex-row">
                                    @csrf
                                    <input type="hidden" name="label_key" value="{{ $key }}">
                                    <input
                                        type="text"
                                        name="override_text"
                                        value="{{ label_text($key, $default) }}"
                                        maxlength="80"
                                        class="{{ $inputClass }} sm:w-48"
                                    >
                                    <button type="submit" class="{{ $primaryButton }}">Save</button>
                                </form>
                                <form method="POST" action="{{ route('developer.settings.labels.reset', $key) }}">
                                    @csrf
                                    <button type="submit" class="{{ $secondaryButton }}">Reset</button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section id="features" class="{{ $panel }} overflow-hidden">
            <div class="border-b border-[var(--glass-border-color)]/10 p-5">
                <h2 class="text-lg font-semibold">Feature Flags</h2>
                <p class="mt-1 text-sm text-[var(--secondary-text)]">
                    Foundation status only. Feature flags affect only routes/actions that explicitly use them.
                </p>
            </div>
            <div class="grid gap-4 p-5 md:grid-cols-2">
                @foreach ($features as $feature)
                    @php
                        $featureClass = $feature['effective_enabled']
                            ? 'border-[var(--border-success)] bg-[var(--bg-success)] text-[var(--text-success)]'
                            : 'border-[var(--border-warning)] bg-[var(--bg-warning)] text-[var(--text-warning)]';
                    @endphp
                    <article class="{{ $mutedPanel }} p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold">{{ $feature['label'] }}</h3>
                                <p class="mt-1 font-mono text-xs text-[var(--secondary-text)]">{{ $feature['key'] }}</p>
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
            </div>
        </div>
    </section>
@endsection
