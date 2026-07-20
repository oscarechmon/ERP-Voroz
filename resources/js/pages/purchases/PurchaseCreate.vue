<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Select from 'primevue/select';
import AutoComplete, { type AutoCompleteCompleteEvent } from 'primevue/autocomplete';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import ToggleSwitch from 'primevue/toggleswitch';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { AxiosError } from 'axios';
import { productsApi, type Product } from '@/services/catalog';
import { suppliersApi, type Supplier } from '@/services/contacts';
import { inventoryApi, type Warehouse } from '@/services/inventory';
import { purchasesApi } from '@/services/purchases';

interface Line { product_id: number; description: string; quantity: number; cost: number; }

const IGV_RATE = 0.18;

const router = useRouter();
const route = useRoute();
const toast = useToast();

const editId = computed<number | null>(() => (route.params.id ? Number(route.params.id) : null));
const isEdit = computed(() => editId.value !== null);
const loading = ref(false);

const suppliers = ref<Supplier[]>([]);
const warehouses = ref<Warehouse[]>([]);
const supplierId = ref<number | null>(null);
const warehouseId = ref<number | null>(null);
const supplierDoc = ref('');
const applyIgv = ref(true);
const lines = ref<Line[]>([]);
const saving = ref(false);

const productQuery = ref('');
const productSuggestions = ref<Product[]>([]);

const money = (n: number): string => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);
const base = computed(() => lines.value.reduce((s, l) => s + l.quantity * l.cost, 0));
const igv = computed(() => (applyIgv.value ? base.value * IGV_RATE : 0));
const total = computed(() => base.value + igv.value);
const canSave = computed(() => supplierId.value && warehouseId.value && lines.value.length > 0);

async function searchProducts(e: AutoCompleteCompleteEvent): Promise<void> {
    const res = await productsApi.list({ search: e.query, per_page: 8, is_active: 1 });
    productSuggestions.value = res.data;
}

function addProduct(p: Product): void {
    if (!lines.value.some((l) => l.product_id === p.id)) {
        lines.value.push({ product_id: p.id, description: p.name, quantity: 1, cost: p.cost });
    }
    productQuery.value = '';
}

const removeLine = (i: number): void => { lines.value.splice(i, 1); };

async function save(): Promise<void> {
    saving.value = true;
    try {
        const payload = {
            supplier_id: supplierId.value!,
            warehouse_id: warehouseId.value!,
            supplier_doc: supplierDoc.value || undefined,
            apply_igv: applyIgv.value,
            items: lines.value.map((l) => ({ product_id: l.product_id, quantity: l.quantity, cost: l.cost })),
        };
        const purchase = isEdit.value
            ? await purchasesApi.update(editId.value!, payload)
            : await purchasesApi.register(payload);
        toast.add({
            severity: 'success',
            summary: `Compra ${purchase.number}`,
            detail: isEdit.value ? 'Compra actualizada, stock recalculado' : 'Stock y costo actualizados',
            life: 4000,
        });
        router.push('/purchases');
    } catch (e) {
        const ax = e as AxiosError<{ message?: string }>;
        const fallback = isEdit.value ? 'No se pudo actualizar la compra' : 'No se pudo registrar la compra';
        toast.add({ severity: 'error', summary: 'Error', detail: ax.response?.data?.message ?? fallback, life: 5000 });
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    [suppliers.value, warehouses.value] = await Promise.all([suppliersApi.options(), inventoryApi.warehouses()]);

    if (isEdit.value) {
        loading.value = true;
        try {
            const p = await purchasesApi.get(editId.value!);
            supplierId.value = p.supplier_id;
            warehouseId.value = p.warehouse_id;
            supplierDoc.value = p.supplier_doc ?? '';
            applyIgv.value = p.apply_igv;
            lines.value = (p.items ?? [])
                .filter((i) => i.product_id !== null)
                .map((i) => ({ product_id: i.product_id as number, description: i.description, quantity: i.quantity, cost: i.cost }));
        } catch {
            toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar la compra', life: 5000 });
            router.push('/purchases');
        } finally {
            loading.value = false;
        }
        return;
    }

    warehouseId.value = warehouses.value.find((w) => w.is_default)?.id ?? warehouses.value[0]?.id ?? null;
});
</script>

