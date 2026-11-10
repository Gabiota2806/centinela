@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'h-11 border-line text-ink placeholder:text-ink-subtle focus:border-brand-turquesa focus:ring-brand-turquesa rounded-lg shadow-sm']) }}>
