import '../css/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import Tooltip from 'primevue/tooltip';
import VueApexCharts from 'vue3-apexcharts';

import App from './App.vue';
import router from './router';
import { SistemaPreset } from './theme';
import { useAuthStore } from './stores/auth';

const app = createApp(App);

app.use(createPinia());

app.use(PrimeVue, {
    ripple: true,
    theme: {
        preset: SistemaPreset,
        options: {
            darkModeSelector: '.app-dark',
            // Coloca los estilos de PrimeVue en una capa CSS para que Tailwind pueda
            // sobreescribir utilidades cuando sea necesario.
            cssLayer: { name: 'primevue', order: 'theme, base, primevue' },
        },
    },
    locale: {
        emptyMessage: 'Sin resultados',
        emptySearchMessage: 'No se encontraron registros',
        emptySelectionMessage: 'Sin selección',
    },
});

app.use(ToastService);
app.use(ConfirmationService);
app.directive('tooltip', Tooltip);
app.component('apexchart', VueApexCharts);

// Carga la sesión antes de montar para que el guard del router tenga el usuario.
const auth = useAuthStore();
auth.bootstrap().finally(() => {
    app.use(router);
    app.mount('#app');
});
