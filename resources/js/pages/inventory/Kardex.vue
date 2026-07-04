<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import DataTable, { type DataTablePageEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { inventoryApi, MOVEMENT_LABELS, type Movement, type Warehouse } from '@/services/inventory';

const rows = ref<Movement[]>([]);
const total = ref(0);
const loading = ref(true);
const warehouses = ref<Warehouse[]>([]);
const params = reactive({ page: 1, per_page: 20, warehouse_id: null as number | null, type: null as string | null, from: '', to: '' });

const typeOptions = Object.entries(MOVEMENT_LABELS).map(([value, v]) => ({ value, label: v.label }));

const money = (n: number): string => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);
const dt = (s: string | null): string => (s ? new Date(s).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' }) : '');

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await inventoryApi.kardex(params);
        rows.value = res.data;
        total.value = res.meta.total;
    } finally {
        loading.value = false;
    }
}

const onPage = (e: DataTablePageEvent): void => { params.page = e.page + 1; params.per_page = e.rows; load(); };
const reload = (): void => { params.page = 1; load(); };

onMounted(async () => {
    warehouses.value = await inventoryApi.warehouses();
    load();
});
</script>

<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Kardex</h1>
            <p class="text-sm text-slate-500">Historial valorizado de movimientos de inventario</p>
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <Select v-model="params.warehouse_id" :options="warehouses" option-label="name" option-value="id" class="w-52" show-clear placeholder="Todos los almacenes" @change="reload" />
                <Select v-model="params.type" :options="typeOptions" option-label="label" option-value="value" class="w-44" show-clear placeholder="Todos los tipos" @change="reload" />
                <DatePicker v-model="params.from" date-format="yy-mm-dd" placeholder="Desde" show-icon class="w-40" @update:model-value="reload" />
                <DatePicker v-model="params.to" date-format="yy-mm-dd" placeholder="Hasta" show-icon class="w-40" @update:model-value="reload" />
                <Button icon="pi pi-refresh" text rounded @click="reload" v-tooltip.top="'Actualizar'" />
            </div>

            <DataTable
                :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                :first="(params.page - 1) * params.per_page" :rows-per-page-options="[20, 50, 100]" class="text-sm" @page="onPage"
            >
                <template #empty><div class="py-8 text-center text-slate-400">Sin movimientos.</div></template>
                <Column header="Fecha">
                    <template #body="{ data }"><span class="text-xs">{{ dt(data.created_at) }}</span></template>
                </Column>
                <Column header="Producto">
                    <template #body="{ data }">
                        <p class="font-medium">{{ data.product?.name }}</p>
                        <p class="text-xs text-slate-400">{{ data.product?.code }}</p>
                    </template>
                </Column>
                <Column header="Almacén">
                    <template #body="{ data }">{{ data.warehouse?.name }}</template>
                </Column>
                <Column header="Tipo">
                    <template #body="{ data }">
                        <Tag :value="MOVEMENT_LABELS[data.type]?.label ?? data.type" :severity="MOVEMENT_LABELS[data.type]?.severity ?? 'secondary'" />
                    </template>
                </Column>
                <Column header="Cantidad">
                    <template #body="{ data }">
                        <span :class="data.quantity >= 0 ? 'text-emerald-600' : 'text-rose-600'" class="font-semibold">
                            {{ data.quantity >= 0 ? '+' : '' }}{{ data.quantity }}
                        </span>
                    </template>
                </Column>
                <Column header="Costo unit.">
                    <template #body="{ data }">{{ money(data.cost) }}</template>
                </Column>
                <Column header="Saldo">
                    <template #body="{ data }"><span class="font-semibold">{{ data.balance }}</span></template>
                </Column>
                <Column header="Documento">
                    <template #body="{ data }">
                        <span class="text-xs text-slate-500">{{ data.reference_type ?? data.notes ?? '—' }}</span>
                    </template>
                </Column>
            </DataTable>
        </div>
    </div>
</template>
