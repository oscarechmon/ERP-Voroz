<script setup lang="ts">
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
const router = useRouter();
</script>

<template>
    <div class="grid min-h-screen place-items-center bg-[var(--surface-ground)] p-6 text-center">
        <div>
            <p class="text-7xl font-black text-brand-500">404</p>
            <h1 class="mt-2 text-xl font-bold">Página no encontrada</h1>
            <p class="mt-1 text-sm text-slate-500">La ruta que buscas no existe o fue movida.</p>
            <Button class="mt-6" label="Volver al inicio" icon="pi pi-home" @click="router.push('/')" />
        </div>
    </div>
</template>
