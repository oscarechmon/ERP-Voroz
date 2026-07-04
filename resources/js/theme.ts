import { definePreset } from '@primevue/themes';
import Aura from '@primevue/themes/aura';

/**
 * Preset de PrimeVue personalizado para el ERP.
 * Ajusta el color primario a la marca (azul índigo). Las superficies quedan a
 * cargo del preset Aura, coherente en modo claro y oscuro.
 */
export const VorozPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '#eef4ff',
            100: '#d9e6ff',
            200: '#bcd3ff',
            300: '#8eb6ff',
            400: '#598eff',
            500: '#3366ff',
            600: '#1f47f0',
            700: '#1836d6',
            800: '#1a30ad',
            900: '#1c2f88',
            950: '#131f5c',
        },
    },
});