<template>
    <div class="space-y-5">
        <div class="flex items-center gap-3">
            <Button icon="pi pi-arrow-left" text rounded @click="router.push('/purchases')" />
            <h1 class="text-2xl font-bold tracking-tight">{{ isEdit ? 'Editar compra' : 'Registrar compra' }}</h1>
            <i v-if="loading" class="pi pi-spin pi-spinner text-slate-400" />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Datos + productos -->
            <div class="space-y-4 lg:col-span-2">
                <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Proveedor *</label>
                            <Select v-model="supplierId" :options="suppliers" option-label="name" option-value="id" class="w-full" filter placeholder="Seleccionar" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Almacén destino *</label>
                            <Select v-model="warehouseId" :options="warehouses" option-label="name" option-value="id" class="w-full" placeholder="Seleccionar" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Nº documento</label>
                            <InputText v-model="supplierDoc" class="w-full" placeholder="F001-1234" />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
                    <label class="mb-1 block text-sm font-medium">Agregar producto</label>
                    <AutoComplete
                        v-model="productQuery" :suggestions="productSuggestions" option-label="name" class="w-full" input-class="w-full"
                        placeholder="Buscar producto…" complete-on-focus @complete="searchProducts" @option-select="(e) => addProduct(e.value)"
                    >
                        <template #option="{ option }">
                            <div class="flex items-center justify-between gap-4">
                                <span>{{ option.name }}</span>
                                <span class="text-xs text-slate-400">{{ option.code }} · costo {{ money(option.cost) }}</span>
                            </div>
                        </template>
                    </AutoComplete>

                    <DataTable :value="lines" class="mt-3 text-sm">
                        <template #empty><div class="py-6 text-center text-slate-400">Agrega productos a la compra.</div></template>
                        <Column header="Producto" field="description" />
                        <Column header="Cantidad" header-style="width:9rem">
                            <template #body="{ data }"><InputNumber v-model="data.quantity" :min="1" show-buttons button-layout="horizontal" input-class="w-12 text-center" /></template>
                        </Column>
                        <Column header="Costo unit." header-style="width:9rem">
                            <template #body="{ data }"><InputNumber v-model="data.cost" mode="currency" currency="PEN" locale="es-PE" input-class="text-right" /></template>
                        </Column>
                        <Column header="Subtotal">
                            <template #body="{ data }">{{ money(data.quantity * data.cost) }}</template>
                        </Column>
                        <Column header="" header-style="width:3rem">
                            <template #body="{ index }"><Button icon="pi pi-times" text rounded size="small" severity="danger" @click="removeLine(index)" /></template>
                        </Column>
                    </DataTable>
                </div>
            </div>

            <!-- Resumen -->
            <div class="h-min rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-5 shadow-sm">
                <h3 class="mb-4 font-semibold">Resumen</h3>
                <div class="mb-3 flex items-center justify-between rounded-xl border border-[var(--surface-border)] px-3 py-2">
                    <div class="flex items-center gap-2">
                        <ToggleSwitch v-model="applyIgv" input-id="apply-igv" />
                        <label for="apply-igv" class="text-sm font-medium">Aplicar IGV (18%)</label>
                    </div>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-slate-500"><span>Base imponible</span><span>{{ money(base) }}</span></div>
                    <div class="flex justify-between text-slate-500"><span>{{ applyIgv ? 'IGV (18%)' : 'IGV (no aplica)' }}</span><span>{{ money(igv) }}</span></div>
                    <div class="flex justify-between border-t border-[var(--surface-border)] pt-2 text-lg font-bold"><span>Total</span><span>{{ money(total) }}</span></div>
                </div>
                <Button :label="isEdit ? 'Guardar cambios' : 'Registrar compra'" icon="pi pi-check" class="mt-5 w-full" size="large" :disabled="!canSave" :loading="saving" @click="save" />
                <p class="mt-3 text-center text-xs text-slate-400">{{ isEdit ? 'Al guardar se revierte y reaplica el stock, recalculando el costo promedio.' : 'Al registrar se repone el stock y se recalcula el costo promedio.' }}</p>
            </div>
        </div>
    </div>
</template>
