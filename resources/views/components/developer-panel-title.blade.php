@props([
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'mb-4 flex items-start justify-between gap-4 border-b border-[var(--h-bg-color)] pb-4 text-left']) }}>
    <div class="flex min-w-0 items-start gap-3">
        <span class="mt-1 h-10 w-1.5 shrink-0 rounded-full bg-[var(--primary-color)]/80"></span>
        <div class="min-w-0">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--text-color)]">{{ $title }}</h2>
            @if ($description)
                <p class="mt-1 text-xs text-[var(--secondary-text)]">{{ $description }}</p>
            @endif
        </div>
    </div>

    @if (trim($slot) !== '')
        <div class="shrink-0">
            {{ $slot }}
        </div>
    @endif
</div>
