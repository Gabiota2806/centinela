<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 h-10 px-4 bg-white border border-line rounded-lg font-semibold text-sm text-brand-petroleo hover:bg-canvas focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa focus-visible:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
