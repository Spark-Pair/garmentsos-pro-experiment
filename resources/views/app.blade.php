@php
    $preferredTheme = Auth::check()
        ? Auth::user()->theme
        : (request()->cookie('theme')
            ?? (isset($_COOKIE['theme']) ? $_COOKIE['theme'] : (str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'Dark') ? 'dark' : 'light')));
    $rawBranding = $branding ?? [];
    $effectiveBranding = collect($rawBranding)
        ->mapWithKeys(fn ($value, $key) => [$key => is_array($value) ? ($value['effective_value'] ?? $value['value'] ?? null) : $value])
        ->all();
    $appName = $effectiveBranding['app_name'] ?? $client_company->logo_text ?? 'GarmentsOS PRO';
    $primaryColor = $effectiveBranding['theme_primary_color'] ?? '#2563eb';
    $secondaryColor = $effectiveBranding['theme_secondary_color'] ?? '#1f2937';
    $accentColor = $effectiveBranding['theme_accent_color'] ?? '#2563eb';

    $appConfig = [
        'authenticated' => Auth::check(),
        'homeUrl' => route('home'),
        'menuShortcuts' => Auth::check() ? (json_decode(Auth::user()->menu_shortcuts, true) ?? []) : [],
        'maxShortcutsLimit' => 7,
        'pusherEnabled' => $pusherEnabled,
        'pusherKey' => $pusherFrontend['key'] ?? null,
        'pusherCluster' => $pusherFrontend['cluster'] ?? null,
        'authUserId' => Auth::check() ? Auth::user()->id : null,
        'authUserRole' => Auth::check() ? Auth::user()->role : null,
        'routeIsLogin' => request()->is('login'),
        'routeIsSetup' => request()->is('setup'),
        'routeIsSubscriptionExpired' => request()->is('subscription-expired'),
        'routeIsOrdersCreate' => request()->is('orders/create'),
        'changeLayoutUrl' => request()->route()?->getActionMethod() === 'index' || request()->route()?->getActionMethod() === 'summary'
            ? route('change-data-layout')
            : null,
        'routeName' => request()->route()?->getName(),
        'companyLogoBase' => url('/') . '/',
        'branding' => $effectiveBranding,
        'notificationsUrl' => Auth::check() ? route('notifications.index') : null,
        'readonlySession' => !request()->is('login') && !request()->is('setup') && ((bool) session('license_readonly')),
    ];
    $centerMainContent = request()->is('/') || request()->is('login') || request()->is('setup') || request()->is('subscription-expired');
    $developerUpdateStatus = null;
    $developerLauncherHandoff = null;
    if (Auth::check() && Auth::user()->role === 'developer' && !request()->is('login') && !request()->is('setup')) {
        try {
            $releaseFeedService = app(\App\Services\Updater\ReleaseFeedService::class);
            $developerUpdateStatus = $releaseFeedService->checkConfiguredCached();
            if (!empty($developerUpdateStatus['update_available'])) {
                $developerLauncherHandoff = $releaseFeedService->launcherHandoff($developerUpdateStatus);
            }
        } catch (\Throwable) {
            $developerUpdateStatus = null;
            $developerLauncherHandoff = null;
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ $preferredTheme }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $primaryColor }}">
    <meta name="description" content="{{ $appName }} - Garments Business Management Solution">
    <link rel="manifest" href="/manifest.json">
    <title>@yield('title', $client_company->name)</title>
    <style>
        @font-face {
            font-family: 'Calibri';
            src: url('/calibri.ttf') format('truetype'); /* For TTF */
            font-weight: normal;
            font-style: normal;
        }

        /* color theme */
        :root {
            --bg-color: #111827;
            /* Default dark theme background */
            --h-bg-color: #374151;
            --secondary-bg-color: #1f2937;
            --h-secondary-bg-color: hsl(215, 28%, 13%);
            /* Default dark theme secondary background */
            --text-color: #ffffff;
            /* Default dark theme text color */
            --secondary-text: #d1d5db;
            /* Default dark theme secondary text */
            --primary-color: {{ $primaryColor }};
            --h-primary-color: #1f56cd;
            --brand-secondary-color: {{ $secondaryColor }};
            --brand-accent-color: {{ $accentColor }};
            /* Default dark theme primary color */
            --bg-warning: hsl(45, 50%, 30%);
            --bg-success: hsl(130, 50%, 30%);
            --bg-error: hsl(360, 50%, 30%);
            --border-warning: hsl(45, 100%, 45%);
            --border-success: hsl(130, 100%, 45%);
            --border-error: hsl(360, 100%, 45%);
            --text-warning: hsl(45, 30%, 95%);
            --text-success: hsl(130, 30%, 95%);
            --text-error: hsl(360, 30%, 95%);

            --h-bg-warning: hsl(45, 50%, 20%);
            --h-bg-success: hsl(130, 50%, 20%);
            --h-bg-error: hsl(360, 50%, 20%);

            --danger-color: hsl(0, 65%, 51%);
            --h-danger-color: hsl(0, 65%, 41%);
            --success-color: hsl(142, 65%, 36%);
            --h-success-color: hsl(142, 65%, 26%);

            --overlay-color: rgba(0, 0, 0, 0.438);
            --glass-border-color: #ffffff;
        }

        [data-theme='light'] {
            --bg-color: #ffffff;
            --h-bg-color: #d1d3d7;
            --secondary-bg-color: #eef0f2;
            --h-secondary-bg-color: hsl(0, 0%, 96%);
            --text-color: #1f2937;
            --secondary-text: #4b5563;
            --bg-warning: hsl(45, 100%, 80%);
            --bg-success: hsl(130, 100%, 80%);
            --bg-error: hsl(360, 100%, 80%);
            --h-bg-warning: hsl(45, 100%, 75%);
            --h-bg-success: hsl(130, 100%, 75%);
            --h-bg-error: hsl(360, 100%, 75%);
            --border-warning: hsl(45, 100%, 40%);
            --border-success: hsl(130, 100%, 40%);
            --border-error: hsl(360, 100%, 40%);
            --text-warning: hsl(45, 75%, 35%);
            --text-success: hsl(130, 75%, 35%);
            --text-error: hsl(360, 75%, 35%);
            --glass-border-color: #000000;
        }

        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
        }

        .bg-\[var\(--primary-color\)\] {
            color: #e2e8f0 !important;
        }

        .bg-\[var\(--primary-color\)\] svg {
            fill: #e2e8f0 !important;
        }

        .my-scrollbar-2 {
            overflow: auto; /* ensure it's scrollable itself */
        }

        /* Now target ONLY this element's own scrollbar */
        .my-scrollbar-2::-webkit-scrollbar,
        .my-scrollbar-2::-webkit-scrollbar-track,
        .my-scrollbar-2::-webkit-scrollbar-thumb,
        .my-scrollbar-2::-webkit-scrollbar-corner {
            all: unset;
        }

        .my-scrollbar-2::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .my-scrollbar-2::-webkit-scrollbar-track {
            background: var(--secondary-bg-color);
            border-radius: 8px;
        }

        .my-scrollbar-2::-webkit-scrollbar-thumb {
            background: linear-gradient(
                180deg,
                var(--primary-color),
                var(--h-primary-color)
            );
            border-radius: 8px;
            border: 2px solid var(--secondary-bg-color);
            transition: background 0.3s ease;
        }

        .my-scrollbar-2::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(
                180deg,
                var(--h-primary-color),
                var(--primary-color)
            );
        }

        .scrollbar-hidden::-webkit-scrollbar {
            display: none !important;
        }

        .fade-in {
            animation: fadeIn 0.35s ease-in-out;
        }

        .scale-in {
            animation: scaleIn 0.4s ease-in-out;
        }

        .scale-out {
            animation: scaleOut 0.4s ease-in-out;
        }

        /* Example animation */
        @keyframes fadeIn {
            0% {
                opacity: 0;
            }

            100% {
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            0% {
                opacity: 1;
            }

            100% {
                opacity: 0;
            }
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0.9);
            }
            60% {
                transform: scale(1.05);
            }
            80% {
                transform: scale(0.97);
            }
            100% {
                transform: scale(1);
            }
        }

        @keyframes scaleOut {
            0% {
                transform: scale(1);
            }
            30% {
                transform: scale(1.05);
            }
            60% {
                transform: scale(0.95);
            }
            100% {
                transform: scale(0);
            }
        }

        .fade-out {
            animation: fadeOut 0.35s forwards !important;
        }

        .opacity-zero {
            opacity: 0;
        }

        .opacity-transition {
            transition: opacity .2s linear;
        }

        #mobileMenu.is-open {
            transform: translateY(0) !important;
        }

        @media (max-width: 768px) {
            /* Allow horizontal scroll for A4 previews on small screens */
            #preview-container {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
            }
        }

        .card {
            transition: all 0.3s ease-in-out;
            position: relative;
        }

        .card:not(.no-translate):hover {
            transform: translateY(-0.3rem);
        }

        .card:hover {
            background-color: var(--h-secondary-bg-color);
            box-shadow: 0 5px 0.8rem var(--bg-color);
        }

        .card button {
            transition: all 0.2s ease-in-out;
        }

        .card:hover button {
            scale: 1.1;
        }

        .active_inactive_dot {
            opacity: 100;
            transition: all 0.2s ease-in-out;
        }

        .active_inactive {
            opacity: 0;
            transition: all 0.2s ease-in-out;
        }

        .card:hover .active_inactive {
            opacity: 100;
        }

        .card:hover .active_inactive_dot {
            opacity: 0;
        }

        .nav-link.active {
            background-color: var(--h-bg-color) !important;
        }

        .nav-link.active i {
            color: var(--primary-color) !important;
        }

        .nav-link.active svg {
            fill: var(--primary-color) !important;
        }

        :where(a, button, input, select, textarea, [role="button"], [tabindex]):focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 3px;
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary-color) 22%, transparent);
        }

        .nav-link:focus-visible,
        .dropdownMenu :where(a, button):focus-visible {
            background-color: var(--h-bg-color) !important;
            color: var(--primary-color) !important;
        }

        .nav-link.active:hover i {
            color: var(--h-primary-color) !important;
        }

        .nav-link.active:hover svg {
            fill: var(--h-primary-color) !important;
        }

        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type="number"] {
            -moz-appearance: textfield;
            /* For Firefox */
        }

        input[disabled] {
            cursor: not-allowed;
        }

        input[readonly] {
            background-color: transparent !important;
            pointer-events: none;
        }

        select[disabled] {
            cursor: not-allowed;
        }

        input::-webkit-calendar-picker-indicator {
            display: none !important;
            -webkit-appearance: none;
        }

        strong {
            font-weight: 600 !important;
        }

        span {
            color: var(--secondary-text) !important;
        }

        .negative-value,
        .negative-value * {
            color: var(--border-error) !important;
        }

        .open-dropdown:hover .open-dropdown-hover\:block {
            display: block;
        }

        input.row-checkbox:checked + input {
            opacity: 1 !important;
            pointer-events: all !important;
        }

        .switchBtn {
            display: flex;
            justify-content: left;
        }

        .switchBtn .circle {
            background-color: var(--bg-color);
        }

        .switchBtn.active {
            justify-content: right;
        }

        .switchBtn.active .circle {
            background-color: var(--secondary-text);
        }

        .selectParent:has(input:focus) .selectDropdownIcon {
            scale: 1 -1;
        }

        .td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #preview-container .td,
        #preview-container .th,
        .preview .td,
        .preview .th,
        .preview-document .td,
        .preview-document .th {
            min-width: 0;
            overflow: hidden !important;
            text-overflow: clip !important;
            white-space: nowrap !important;
            line-height: 1.05;
        }

        #preview-container .truncate,
        .preview .truncate,
        .preview-document .truncate {
            overflow: hidden !important;
            text-overflow: clip !important;
            white-space: nowrap !important;
        }
    </style>

    @vite('resources/css/app.css')
    @stack('page-styles')

    @if($pusherEnabled)
        <script defer src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    @endif
    <script defer src="{{ asset('jquery.js') }}"></script>
    <script defer src="{{ asset('js/validate-inputs.js') }}"></script>
    <script defer src="{{ asset('js/utils/format.js') }}"></script>
    <script defer src="{{ asset('js/utils/ui.js') }}"></script>
    <script defer src="{{ asset('js/utils/activity.js') }}"></script>
    <script defer src="{{ asset('js/utils/notifications.js') }}"></script>
    <script defer src="{{ asset('js/utils/loader.js') }}"></script>
    <script defer src="{{ asset('js/utils/table.js') }}"></script>
    <script defer src="{{ asset('js/utils/layout.js') }}"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
    <script defer src="{{ asset('js/utils/print-columns.js') }}"></script>
    <script defer src="{{ asset('js/utils/export-excel.js') }}"></script>
    <script defer src="{{ asset('js/utils/backup.js') }}"></script>
    <script defer src="{{ asset('js/utils/modal.js') }}"></script>
    <script defer src="{{ asset('js/components/sidebar.js') }}"></script>
    <script defer src="{{ asset('js/utils/menu-customization.js') }}"></script>
    <script defer src="{{ asset('js/utils/form-submit.js') }}"></script>
    <script defer src="{{ asset('js/utils/inputs.js') }}"></script>
    <script defer src="{{ asset('js/utils/navigation.js') }}"></script>
    <script defer src="{{ asset('js/utils/readonly.js') }}"></script>
    <script defer src="{{ asset('js/utils/amounts.js') }}"></script>
    @if($pusherEnabled)
        <script defer src="{{ asset('js/utils/pusher-notifications.js') }}"></script>
    @endif
    <script defer src="{{ asset('js/components/select.js') }}"></script>
    <script defer src="{{ asset('js/components/input.js') }}"></script>
    <script defer src="{{ asset('js/app-init.js') }}"></script>

    <script defer src="{{ asset('js/components/card.js') }}"></script>
    <script defer src="{{ asset('js/components/modal.js') }}"></script>
    <script defer src="{{ asset('js/components/context-menu.js') }}"></script>
    <script defer src="{{ asset('js/global-filter-manager.js') }}"></script>
