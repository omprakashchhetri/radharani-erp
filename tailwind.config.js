import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // UI text below 20px. Manrope reads well at small ERP sizes and has tabular figures.
                sans: ['Manrope', ...defaultTheme.fontFamily.sans],
                // Saudagar, the brand typeface (public/fonts): wordmark, page titles and headline
                // figures, 20px and up only (it is too thin to read smaller).
                display: ['Saudagar', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Aurum ERP design system: see docs/DESIGN_SYSTEM.md
                ink: {
                    DEFAULT: '#151515', // sidebar / darkest surface
                    charcoal: '#1F1F1F',
                    soft: '#242320',    // raised surface on the dark sidebar
                    line: '#2E2C28',    // hairlines on the dark sidebar
                    fg: '#C9C4B8',      // text on dark
                    dim: '#8E8A80',     // muted text on dark
                },
                gold: {
                    DEFAULT: '#B8862D',
                    dark: '#946B20',
                    light: '#D4AF5A',
                    soft: '#E8D3A2',
                    tint: '#FBF4E4',    // faint gold wash for selected rows / chips
                },
                surface: {
                    bg: '#FAF8F3',
                    DEFAULT: '#FFFFFF',
                    muted: '#F3F1EC',
                    sunken: '#F7F5F0',
                },
                ink_text: {
                    primary: '#1A1A1A',
                    secondary: '#6F6B63',
                    muted: '#9A958B',
                },
                line: {
                    DEFAULT: '#E7E3DA',
                    light: '#EFEBE3',
                    strong: '#D9D3C6',
                },
                success: { DEFAULT: '#26845B', bg: '#EAF7F0' },
                warning: { DEFAULT: '#B7791F', bg: '#FFF5DC' },
                danger: { DEFAULT: '#C94A4A', bg: '#FCECEC' },
                info: { DEFAULT: '#3867A6', bg: '#EDF4FC' },
            },
            borderRadius: {
                card: '14px',
                control: '10px',
            },
            boxShadow: {
                card: '0 1px 2px rgba(30, 25, 15, 0.04), 0 2px 10px rgba(30, 25, 15, 0.04)',
                raised: '0 1px 2px rgba(30, 25, 15, 0.05), 0 8px 24px -6px rgba(30, 25, 15, 0.10)',
                pop: '0 12px 32px -8px rgba(30, 25, 15, 0.18), 0 2px 6px rgba(30, 25, 15, 0.06)',
                modal: '0 30px 80px -20px rgba(20, 16, 8, 0.35), 0 4px 12px rgba(20, 16, 8, 0.08)',
                gold: '0 1px 0 rgba(255, 255, 255, 0.25) inset, 0 6px 16px -6px rgba(148, 107, 32, 0.55)',
                focus: '0 0 0 4px rgba(184, 134, 45, 0.14)',
            },
            // One documented layer scale (see docs/DESIGN_SYSTEM.md): sidebar < topbar < dropdown < drawer < modal < toast.
            zIndex: {
                sidebar: '30',
                topbar: '40',
                dropdown: '50',
                drawer: '60',
                modal: '70',
                toast: '80',
            },
            keyframes: {
                'fade-in': { from: { opacity: '0' }, to: { opacity: '1' } },
                'rise-in': { from: { opacity: '0', transform: 'translateY(6px)' }, to: { opacity: '1', transform: 'translateY(0)' } },
                shimmer: { '100%': { transform: 'translateX(100%)' } },
                progress: { '0%': { transform: 'translateX(-100%)' }, '100%': { transform: 'translateX(250%)' } },
            },
            // 'backwards' fill (not 'both'): once finished, the animation leaves no transform/opacity
            // behind. A retained transform creates a stacking context that traps dropdowns underneath
            // later siblings (e.g. the Inventory "Tools" menu sliding under the stat cards).
            animation: {
                'fade-in': 'fade-in .18s ease-out backwards',
                'rise-in': 'rise-in .28s cubic-bezier(.16,1,.3,1) backwards',
                shimmer: 'shimmer 1.4s infinite',
                progress: 'progress 1.1s cubic-bezier(.4,0,.2,1) infinite',
            },
            transitionTimingFunction: {
                silk: 'cubic-bezier(.16,1,.3,1)',
            },
        },
    },

    plugins: [forms],
};
