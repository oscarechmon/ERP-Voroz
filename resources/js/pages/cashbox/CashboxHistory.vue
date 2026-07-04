<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import DataTable, { type DataTablePageEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { cashboxApi, type CashSession } from '@/services/cashbox';

const rows = ref<CashSession[]>([]);
const total = ref(0);
const loading = ref(true);
const params = reactive({ page: 1, per_page: 15 });

const money = (n: number | null): string =>
    n === null ? '—' : new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);
const dt = (s: string | null): string =>
    s ? new Date(s).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' }) : '—';

/** Severidad del arqueo según la diferencia (sobrante/faltante/cuadrado). */
const diffSeverity = (d: number | null): string => {
    if (d === null || Math.abs(d) < 0.005) return 'success';
    return d > 0 ? 'info' : 'danger';
};
const diffLabel = (d: number | null): string => {
    if (d === null) return '—';
    if (Math.abs(d) < 0.005) return 'Cuadrado';
    return (d > 0 ? 'Sobrante ' : 'Faltante ') + money(Math.abs(d));
};

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await cashboxApi.history(params);
        rows.value = res.data;
        total.value = res.meta.total;
    } finally {
        loading.value = false;
    }
}

const onPage = (e: DataTablePageEvent): void => { params.page = e.page + 1; params.per_page = e.rows; load(); };

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Historial de caja</h1>
                <p class="text-sm text-slate-500">{{ total }} sesiones cerradas</p>
            </div>
            <router-link to="/cashbox">
                <Button label="Caja actual" icon="pi pi-clock" outlined />
            </router-link>
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <DataTable
                :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                :first="(params.page - 1) * params.per_page" :rows-per-page-options="[15, 30, 50]" class="text-sm"
                @page="onPage"
            >
                <template #empty><div class="py-8 text-center text-slate-400">Sin sesiones cerradas.</div></template>
                <Column header="Caja">
                    <template #body="{ data }">{{ data.register ?? '—' }}</template>
                </Column>
                <Column header="Cajero">
                    <template #body="{ data }">{{ data.user ?? '—' }}</template>
                </Column>
                <Column header="Apertura">
                    <template #body="{ data }"><span class="text-xs">{{ dt(data.opened_at) }}</span></template>
                </Column>
                <Column header="Cierre">
                    <template #body="{ data }"><span class="text-xs">{{ dt(data.closed_at) }}</span></template>
                </Column>
                <Column header="Ventas efvo.">
                    <template #body="{ data }">{{ money(data.cash_sales) }}</template>
                </Column>
                <Column header="Esperado">
                    <template #body="{ data }">{{ money(data.expected_amount) }}</template>
                </Column>
                <Column header="Contado">
                    <template #body="{ data }"><span class="font-semibold">{{ money(data.counted_amount) }}</span></template>
                </Column>
                <Column header="Arqueo">
                    <template #body="{ data }">
                        <Tag :value="diffLabel(data.difference)" :severity="diffSeverity(data.difference)" />
                    </template>
                </Column>
            </DataTable>
        </div>
    </div>
</template>
