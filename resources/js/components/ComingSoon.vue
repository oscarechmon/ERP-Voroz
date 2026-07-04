<script setup lang="ts">
defineProps<{ title: string; icon?: string }>();
</script>

<template>
    <div class="space-y-6">
        <h1 class="text-2xl font-bold tracking-tight">{{ title }}</h1>
        <div class="grid place-items-center rounded-2xl border border-dashed border-[var(--surface-border)] bg-[var(--surface-card)] p-16 text-center">
            <span class="grid h-16 w-16 place-items-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-500/10">
                <i :class="icon ?? 'pi pi-wrench'" class="text-2xl"></i>
            </span>
            <h2 class="mt-4 text-lg font-semibold">Módulo en construcción</h2>
            <p class="mt-1 max-w-md text-sm text-slate-500">
                Esta sección se está implementando siguiendo el plan por fases. La arquitectura y la API base ya están listas.
            </p>
        </div>
    </div>
</template>
