@props(['negative' => false, 'tagline' => true])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <x-application-logo :negative="$negative" class="w-9 h-9 shrink-0" />
    <span class="leading-none">
        <span @class(['block font-extrabold text-xl tracking-tight', 'text-white' => $negative, 'text-brand-petroleo' => ! $negative])>Centinela</span>
        @if ($tagline)
            <span @class(['block mt-1 text-[8px] font-semibold tracking-[0.15em]', 'text-brand-50/80' => $negative, 'text-ink-muted' => ! $negative])>TRIAGE · ALERTA TEMPRANA</span>
        @endif
    </span>
</span>
