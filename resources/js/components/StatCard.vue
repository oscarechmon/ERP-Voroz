<script setup lang="ts">
import Skeleton from 'primevue/skeleton';

defineProps<{
    label: string;
    value: string | number;
    icon: string;
    tone?: 'brand' | 'emerald' | 'amber' | 'rose' | 'violet';
    hint?: string;
    loading?: boolean;
}>();

const tones: Record<string, string> = {
    brand: 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300',
    emerald: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300',
    amber: 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300',
    rose: 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300',
    violet: 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300',
};
</script>

<template>
    <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-5 shadow-sm transition hover:shadow-md">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm font-medium text-slate-500">{{ label }}</p>
                <Skeleton v-if="loading" width="6rem" height="2rem" class="mt-2" />
                <p v-else class="mt-1 text-2xl font-bold tracking-tight">{{ value }}</p>
            </div>
            <span class="grid h-11 w-11 place-items-center rounded-xl" :class="tones[tone ?? 'brand']">
                <i :class="icon" class="text-lg"></i>
            </span>
        </div>
        <p v-if="hint && !loading" class="mt-3 text-xs text-slate-400">{{ hint }}</p>
    </div>
</template>
