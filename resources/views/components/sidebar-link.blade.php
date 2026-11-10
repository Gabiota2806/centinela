@props(['active' => false, 'icon' => 'home', 'label'])

{{-- Ítem de la barra lateral. Lee el estado `collapsed` del layout app (Alpine). --}}
<a {{ $attributes->class([
        'flex items-center gap-3 h-10 px-3 rounded-lg text-sm transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa',
        'bg-brand-50 text-brand-petroleo font-semibold' => $active,
        'text-ink-muted font-medium hover:bg-canvas hover:text-ink' => ! $active,
    ]) }}
    title="{{ $label }}"
    @if ($active) aria-current="page" @endif
    :class="collapsed && 'lg:justify-center lg:px-0'">
    <x-icon :name="$icon" />
    <span :class="collapsed && 'lg:sr-only'">{{ $label }}</span>
</a>
