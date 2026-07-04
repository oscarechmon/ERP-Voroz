<script setup lang="ts">
import { onMounted } from 'vue';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { useUiStore } from '@/stores/ui';

// Aplica el tema (claro/oscuro) persistido al montar la aplicación.
const ui = useUiStore();
onMounted(() => ui.applyTheme());
</script>

<template>
    <!-- Servicios globales de UI: notificaciones (Toast) y confirmaciones. -->
    <Toast position="top-right" />
    <ConfirmDialog />
    <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
            <component :is="Component" />
        </transition>
    </router-view>
</template>