</head>

<body class="bg-[var(--secondary-bg-color)] text-[var(--text-color)] text-sm min-h-screen flex flex-col md:flex-row items-stretch justify-start fade-in" cz-shortcut-listen="true" data-app-config='@json($appConfig)'>
    {{-- side bar --}}
    @if (Auth::check())
        @component('components.sidebar')
        @endcomponent
    @endif

    <!-- Loader -->
    <div id="page-loader" class="fixed inset-0 z-[9999] bg-[var(--overlay-color)] bg-opacity-80 flex items-center justify-center hidden">
        <div class="w-12 h-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
    </div>
    <div class="wrapper flex-1 min-w-0 min-h-screen md:h-screen flex flex-col relative w-full overflow-hidden">
        {{-- main content --}}
        <main class="flex-1 min-h-0 px-4 py-6 md:p-8 overflow-y-auto my-scrollbar-2 flex {{ $centerMainContent ? 'items-center' : 'items-start' }} justify-center bg-[var(--bg-color)] rounded-3xl mx-2.5 md:mr-2.5 {{ request()->is('login') ? 'mt-2.5 md:ml-2.5' : 'mt-0 md:ml-0' }} md:mt-2.5 relative">
            {{-- alert --}}
            <div id="messageBox" class="absolute top-5 mx-auto flex items-center flex-col space-y-3 z-[100] text-sm w-full select-none pointer-events-none">
                @if (session('info'))
                    <x-alert type="info" :messages="session('info')" />
                @endif

                @if (session('success'))
                    <x-alert type="success" :messages="session('success')" />
                @endif

                @if (session('warning'))
                    <x-alert type="warning" :messages="session('warning')" />
                @endif

                @if (session('error'))
                    <x-alert type="error" :messages="session('error')" />
                @endif
                @if (!request()->is('login') && !request()->is('setup') && session('license_readonly'))
                    <x-alert type="warning" :messages="'Read-only mode is enabled. You can view data but cannot make changes.'"/>
                @endif
            </div>
            <!-- Notification Box -->
            <div id="notificationBox" class="absolute top-5 right-5 flex flex-col space-y-3 z-[100] text-sm mx-auto items-end w-full select-none">
                {{-- <x-notification
                    title="Payment Method Expiring"
                    message="Your card ending in 1122 is expiring soon. Please update your billing info."
                    actionLabel="Update Card"
                    actionUrl="/billing"
                />
                <x-notification
                    title="Payment Method Expiring"
                    message="Your card ending in 1122 is expiring soon. Please update your billing info."
                /> --}}
            </div>
            <div class="left_actions absolute top-5 left-5 flex gap-2">
                <button id="go_back_button" type="button" aria-label="Go Back" class="border border-gray-600 group bg-[var(--bg-color)] h-full rounded-xl cursor-pointer flex items-center justify-end p-1 overflow-hidden hover:pr-3 transition-all duration-300 ease-in-out">
                    <div class="flex items-center justify-center bg-[var(--h-bg-color)] rounded-lg p-2">
                        <svg class="size-3 transition-all duration-300 ease-in-out group-hover:size-2.5 fill-[var(--secondary-text)]"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                        <path d="M19 12H5m6-6l-6 6 6 6" stroke="currentColor" stroke-width="2.5" fill="none"/>
                        </svg>
                    </div>
                    <span class="inline-block max-w-0 opacity-0 overflow-hidden whitespace-nowrap transition-all duration-300 ease-in-out group-hover:opacity-100 group-hover:max-w-[200px] group-hover:ml-2">
                        Go Back
                    </span>
                </button>
                <button id="refresh_button" type="button" aria-label="Refresh" class="border border-gray-600 group bg-[var(--bg-color)] h-full rounded-xl cursor-pointer flex items-center justify-end p-1 overflow-hidden hover:pr-3 transition-all duration-300 ease-in-out">
                    <div class="flex items-center justify-center bg-[var(--h-bg-color)] rounded-lg p-2">
                        <svg class="size-3 transition-all duration-300 ease-in-out group-hover:size-2.5 fill-[var(--secondary-text)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
                            <g>
                              <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/>
                              <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z" transform="translate(0.3, 0.3)" />
                            </g>
                        </svg>
                    </div>
                    <span class="inline-block max-w-0 opacity-0 overflow-hidden whitespace-nowrap transition-all duration-300 ease-in-out group-hover:opacity-100 group-hover:max-w-[200px] group-hover:ml-2">
                        Refresh
                    </span>
                </button>
            </div>
            <div class="main-child w-full grow pb-10">
                @if (!empty($developerUpdateStatus['update_available']))
                    <div class="mb-4 rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] px-4 py-3 text-sm text-[var(--text-warning)]">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <strong>Update available: {{ $developerUpdateStatus['latest_version'] ?? ($developerUpdateStatus['feed']['version'] ?? 'latest') }}</strong>
                                <div class="mt-1 text-xs">
                                    Laravel will prepare the update handoff only. The Windows launcher applies the update outside the running app.
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if (!empty($developerLauncherHandoff['protocol_url']))
                                    <a href="{{ $developerLauncherHandoff['protocol_url'] }}" class="rounded-lg border border-[var(--border-warning)] bg-[var(--h-bg-warning)] px-3 py-2 text-xs font-semibold text-[var(--text-warning)]">
                                        Update Now
                                    </a>
                                @endif
                                <a href="{{ route('developer.updater') }}" class="rounded-lg border border-[var(--border-warning)] bg-[var(--h-bg-warning)] px-3 py-2 text-xs font-semibold text-[var(--text-warning)]">
                                    Details
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
                @yield('content')
            </div>
        </main>

        {{-- footer --}}
        @component('components.footer')
        @endcomponent
    </div>

@stack('page-scripts')
</body>
</html>
