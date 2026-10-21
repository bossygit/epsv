import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,jsx}',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                serif: ['Spectral', 'Georgia', 'Cambria', 'Times New Roman', 'serif'],
            },

            colors: {
                // Jetons de l'identité EPSV
                ink: '#14232E',
                muted: '#62717C',
                ivory: '#FAF7F1',
                paper: '#FFFFFF',
                navy: {
                    DEFAULT: '#0F2438',
                    950: '#0A1826',
                    900: '#0F2438',
                    800: '#16304A',
                    700: '#1B3A5C',
                    600: '#2A4E70',
                    50: '#F1F4F7',
                },
                gold: {
                    DEFAULT: '#B08A3E',
                    light: '#D9BE8A',
                    pale: '#F6EEDD',
                },
                line: {
                    DEFAULT: '#E4DED1',
                    soft: '#EFEAE0',
                },

                // Remappage des échelles héritées de Breeze : toutes les vues
                // existantes passent à un neutre chaud + bleu nuit.
                gray: {
                    50: '#FAF7F1',
                    100: '#F4F0E7',
                    200: '#E6E0D4',
                    300: '#D5CCBA',
                    400: '#A9A294',
                    500: '#827C71',
                    600: '#5F6A67',
                    700: '#42525C',
                    800: '#263742',
                    900: '#15252F',
                    950: '#0F1E27',
                },
                slate: {
                    50: '#F5F6F7',
                    100: '#E9EDF1',
                    200: '#D3DBE3',
                    300: '#B0BFCD',
                    400: '#7E93A6',
                    500: '#5C7285',
                    600: '#485B6C',
                    700: '#394A58',
                    800: '#22333F',
                    900: '#0F2438',
                    950: '#0A1826',
                },
                indigo: {
                    50: '#F1F4F7',
                    100: '#E3EAF1',
                    200: '#C7D5E2',
                    300: '#9FB4C9',
                    400: '#6E8CA8',
                    500: '#3F6081',
                    600: '#1B3A5C',
                    700: '#16304A',
                    800: '#12283D',
                    900: '#0F2438',
                    950: '#0A1826',
                },
            },

            borderRadius: {
                none: '0',
                sm: '1px',
                DEFAULT: '2px',
                md: '2px',
                lg: '3px',
                xl: '4px',
                '2xl': '5px',
                '3xl': '6px',
                full: '9999px',
            },

            boxShadow: {
                sm: '0 1px 2px rgba(15, 36, 56, 0.04)',
                DEFAULT: '0 1px 3px rgba(15, 36, 56, 0.06)',
                md: '0 2px 6px rgba(15, 36, 56, 0.07)',
                lg: '0 6px 18px rgba(15, 36, 56, 0.09)',
            },
        },
    },

    plugins: [forms],
};
