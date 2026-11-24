import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Tokens del Design System de Centinela (docs/specs/ui/spec.md).
 * Fuente de diseño: Figma «Centinela – UX/UI», página Design System.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './config/centinela.php',
        // Enums con clases de Tailwind (p. ej. TriagePriority::badgeClasses()).
        './app/Enums/**/*.php',
        './app/Livewire/**/*.php',
    ],

    // Garantiza la compilación CSS de los 5 niveles MTS (tokens oficiales y clases de TriagePriority).
    safelist: [
        'bg-mts-red',
        'bg-mts-orange',
        'bg-mts-yellow',
        'bg-mts-green',
        'bg-mts-blue',
        'bg-red-600',
        'bg-orange-600',
        'bg-yellow-500',
        'bg-green-600',
        'bg-blue-600',
        'text-white',
        'text-black',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Prioridad clínica Manchester (backlog CEN-12). Uso exclusivo para triage.
                mts: {
                    red: '#DC2626',
                    orange: '#EA580C',
                    yellow: '#EAB308',
                    green: '#16A34A',
                    blue: '#2563EB',
                },
                brand: {
                    petroleo: '#0B4F55',
                    turquesa: '#12808A',
                    noche: '#0A2E33',
                    50: '#E3F1F2',
                },
                ink: {
                    DEFAULT: '#12292C',
                    muted: '#56706F',
                    subtle: '#8AA0A0',
                },
                line: '#DCE6E6',
                canvas: '#F5F8F8',
                ok: '#198754',
                warn: '#B35C00',
                danger: '#C92A2A',
                risk: {
                    low: '#9FD8C6',
                    mid: '#F4B860',
                    high: '#C2410C',
                },
            },
            boxShadow: {
                card: '0 2px 8px rgba(10, 46, 51, 0.08)',
                modal: '0 16px 40px rgba(10, 46, 51, 0.20)',
            },
        },
    },

    plugins: [forms],
};
