import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.ts',
    ],

    theme: {
        extend: {
            colors: {
                navy: {
                    base: '#050D18',
                    raised: '#07111F',
                },
                accent: {
                    cyan: '#2DE2E6',
                    violet: '#8B7CFF',
                },
                status: {
                    completed: '#55D98B',
                    review: '#F8C65D',
                    critical: '#F26C78',
                    info: '#5AA8FF',
                },
                /* Runs Explorer (Phase 4) text + surface ramp, from the mockup. */
                ink: {
                    100: '#EAF3FB',
                    200: '#DCE8F5',
                    300: '#B9CDE0',
                    400: '#A8BDD4',
                    500: '#8AA3BC',
                    600: '#7D93AE',
                    700: '#6F87A3',
                    800: '#61788F',
                    900: '#5F7690',
                    950: '#4A5D73',
                },
                glow: {
                    cyan: '#BFF6F7',
                    teal: '#93E9EC',
                    blue: '#9FC9E8',
                    violet: '#D6D0FF',
                    green: '#8FEFBB',
                    red: '#F5959D',
                },
                panel: {
                    base: '#0A1424',
                    head: '#0B1627',
                    row: '#101E33',
                    hover: '#111F34',
                },
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Space Grotesk', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
        },
    },

    plugins: [forms],
};
