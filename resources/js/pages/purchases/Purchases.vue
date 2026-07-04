<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import DataTable, { type DataTablePageEvent, type DataTableSortEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import { purchasesApi, type Purchase } from '@/services/purchases';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const rows = ref<Purchase[]>([]);
const total = ref(0);
const loading = ref(true);
const params = reactive({ page: 1, per_page: 15, search: '', sort_by: 'purchased_at', sort_dir: 'desc' as 'asc' | 'desc' });

const money = (n: number): string => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);
const dt = (s: string | null): string => (s ? new Date(s).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' }) : '');

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await purchasesApi.list(params);
        rows.value = res.data;
        total.value = res.meta.total;
    } finally {
        loading.value = false;
    }
}

let t: number | undefined;
const onSearch = (): void => { window.clearTimeout(t); t = window.setTimeout(() => { params.page = 1; load(); }, 350); };
const onPage = (e: DataTablePageEvent): void => { params.page = e.page + 1; params.per_page = e.rows; load(); };
const onSort = (e: DataTableSortEvent): void => { params.sort_by = (e.sortField as string) || 'purchased_at'; params.sort_dir = e.sortOrder === 1 ? 'asc' : 'desc'; load(); };

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Compras</h1>
                <p class="text-sm text-slate-500">{{ total }} compras registradas</p>
            </div>
            <router-link v-if="auth.can('purchases.create')" to="/purchases/create">
                <Button label="Registrar compra" icon="pi pi-plus" />
            </router-link>
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="mb-3">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="params.search" placeholder="Buscar por número o documento…" class="w-72" @input="onSearch" />
                </IconField>
            </div>
            <DataTable
                :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                :first="(params.page - 1) * params.per_page" :rows-per-page-options="[15, 30, 50]" removable-sort class="text-sm"
                @page="onPage" @sort="onSort"
            >
                <template #empty><div class="py-8 text-center text-slate-400">Sin compras registradas.</div></template>
                <Column header="Número" field="number" sortable />
                <Column header="Documento prov.">
                    <template #body="{ data }">{{ data.supplier_doc ?? '—' }}</template>
                </Column>
                <Column header="Proveedor">
                    <template #body="{ data }">{{ data.supplier?.name ?? '—' }}</template>
                </Column>
                <Column header="Fecha" field="purchased_at" sortable>
                    <template #body="{ data }"><span class="text-xs">{{ dt(data.purchased_at) }}</span></template>
                </Column>
                <Column header="Total" field="total" sortable>
                    <template #body="{ data }"><span class="font-semibold">{{ money(data.total) }}</span></template>
                </Column>
                <Column header="Registró">
                    <template #body="{ data }">{{ data.user ?? '—' }}</template>
                </Column>
            </DataTable>
        </div>
    </div>
</template>
