<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import DataTable, { type DataTablePageEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import ToggleButton from 'primevue/togglebutton';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import { inventoryApi, type StockRow, type Warehouse } from '@/services/inventory';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const auth = useAuthStore();

const rows = ref<StockRow[]>([]);
const total = ref(0);
const loading = ref(true);
const warehouses = ref<Warehouse[]>([]);
const params = reactive({ page: 1, per_page: 15, search: '', warehouse_id: null as number | null, low: false });

const dialog = ref(false);
const saving = ref(false);
const adjustForm = ref({ product_id: 0, warehouse_id: 0, quantity: 0, notes: '', productName: '' });

const money = (n: number): string => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await inventoryApi.stock(params);
        rows.value = res.data;
        total.value = res.meta.total;
    } finally {
        loading.value = false;
    }
}

let t: number | undefined;
const onSearch = (): void => { window.clearTimeout(t); t = window.setTimeout(() => { params.page = 1; load(); }, 350); };
const onPage = (e: DataTablePageEvent): void => { params.page = e.page + 1; params.per_page = e.rows; load(); };
const reload = (): void => { params.page = 1; load(); };

function openAdjust(row: StockRow): void {
    adjustForm.value = {
        product_id: row.product_id,
        warehouse_id: row.warehouse_id,
        quantity: row.quantity,
        notes: '',
        productName: row.product?.name ?? '',
    };
    dialog.value = true;
}

async function submitAdjust(): Promise<void> {
    saving.value = true;
    try {
        await inventoryApi.adjust({
            product_id: adjustForm.value.product_id,
            warehouse_id: adjustForm.value.warehouse_id,
            quantity: adjustForm.value.quantity,
            notes: adjustForm.value.notes || undefined,
        });
        dialog.value = false;
        toast.add({ severity: 'success', summary: 'Ajustado', detail: 'Inventario actualizado', life: 2500 });
        load();
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    warehouses.value = await inventoryApi.warehouses();
    load();
});
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Stock por almacén</h1>
                <p class="text-sm text-slate-500">{{ total }} registros de existencias</p>
            </div>
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="params.search" placeholder="Buscar producto…" class="w-72" @input="onSearch" />
                </IconField>
                <Select
                    v-model="params.warehouse_id" :options="warehouses" option-label="name" option-value="id"
                    class="w-56" show-clear placeholder="Todos los almacenes" @change="reload"
                />
                <ToggleButton v-model="params.low" on-label="Solo stock bajo" off-label="Solo stock bajo" on-icon="pi pi-exclamation-triangle" off-icon="pi pi-filter" @change="reload" />
            </div>

            <DataTable
                :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                :first="(params.page - 1) * params.per_page" :rows-per-page-options="[15, 30, 50]" class="text-sm"
                :row-class="(data) => (data.is_low ? 'bg-amber-50/60 dark:bg-amber-500/5' : '')" @page="onPage"
            >
                <template #empty><div class="py-8 text-center text-slate-400">Sin registros de stock.</div></template>
                <Column header="Producto">
                    <template #body="{ data }">
                        <p class="font-medium">{{ data.product?.name }}</p>
                        <p class="text-xs text-slate-400">{{ data.product?.code }}</p>
                    </template>
                </Column>
                <Column header="Almacén">
                    <template #body="{ data }">{{ data.warehouse?.name }}</template>
                </Column>
                <Column header="Cantidad">
                    <template #body="{ data }">
                        <span class="font-semibold" :class="data.is_low ? 'text-amber-600' : ''">{{ data.quantity }}</span>
                        <span class="text-xs text-slate-400"> / mín {{ data.product?.stock_min }}</span>
                    </template>
                </Column>
                <Column header="Costo prom.">
                    <template #body="{ data }">{{ money(data.avg_cost) }}</template>
                </Column>
                <Column header="Valorizado">
                    <template #body="{ data }">{{ money(data.quantity * data.avg_cost) }}</template>
                </Column>
                <Column header="Estado">
                    <template #body="{ data }">
                        <Tag v-if="data.quantity <= 0" value="Sin stock" severity="danger" />
                        <Tag v-else-if="data.is_low" value="Stock bajo" severity="warn" />
                        <Tag v-else value="OK" severity="success" />
                    </template>
                </Column>
                <Column header="" header-style="width:5rem">
                    <template #body="{ data }">
                        <Button v-if="auth.can('inventory.create')" icon="pi pi-sliders-h" text rounded size="small" @click="openAdjust(data)" v-tooltip.top="'Ajustar'" />
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog v-model:visible="dialog" modal header="Ajustar inventario" :style="{ width: '440px' }">
            <div class="space-y-4">
                <p class="text-sm text-slate-500">Producto: <span class="font-medium text-slate-700 dark:text-slate-200">{{ adjustForm.productName }}</span></p>
                <div>
                    <label class="mb-1 block text-sm font-medium">Nueva cantidad</label>
                    <InputNumber v-model="adjustForm.quantity" class="w-full" :min="0" show-buttons />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Motivo</label>
                    <Textarea v-model="adjustForm.notes" class="w-full" rows="2" placeholder="Conteo físico, merma, corrección…" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="dialog = false" />
                <Button label="Aplicar ajuste" icon="pi pi-check" :loading="saving" @click="submitAdjust" />
            </template>
        </Dialog>
    </div>
</template>
