<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 h-10 px-4 bg-brand-petroleo border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-brand-noche focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-turquesa focus-visible:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
