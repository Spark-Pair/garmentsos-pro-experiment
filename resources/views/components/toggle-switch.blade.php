@props([
    'name' => null,
    'value' => '1',
    'checked' => false,
    'disabled' => false,
])

<span
    {{ $attributes->merge(['class' => 'app-toggle' . ($checked ? ' is-checked' : '')]) }}
    role="switch"
    aria-checked="{{ $checked ? 'true' : 'false' }}"
>
    <input
        class="app-toggle-input peer sr-only"
        type="checkbox"
        @if($name) name="{{ $name }}" @endif
        value="{{ $value }}"
        @checked($checked)
        @disabled($disabled)
    >
    <span class="app-toggle-track"></span>
    <span class="app-toggle-thumb"></span>
</span>
