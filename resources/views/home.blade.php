@extends('app')
@section('title', 'Home | ' . $client_company->name)
@php
    $svgPath = public_path($client_company->logo_svg_path);
    $logoSvg = '';

    if (file_exists($svgPath) && is_readable($svgPath)) {
        $logoSvg = file_get_contents($svgPath);
    }
    $homeAppName = $branding['app_name'] ?? $client_company->logo_text ?? 'GarmentsOS PRO';
@endphp

@section('content')
    <div class="flex min-h-[calc(100vh-13rem)] flex-col justify-center items-center tracking-wide py-8">
        <!-- Logo -->
        <div class="mb-5 p-4 shadow-sm border border-[var(--glass-border-color)]/20 rounded-3xl">
            <div class="logo w-45 rounded-xl overflow-hidden">
                @if ($logoSvg)
                    {!! $logoSvg !!}
                @else
                    <div class="text-center text-2xl font-bold text-[var(--primary-color)]">{{ $homeAppName }}</div>
                @endif
            </div>
        </div>

        <!-- Title & Subtitle -->
        <h1 class="text-4xl font-bold text-[var(--primary-color)] mb-2 text-center">Welcome to {{ $client_company->name }}!</h1>
        <p class="text-[var(--secondary-text)] text-center mb-4">
            {{ $homeAppName }} | Track your progress and manage your tasks efficiently.
        </p>
        @if (!empty($branding['print_footer_text']))
            <p class="text-xs text-[var(--secondary-text)] text-center mb-4">{{ $branding['print_footer_text'] }}</p>
        @endif

        <!-- Powered by Tag -->
        <div class="text-xs text-gray-500 italic">
            Powered by <span class="font-semibold text-[var(--primary-color)]">SparkPair</span>
        </div>
    </div>

@endsection

@if ($pusherEnabled && $notification)
    @push('page-scripts')
    <script defer src="{{ asset('js/pages/home.js') }}"></script>
    <script>
        window.__home = {
            notification: {
                title: @json($notification['title']),
                message: @json($notification['message']),
            },
        };
    </script>
    @endpush
@endif
