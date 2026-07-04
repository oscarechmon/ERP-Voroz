<script setup lang="ts">
import { onMounted, ref } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import { reportsApi, REPORT_TYPES, type ReportData } from '@/services/reports';

const type = ref('sales');
const from = ref<string>('');
const to = ref<string>('');
const data = ref<ReportData | null>(null);
const loading = ref(false);

const currentMeta = () => REPORT_TYPES.find((r) => r.value === type.value);

function toDateStr(v: string | Date | null): string | undefined {
    if (!v) return undefined;
    const d = typeof v === 'string' ? new Date(v) : v;
    return d.toISOString().slice(0, 10);
}

async function load(): Promise<void> {
    loading.value = true;
    try {
        data.value = await reportsApi.get(type.value, toDateStr(from.value), toDateStr(to.value));
    } finally {
        loading.value = false;
    }
}

function exportAs(format: 'xlsx' | 'csv' | 'pdf'): void {
    window.open(reportsApi.exportUrl(type.value, format, toDateStr(from.value), toDateStr(to.value)), '_blank');
}

const fmt = (v: string | number): string =>
    typeof v === 'number' ? new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v) : v;

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Reportes</h1>
            <p class="text-sm text-slate-500">Consulta y exporta a Excel, CSV o PDF.</p>
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Reporte</label>
                    <Select v-model="type" :options="REPORT_TYPES" option-label="label" option-value="value" class="w-52" @change="load" />
                </div>
                <template v-if="currentMeta()?.dated">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Desde</label>
                        <DatePicker v-model="from" date-format="yy-mm-dd" show-icon class="w-40" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Hasta</label>
                        <DatePicker v-model="to" date-format="yy-mm-dd" show-icon class="w-40" />
                    </div>
                </template>
                <Button label="Consultar" icon="pi pi-search" @click="load" />
                <div class="ml-auto flex gap-2">
                    <Button label="Excel" icon="pi pi-file-excel" severity="success" outlined @click="exportAs('xlsx')" />
                    <Button label="CSV" icon="pi pi-file" severity="secondary" outlined @click="exportAs('csv')" />
                    <Button label="PDF" icon="pi pi-file-pdf" severity="danger" outlined @click="exportAs('pdf')" />
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <DataTable :value="data?.rows ?? []" :loading="loading" paginator :rows="15" :rows-per-page-options="[15, 30, 50, 100]" class="text-sm">
                <template #empty><div class="py-8 text-center text-slate-400">Sin datos para el rango seleccionado.</div></template>
                <Column v-for="(h, i) in data?.headings ?? []" :key="i" :header="h">
                    <template #body="{ data: row }">
                        <span :class="{ 'font-semibold': i === (data?.headings.length ?? 0) - 1 }">{{ fmt(row[i]) }}</span>
                    </template>
                </Column>
            </DataTable>

            <div v-if="data?.summary && Object.keys(data.summary).length" class="mt-4 flex flex-wrap gap-6 rounded-xl bg-slate-50 p-4 text-sm dark:bg-white/5">
                <div v-for="(v, k) in data.summary" :key="k">
                    <span class="text-slate-500">{{ String(k).replace(/_/g, ' ') }}:</span>
                    <span class="ml-1 font-bold">{{ fmt(v) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
