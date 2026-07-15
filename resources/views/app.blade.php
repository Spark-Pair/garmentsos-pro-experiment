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
    $activeUpdateLock = null;
    try {
        $activeUpdateLock = app(\App\Services\Updater\UpdateLockService::class)->activeLock();
    } catch (\Throwable) {
        $activeUpdateLock = null;
    }

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
        'updating' => $activeUpdateLock !== null,
    ];
    $developerUpdateStatus = null;
    $developerLauncherHandoff = null;
    $canManageUpdates = Auth::check() && in_array(Auth::user()->role, ['developer', 'admin'], true);
    if (Auth::check() && !request()->is('login') && !request()->is('setup')) {
        try {
            $releaseFeedService = app(\App\Services\Updater\ReleaseFeedService::class);
            $developerUpdateStatus = $releaseFeedService->checkConfiguredCached((int) config('updater.app_shell_feed_cache_seconds', 0));
            if ($canManageUpdates && !empty($developerUpdateStatus['update_available'])) {
                $developerLauncherHandoff = $releaseFeedService->launcherHandoff($developerUpdateStatus);
            }
        } catch (\Throwable) {
            $developerUpdateStatus = null;
            $developerLauncherHandoff = null;
        }
    }

    $currentBranchModuleKey = null;
    if (Auth::check() && !request()->is('login') && !request()->is('setup')) {
        try {
            $currentBranchModuleKey = app(\App\Services\Branches\BranchModuleRegistryService::class)
                ->moduleKeyForRoute(request()->route());
        } catch (\Throwable) {
            $currentBranchModuleKey = null;
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

        .gos-a5-document {
            box-sizing: border-box;
            padding: 8mm;
            font-size: 11.2px;
            line-height: 1.24;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .gos-a5-document hr {
            margin-top: 6px !important;
            margin-bottom: 6px !important;
        }

        .gos-a5-document #banner,
        .gos-a5-document .banner,
        .gos-a5-document #header,
        .gos-a5-document .header {
            padding-left: 14px !important;
            padding-right: 14px !important;
        }

        .gos-a5-document #banner,
        .gos-a5-document .banner {
            align-items: center !important;
        }

        .gos-a5-document .logo img {
            max-height: 36px !important;
            width: auto !important;
        }

        .gos-a5-document .logo .h-\[3\.50rem\] {
            height: 40px !important;
            width: 152px !important;
        }

        .gos-a5-document .logo .mt-2 {
            margin-top: 4px !important;
        }

        .gos-a5-document .text-2xl {
            font-size: 20.5px !important;
            line-height: 1.12 !important;
        }

        .gos-a5-document .text-lg {
            font-size: 14.5px !important;
            line-height: 1.15 !important;
        }

        .gos-a5-document .text-sm {
            font-size: 11.1px !important;
            line-height: 1.2 !important;
        }

        .gos-a5-document .customer {
            font-size: 14.7px !important;
            line-height: 1.15 !important;
        }

        .gos-a5-document .right,
        .gos-a5-document .date,
        .gos-a5-document .number,
        .gos-a5-document .preview-copy,
        .gos-a5-document .copy,
        .gos-a5-document .person,
        .gos-a5-document .address,
        .gos-a5-document .phone {
            font-size: 11.6px !important;
            line-height: 1.22 !important;
        }

        .gos-a5-document .right.space-y-1\.5 > :not([hidden]) ~ :not([hidden]),
        .gos-a5-document .left.space-y-1 > :not([hidden]) ~ :not([hidden]) {
            margin-top: 2px !important;
        }

        .gos-a5-document .body {
            padding-left: 14px !important;
            padding-right: 14px !important;
        }

        .gos-a5-document .grid-cols-9 {
            grid-template-columns:
                minmax(28px, 0.55fr)
                minmax(56px, 1.05fr)
                minmax(0, 1.3fr)
                minmax(0, 1.7fr)
                minmax(30px, 0.65fr)
                minmax(42px, 0.78fr)
                minmax(34px, 0.64fr)
                minmax(54px, 1fr)
                minmax(64px, 1.18fr) !important;
        }

        .gos-a5-document .grid-cols-8 {
            grid-template-columns:
                minmax(28px, 0.55fr)
                minmax(56px, 1.05fr)
                minmax(0, 1.4fr)
                minmax(0, 1.7fr)
                minmax(42px, 0.8fr)
                minmax(34px, 0.65fr)
                minmax(58px, 1.05fr)
                minmax(66px, 1.2fr) !important;
        }

        .gos-a5-document .th,
        .gos-a5-document .td {
            min-width: 0;
            align-items: center;
            display: flex;
            overflow: hidden !important;
            justify-content: center;
            text-align: center;
            font-size: 10.8px !important;
            line-height: 1.22 !important;
        }

        .gos-a5-document .thead .th:nth-child(3),
        .gos-a5-document .tbody .td:nth-child(3) {
            justify-content: flex-start;
            text-align: left;
        }

        .gos-a5-document .thead .th:last-child,
        .gos-a5-document .tbody .td:last-child,
        .gos-a5-document .thead .th:nth-last-child(2),
        .gos-a5-document .tbody .td:nth-last-child(2) {
            justify-content: flex-end;
            text-align: right;
        }

        .gos-a5-document .thead .tr {
            column-gap: 3px;
            min-height: 25px;
            padding: 4px 9px !important;
        }

        .gos-a5-document .tbody .tr {
            column-gap: 3px;
            min-height: 28px;
            padding: 4px 9px !important;
        }

        .gos-a5-document .tbody hr {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }

        .gos-a5-document .table.border {
            padding-bottom: 0 !important;
            border-radius: 6px !important;
        }

        .gos-a5-document > .flex > .grid.grid-cols-2 {
            gap: 7px !important;
            padding-left: 14px !important;
            padding-right: 14px !important;
        }

        .gos-a5-document .total {
            min-height: 25px;
            border-radius: 5px !important;
            font-size: 11px !important;
            line-height: 1.2 !important;
            padding: 5px 10px !important;
        }

        .gos-a5-document .total > div:first-child {
            min-width: 0;
            overflow: hidden;
            padding-right: 8px;
            text-overflow: ellipsis;
        }

        .gos-a5-document .total > div:last-child {
            min-width: 70px;
            text-align: right;
        }

        .gos-a5-document .footer,
        .gos-a5-document .tfooter {
            padding-left: 14px !important;
            padding-right: 14px !important;
            font-size: 10px !important;
            line-height: 1.2 !important;
        }

        .gos-a5-document.gos-a5-invoice {
            --gos-invoice-border: #4b5563;
            --gos-invoice-detail-bg: #f8fafc;
            box-sizing: border-box;
            padding: 4.25mm;
            font-size: 11px;
            line-height: 1.28;
        }

        .gos-a5-invoice hr {
            border-color: var(--gos-invoice-border) !important;
            border-top-width: 1px !important;
            margin-top: 5px !important;
            margin-bottom: 5px !important;
        }

        .gos-a5-invoice #banner,
        .gos-a5-invoice .banner,
        .gos-a5-invoice #header,
        .gos-a5-invoice .header,
        .gos-a5-invoice .body,
        .gos-a5-invoice > .flex > .grid.grid-cols-2,
        .gos-a5-invoice .footer,
        .gos-a5-invoice .tfooter {
            padding-left: 8px !important;
            padding-right: 8px !important;
        }

        .gos-a5-invoice .logo img {
            max-height: 40px !important;
        }

        .gos-a5-invoice .logo .h-\[3\.50rem\] {
            height: 44px !important;
            width: 166px !important;
        }

        .gos-a5-invoice .text-2xl {
            font-size: 19px !important;
            line-height: 1.12 !important;
        }

        .gos-a5-invoice .customer {
            font-size: 14px !important;
            font-weight: 700 !important;
            line-height: 1.18 !important;
        }

        .gos-a5-invoice .right,
        .gos-a5-invoice .date,
        .gos-a5-invoice .number,
        .gos-a5-invoice .preview-copy,
        .gos-a5-invoice .copy,
        .gos-a5-invoice .person,
        .gos-a5-invoice .address,
        .gos-a5-invoice .phone {
            font-size: 11px !important;
            line-height: 1.28 !important;
        }

        .gos-a5-invoice .grid-cols-9 {
            grid-template-columns:
                minmax(24px, 0.45fr)
                minmax(48px, 0.9fr)
                minmax(0, 1.35fr)
                minmax(0, 1.55fr)
                minmax(26px, 0.5fr)
                minmax(34px, 0.62fr)
                minmax(28px, 0.48fr)
                minmax(48px, 0.85fr)
                minmax(58px, 1fr) !important;
        }

        .gos-a5-invoice .grid-cols-7 {
            grid-template-columns:
                minmax(24px, 0.6fr)
                minmax(0, 3.1fr)
                minmax(30px, 0.72fr)
                minmax(34px, 0.78fr)
                minmax(30px, 0.72fr)
                minmax(52px, 1.45fr)
                minmax(66px, 1.75fr) !important;
        }

        .gos-a5-invoice .table.border,
        .gos-a5-invoice .total,
        .gos-a5-invoice .invoice-item-desc {
            border-color: var(--gos-invoice-border) !important;
            border-width: 1px !important;
        }

        .gos-a5-invoice .thead .tr {
            column-gap: 2px;
            min-height: 25px;
            padding: 5px 8px !important;
            border-bottom: 1px solid var(--gos-invoice-border);
        }

        .gos-a5-invoice .tbody .tr {
            column-gap: 2px;
            min-height: 26px;
            padding: 5px 8px 1px !important;
        }

        .gos-a5-invoice .th,
        .gos-a5-invoice .td {
            font-size: 10.9px !important;
            line-height: 1.25 !important;
        }

        .gos-a5-invoice .th {
            font-size: 10.4px !important;
            font-weight: 600 !important;
        }

        .gos-a5-invoice .grid-cols-7 .th:nth-child(2),
        .gos-a5-invoice .grid-cols-7 .td:nth-child(2) {
            justify-content: flex-start;
            text-align: left;
        }

        .gos-a5-invoice .grid-cols-7 .td:nth-child(2),
        .gos-a5-invoice .grid-cols-7 .td:nth-child(5),
        .gos-a5-invoice .grid-cols-7 .td:nth-child(6),
        .gos-a5-invoice .grid-cols-7 .td:nth-child(7) {
            font-weight: 600 !important;
        }

        .gos-a5-invoice .grid-cols-7 .th:nth-child(3),
        .gos-a5-invoice .grid-cols-7 .td:nth-child(3) {
            justify-content: center;
            text-align: center;
        }

        .gos-a5-invoice .grid-cols-7 .th:nth-last-child(-n + 2),
        .gos-a5-invoice .grid-cols-7 .td:nth-last-child(-n + 2) {
            justify-content: flex-end;
            text-align: right;
        }

        .gos-a5-invoice .tbody hr {
            display: none;
        }

        .gos-a5-invoice .invoice-item-row {
            border-top: 1px solid var(--gos-invoice-border);
        }

        .gos-a5-invoice .invoice-item-row:first-child {
            border-top: 0;
        }

        .gos-a5-invoice .invoice-item-main .td:nth-child(2) {
            white-space: normal !important;
            overflow-wrap: anywhere;
            word-break: normal;
        }

        .gos-a5-invoice .invoice-item-desc {
            background: var(--gos-invoice-detail-bg);
            border-left-style: solid;
            border-right-style: solid;
            border-top-style: solid;
            border-bottom-style: solid;
            border-radius: 3px;
            color: #111827;
            font-size: 10.5px;
            font-weight: 500;
            line-height: 1.3;
            margin: 1px 8px 6px 42px;
            overflow-wrap: anywhere;
            padding: 3px 7px;
            text-align: left;
            white-space: normal;
            word-break: normal;
        }

        .gos-a5-invoice .invoice-item-desc span {
            color: #374151;
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0;
            margin-right: 6px;
            text-transform: uppercase;
        }

        .gos-a5-invoice .total {
            min-height: 26px;
            font-size: 11px !important;
            padding: 5px 10px !important;
        }

        .gos-a5-invoice .total > div:last-child {
            font-weight: 600;
        }

        .gos-a5-invoice .total:last-child {
            font-weight: 700;
        }

        .gos-a5-invoice .total:last-child > div:last-child {
            font-weight: 700;
        }

        .gos-a5-invoice > .flex > .grid.grid-cols-2 {
            gap: 6px !important;
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
        <main class="flex-1 min-h-0 px-4 py-6 md:p-8 overflow-y-hidden my-scrollbar-2 flex items-center justify-center bg-[var(--bg-color)] rounded-3xl mx-2.5 md:mr-2.5 {{ request()->is('login') ? 'mt-2.5 md:ml-2.5' : 'mt-0 md:ml-0' }} md:mt-2.5 relative">
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
                @if ($currentBranchModuleKey)
                    <x-module-branch-selector :module-key="$currentBranchModuleKey" />
                @endif
                @stack('left-actions-after')
            </div>
            <div class="main-child w-full grow pb-10">
                @yield('content')
            </div>
        </main>

        {{-- footer --}}
        @component('components.footer')
        @endcomponent
    </div>

    @if (!empty($developerUpdateStatus['update_available']))
        @php
            $latestVersion = $developerUpdateStatus['latest_version'] ?? ($developerUpdateStatus['feed']['version'] ?? 'latest');
            $currentVersion = $developerUpdateStatus['current_version'] ?? config('app.version', 'Installed');
            $releaseNotes = $developerUpdateStatus['notes'] ?? data_get($developerUpdateStatus, 'feed.notes') ?? data_get($developerUpdateStatus, 'feed.body') ?? 'Laravel prepares the update handoff. The Windows launcher applies the update outside the running app.';
            $mandatoryUpdate = (bool) ($developerUpdateStatus['mandatory'] ?? data_get($developerUpdateStatus, 'feed.mandatory', false));
        @endphp
        <div id="developer-update-modal" class="fixed inset-0 z-[9998] flex items-center justify-center bg-[var(--overlay-color)] px-4">
            <div class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-xl border border-[var(--glass-border-color)]/20 bg-[var(--secondary-bg-color)] text-[var(--text-color)] shadow-2xl">
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-[var(--glass-border-color)]/10 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-semibold">Update available</h2>
                        <p class="mt-1 text-xs text-[var(--secondary-text)]">Current {{ $currentVersion }} · New {{ $latestVersion }}</p>
                    </div>
                    @if (!$mandatoryUpdate || !$canManageUpdates)
                        <button type="button" class="rounded-lg px-2 py-1 text-[var(--secondary-text)] hover:bg-[var(--h-bg-color)]" data-update-modal-close aria-label="Close update dialog">
                            <i class="fas fa-xmark"></i>
                        </button>
                    @endif
                </div>
                <div class="min-h-0 grow space-y-4 overflow-y-auto px-5 py-4 my-scrollbar-2">
                    <p class="text-sm text-[var(--secondary-text)]">
                        {{ $mandatoryUpdate ? 'This update is marked mandatory. Update before continuing if your deployment policy requires it.' : 'You can update now or continue working and update later.' }}
                    </p>
                    @unless ($canManageUpdates)
                        <div class="rounded-lg border border-[var(--border-warning)] bg-[var(--bg-warning)] p-3 text-sm text-[var(--text-warning)]">
                            Update is available. Please contact an admin or developer to apply it.
                        </div>
                    @endunless
                    <details class="rounded-lg border border-[var(--glass-border-color)]/10 bg-[var(--h-bg-color)] p-3 text-sm" open>
                        <summary class="cursor-pointer font-semibold">Details</summary>
                        <div class="mt-2 max-h-60 overflow-y-auto whitespace-pre-line rounded-lg pr-2 text-[var(--secondary-text)] my-scrollbar-2">{{ $releaseNotes }}</div>
                    </details>
                </div>
                <div class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-[var(--glass-border-color)]/10 px-5 py-4">
                    @if (!$mandatoryUpdate || !$canManageUpdates)
                        <button type="button" class="rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-4 py-2 text-sm font-semibold text-[var(--secondary-text)]" data-update-modal-close>
                            Later
                        </button>
                    @endif
                    @if ($canManageUpdates)
                        <a href="{{ route('developer.updater') }}" class="rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-4 py-2 text-sm font-semibold text-[var(--secondary-text)]">
                            Details
                        </a>
                    @endif
                    @if ($canManageUpdates && !empty($developerLauncherHandoff['protocol_url']))
                        <a href="#" data-update-start-url="{{ route('developer.updater.launcher-handoff.start') }}" class="js-update-handoff rounded-lg bg-[var(--primary-color)] px-4 py-2 text-sm font-semibold text-white">
                            Update Now
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div id="update-handoff-overlay" class="fixed inset-0 z-[10000] {{ $activeUpdateLock ? 'flex' : 'hidden' }} items-center justify-center bg-[var(--overlay-color)] px-4">
        <div class="w-full max-w-lg rounded-xl border border-[var(--border-warning)] bg-[var(--secondary-bg-color)] p-6 text-center shadow-xl">
            <h2 class="text-xl font-semibold text-[var(--text-color)]">GarmentsOS PRO is updating</h2>
            <p class="mt-3 text-sm text-[var(--secondary-text)]">
                {{ $activeUpdateLock['message'] ?? 'Please do not close or use the app until the update is complete.' }}
            </p>
            <p class="mt-3 text-xs text-[var(--secondary-text)]">
                If Windows asks to open GarmentsOS PRO Updater, choose Open.
            </p>
            @if (!empty($activeUpdateLock['expires_at']))
                <p class="mt-3 text-xs text-[var(--secondary-text)]">
                    Lock expires at {{ $activeUpdateLock['expires_at'] }}.
                </p>
            @endif
        </div>
    </div>

    <form id="moduleBranchPreferenceForm" method="POST" action="{{ route('module-branch-preferences.store') }}" class="hidden">
        @csrf
        <input type="hidden" name="module_key" value="">
        <input type="hidden" name="branch_id" value="">
        <input type="hidden" name="selection_mode" value="single">
        <input type="hidden" name="redirect_to" value="">
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const overlay = document.getElementById('update-handoff-overlay');
            const moduleBranchPreferenceForm = document.getElementById('moduleBranchPreferenceForm');
            const updateLockStatusUrl = @json(Auth::check() ? route('developer.updater.update-lock-status') : null);
            const updatingUrl = @json(route('updating'));
            const launchGuardKey = 'garmentsos_update_launching';
            let updateLockPoll = null;
            let closeFallbackStarted = false;

            const notify = (type, title, message) => {
                if (typeof showNotification === 'function') {
                    showNotification(title, message, type);
                    return;
                }

                if (typeof showMessageBox === 'function') {
                    showMessageBox(type, message || title);
                }
            };

            document.addEventListener('click', (event) => {
                const option = event.target.closest('[data-module-branch-option]');
                if (!option) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (option.disabled || !moduleBranchPreferenceForm) {
                    return;
                }

                const moduleKey = option.dataset.moduleKey || '';
                const branchId = option.dataset.branchId || '';
                if (!moduleKey || !branchId) {
                    return;
                }

                moduleBranchPreferenceForm.elements.module_key.value = moduleKey;
                moduleBranchPreferenceForm.elements.branch_id.value = branchId;
                moduleBranchPreferenceForm.elements.selection_mode.value = 'single';
                moduleBranchPreferenceForm.querySelectorAll('[name="branch_ids[]"]').forEach((input) => input.remove());
                moduleBranchPreferenceForm.elements.redirect_to.value = window.location.href;
                moduleBranchPreferenceForm.submit();
            }, true);

            document.addEventListener('change', (event) => {
                const allToggle = event.target.closest('[data-module-branch-all]');
                if (!allToggle) {
                    return;
                }

                allToggle.closest('.branch-switcher')?.querySelectorAll('[data-module-branch-checkbox]').forEach((checkbox) => {
                    checkbox.checked = allToggle.checked;
                });
            });

            document.addEventListener('click', (event) => {
                const apply = event.target.closest('[data-module-branch-apply]');
                if (!apply) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (!moduleBranchPreferenceForm) {
                    return;
                }

                const moduleKey = apply.dataset.moduleKey || '';
                if (!moduleKey) {
                    return;
                }

                const wrapper = apply.closest('.branch-switcher');
                const selectedIds = Array.from(wrapper?.querySelectorAll('[data-module-branch-checkbox]:checked') || [])
                    .map((checkbox) => checkbox.dataset.branchId)
                    .filter(Boolean);

                moduleBranchPreferenceForm.querySelectorAll('[name="branch_ids[]"]').forEach((input) => input.remove());
                selectedIds.forEach((branchId) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'branch_ids[]';
                    input.value = branchId;
                    moduleBranchPreferenceForm.appendChild(input);
                });

                moduleBranchPreferenceForm.elements.module_key.value = moduleKey;
                moduleBranchPreferenceForm.elements.branch_id.value = selectedIds[0] || '';
                moduleBranchPreferenceForm.elements.selection_mode.value = 'multiple';
                moduleBranchPreferenceForm.elements.redirect_to.value = window.location.href;
                moduleBranchPreferenceForm.submit();
            }, true);

            const launchGuardActive = () => {
                try {
                    const payload = JSON.parse(sessionStorage.getItem(launchGuardKey) || 'null');
                    if (!payload || !payload.expiresAt) {
                        return false;
                    }

                    if (Date.now() > payload.expiresAt) {
                        sessionStorage.removeItem(launchGuardKey);
                        return false;
                    }

                    return true;
                } catch (error) {
                    sessionStorage.removeItem(launchGuardKey);
                    return false;
                }
            };

            const setLaunchGuard = () => {
                try {
                    sessionStorage.setItem(launchGuardKey, JSON.stringify({
                        startedAt: Date.now(),
                        expiresAt: Date.now() + 60000,
                    }));
                } catch (error) {
                }
            };

            const clearLaunchGuard = () => {
                try {
                    sessionStorage.removeItem(launchGuardKey);
                } catch (error) {
                }
            };

            document.querySelectorAll('[data-update-modal-close]').forEach((button) => {
                button.addEventListener('click', () => {
                    document.getElementById('developer-update-modal')?.remove();
                });
            });

            const closeOrReplaceWithUpdating = (delay = 4000) => {
                if (closeFallbackStarted) {
                    return;
                }

                closeFallbackStarted = true;

                window.setTimeout(() => {
                    try {
                        window.close();
                    } catch (error) {
                    }

                    try {
                        window.open('', '_self');
                        window.close();
                    } catch (error) {
                    }

                    try {
                        window.location.replace(updatingUrl);
                    } catch (error) {
                        window.location.href = updatingUrl;
                    }
                }, delay);
            };

            const launchUpdaterProtocol = (protocolUrl) => {
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.setAttribute('aria-hidden', 'true');
                document.body.appendChild(iframe);

                try {
                    iframe.src = protocolUrl;
                } catch (error) {
                }

                window.setTimeout(() => {
                    iframe.remove();
                }, 5000);
            };

            const showUpdateOverlay = () => {
                if (overlay) {
                    overlay.classList.remove('hidden');
                    overlay.classList.add('flex');
                }
                document.querySelectorAll('button, input, select, textarea').forEach((element) => {
                    if (!element.closest('#update-handoff-overlay')) {
                        if (!element.hasAttribute('disabled')) {
                            element.dataset.updateLockDisabled = '1';
                        }
                        element.setAttribute('disabled', 'disabled');
                    }
                });
            };

            const hideUpdateOverlay = () => {
                if (overlay) {
                    overlay.classList.add('hidden');
                    overlay.classList.remove('flex');
                }
                document.querySelectorAll('[data-update-lock-disabled="1"]').forEach((element) => {
                    element.removeAttribute('disabled');
                    delete element.dataset.updateLockDisabled;
                });
            };

            const pollUpdateLock = () => {
                if (!updateLockStatusUrl || updateLockPoll) {
                    return;
                }

                updateLockPoll = window.setInterval(async () => {
                    try {
                        const response = await fetch(updateLockStatusUrl, {
                            headers: {
                                'Accept': 'application/json',
                            },
                        });
                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        if (payload && payload.updating === false) {
                            window.clearInterval(updateLockPoll);
                            updateLockPoll = null;
                            hideUpdateOverlay();
                            window.location.reload();
                        }
                    } catch (error) {
                        // Keep the overlay visible while the app/container is restarting.
                    }
                }, 5000);
            };

            @if ($activeUpdateLock)
                showUpdateOverlay();
                pollUpdateLock();
                @unless (request()->routeIs('developer.updater'))
                    closeOrReplaceWithUpdating();
                @endunless
            @endif

            document.querySelectorAll('.js-update-handoff').forEach((link) => {
                link.addEventListener('click', async (event) => {
                    event.preventDefault();
                    if (link.dataset.busy === '1' || launchGuardActive()) {
                        notify('warning', 'Launcher opening', 'Update launcher is already opening.');
                        return;
                    }

                    const startUrl = link.dataset.updateStartUrl;
                    if (!startUrl) {
                        return;
                    }

                    link.dataset.busy = '1';
                    link.setAttribute('aria-disabled', 'true');
                    const originalText = link.textContent;
                    link.textContent = 'Launcher opening...';
                    document.querySelectorAll('.js-update-handoff').forEach((otherLink) => {
                        otherLink.dataset.busy = '1';
                        otherLink.setAttribute('aria-disabled', 'true');
                    });
                    setLaunchGuard();
                    showUpdateOverlay();
                    pollUpdateLock();

                    try {
                        const response = await fetch(startUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            },
                            body: JSON.stringify({}),
                        });

                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok || !payload.protocol_url) {
                            throw new Error(payload.message || 'Update could not be started.');
                        }

                        try {
                            sessionStorage.setItem(launchGuardKey, JSON.stringify({
                                startedAt: Date.now(),
                                expiresAt: Date.now() + 60000,
                                requestId: payload.request_id || null,
                            }));
                        } catch (error) {
                        }

                        launchUpdaterProtocol(payload.protocol_url);
                        closeOrReplaceWithUpdating(4500);
                    } catch (error) {
                        clearLaunchGuard();
                        notify('error', 'Update could not be started', error.message || 'Update could not be started.');
                        link.dataset.busy = '0';
                        link.removeAttribute('aria-disabled');
                        link.textContent = originalText;
                        document.querySelectorAll('.js-update-handoff').forEach((otherLink) => {
                            otherLink.dataset.busy = '0';
                            otherLink.removeAttribute('aria-disabled');
                        });
                        hideUpdateOverlay();
                    }
                });
            });

            document.addEventListener('submit', (event) => {
                if (overlay && !overlay.classList.contains('hidden')) {
                    event.preventDefault();
                }
            }, true);

            if (window.fetch) {
                const originalFetch = window.fetch.bind(window);
                window.fetch = async (...args) => {
                    const response = await originalFetch(...args);
                    if (response.status === 423 || response.status === 503) {
                        const cloned = response.clone();
                        cloned.json().then((payload) => {
                            if (payload?.updating) {
                                showUpdateOverlay();
                                pollUpdateLock();
                                closeOrReplaceWithUpdating();
                            }
                        }).catch(() => {});
                    }

                    return response;
                };
            }
        });
    </script>

@stack('page-scripts')
</body>
</html>
