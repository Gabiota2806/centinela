@props(['negative' => false])

{{-- Isotipo de Centinela: pin geoespacial + cruz sanitaria + anillo con los 5 niveles MTS (azul → rojo). --}}
<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Centinela" {{ $attributes }}>
    <g fill="none" stroke-width="4">
        <path d="M15.888 38.068 A19 19 0 0 1 13.416 24.050" stroke="#2563EB"/>
        <path d="M13.929 22.129 A19 19 0 0 1 23.080 11.224" stroke="#16A34A"/>
        <path d="M24.883 10.383 A19 19 0 0 1 39.117 10.383" stroke="#EAB308"/>
        <path d="M40.920 11.224 A19 19 0 0 1 50.071 22.129" stroke="#EA580C"/>
        <path d="M50.584 24.050 A19 19 0 0 1 48.112 38.068" stroke="#DC2626"/>
    </g>
    <path d="M19.876 35 L32 56 L44.124 35 A14 14 0 1 0 19.876 35 Z" fill="{{ $negative ? '#FFFFFF' : '#0B4F55' }}"/>
    <rect x="29" y="20" width="6" height="16" rx="1.5" fill="{{ $negative ? '#0B4F55' : '#FFFFFF' }}"/>
    <rect x="24" y="25" width="16" height="6" rx="1.5" fill="{{ $negative ? '#0B4F55' : '#FFFFFF' }}"/>
</svg>
