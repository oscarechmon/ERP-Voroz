<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import DataTable, { type DataTablePageEvent, type DataTableSortEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Tag from 'primevue/tag';
import Skeleton from 'primevue/skeleton';
import ProductFormDialog from '@/components/catalog/ProductFormDialog.vue';
import LabelPrintDialog from '@/components/catalog/LabelPrintDialog.vue';
import { productsApi, type Product } from '@/services/catalog';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();

const rows = ref<Product[]>([]);
const total = ref(0);
const loading = ref(true);
const selection = ref<Product[]>([]);

const params = reactive({
    page: 1,
    per_page: 10,
    search: '',
    sort_by: 'created_at',
    sort_dir: 'desc' as 'asc' | 'desc',
});

const dialogVisible = ref(false);
const editing = ref<Product | null>(null);

const labelDialogVisible = ref(false);
const labelIds = ref<number[]>([]);

const money = (n: number): string => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await productsApi.list(params);
        rows.value = res.data;
        total.value = res.meta.total;
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los productos', life: 3000 });
    } finally {
        loading.value = false;
    }
}

let searchTimer: number | undefined;
function onSearch(): void {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        params.page = 1;
        load();
    }, 350);
}

function onPage(e: DataTablePageEvent): void {
    params.page = e.page + 1;
    params.per_page = e.rows;
    load();
}

function onSort(e: DataTableSortEvent): void {
    params.sort_by = (e.sortField as string) || 'created_at';
    params.sort_dir = e.sortOrder === 1 ? 'asc' : 'desc';
    load();
}

function openCreate(): void {
    editing.value = null;
    dialogVisible.value = true;
}

function openEdit(product: Product): void {
    editing.value = product;
    dialogVisible.value = true;
}

function onSaved(): void {
    dialogVisible.value = false;
    toast.add({ severity: 'success', summary: 'Guardado', detail: 'Producto guardado correctamente', life: 2500 });
    load();
}

/** Abre el diálogo de etiquetas para los ids dados (una fila o la selección). */
function openLabels(ids: number[]): void {
    if (!ids.length) return;
    labelIds.value = ids;
    labelDialogVisible.value = true;
}

/** Exporta el catálogo (respeta el buscador actual) a Excel o CSV. */
function exportProducts(format: 'xlsx' | 'csv'): void {
    window.open(productsApi.exportUrl(format, { search: params.search }), '_blank');
}

function confirmDelete(product: Product): void {
    confirm.require({
        message: `¿Eliminar el producto «${product.name}»?`,
        header: 'Confirmar eliminación',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Cancelar',
        acceptLabel: 'Eliminar',
        acceptClass: 'p-button-danger',
        accept: async () => {
            await productsApi.remove(product.id);
            toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Producto eliminado', life: 2500 });
            load();
        },
    });
}

function confirmBulkDelete(): void {
    const ids = selection.value.map((p) => p.id);
    confirm.require({
        message: `¿Eliminar ${ids.length} producto(s) seleccionado(s)?`,
        header: 'Eliminación masiva',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar',
        rejectLabel: 'Cancelar',
        acceptClass: 'p-button-danger',
        accept: async () => {
            await productsApi.bulkRemove(ids);
            selection.value = [];
            toast.add({ severity: 'success', summary: 'Listo', detail: `${ids.length} eliminados`, life: 2500 });
            load();
        },
    });
}

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Productos</h1>
                <p class="text-sm text-slate-500">{{ total }} productos en el catálogo</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="selection.length && auth.can('products.print')"
                    :label="`Etiquetas (${selection.length})`"
                    icon="pi pi-tags"
                    severity="secondary"
                    outlined
                    @click="openLabels(selection.map((p) => p.id))"
                />
                <Button
                    v-if="selection.length"
                    :label="`Eliminar (${selection.length})`"
                    icon="pi pi-trash"
                    severity="danger"
                    outlined
                    @click="confirmBulkDelete"
                />
                <Button
                    v-if="auth.can('products.export')"
                    label="Excel"
                    icon="pi pi-file-excel"
                    severity="success"
                    outlined
                    @click="exportProducts('xlsx')"
                />
                <Button
                    v-if="auth.can('products.export')"
                    label="CSV"
                    icon="pi pi-file"
                    severity="secondary"
                    outlined
                    @click="exportProducts('csv')"
                />
                <Button
                    v-if="auth.can('products.create')"
                    label="Nuevo producto"
                    icon="pi pi-plus"
                    @click="openCreate"
                />
            </div>
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-3">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="params.search" placeholder="Buscar por nombre, código o barras…" class="w-80" @input="onSearch" />
                </IconField>
            </div>

            <DataTable
                :value="rows"
                :loading="loading"
                lazy
                paginator
                :rows="params.per_page"
                :total-records="total"
                :rows-per-page-options="[10, 25, 50, 100]"
                :first="(params.page - 1) * params.per_page"
                v-model:selection="selection"
                data-key="id"
                removable-sort
                paginator-template="FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink RowsPerPageDropdown"
                current-page-report-template="{first}–{last} de {totalRecords}"
                class="text-sm"
                @page="onPage"
                @sort="onSort"
            >
                <template #empty>
                    <div class="py-10 text-center text-slate-400">No se encontraron productos.</div>
                </template>
                <template #loading>
                    <div class="space-y-2 py-4">
                        <Skeleton v-for="i in 6" :key="i" height="2.2rem" />
                    </div>
                </template>

                <Column selection-mode="multiple" header-style="width:3rem" />
                <Column header="Producto" field="name" sortable>
                    <template #body="{ data }">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-100 dark:bg-white/5">
                                <img v-if="data.image_url" :src="data.image_url" class="h-full w-full object-cover" alt="" />
                                <i v-else class="pi pi-box text-slate-400"></i>
                            </span>
                            <div>
                                <p class="font-medium">{{ data.name }}</p>
                                <p class="text-xs text-slate-400">{{ data.code }} · {{ data.barcode }}</p>
                            </div>
                        </div>
                    </template>
                </Column>
                <Column header="Categoría">
                    <template #body="{ data }">{{ data.category?.name ?? '—' }}</template>
                </Column>
                <Column header="Marca">
                    <template #body="{ data }">{{ data.brand?.name ?? '—' }}</template>
                </Column>
                <Column header="Costo" field="cost" sortable>
                    <template #body="{ data }">{{ money(data.cost) }}</template>
                </Column>
                <Column header="Precio" field="price" sortable>
                    <template #body="{ data }">
                        <span class="font-semibold">{{ money(data.price) }}</span>
                    </template>
                </Column>
                <Column header="Margen">
                    <template #body="{ data }">
                        <Tag :value="`${data.profit_margin}%`" :severity="data.profit_margin >= 30 ? 'success' : 'warn'" />
                    </template>
                </Column>
                <Column header="Estado">
                    <template #body="{ data }">
                        <Tag :value="data.is_active ? 'Activo' : 'Inactivo'" :severity="data.is_active ? 'success' : 'danger'" />
                    </template>
                </Column>
                <Column header="" header-style="width:6rem">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-1">
                            <Button
                                v-if="auth.can('products.print')"
                                icon="pi pi-tag"
                                text
                                rounded
                                size="small"
                                @click="openLabels([data.id])"
                                v-tooltip.top="'Imprimir etiqueta'"
                            />
                            <Button
                                v-if="auth.can('products.edit')"
                                icon="pi pi-pencil"
                                text
                                rounded
                                size="small"
                                @click="openEdit(data)"
                                v-tooltip.top="'Editar'"
                            />
                            <Button
                                v-if="auth.can('products.delete')"
                                icon="pi pi-trash"
                                text
                                rounded
                                size="small"
                                severity="danger"
                                @click="confirmDelete(data)"
                                v-tooltip.top="'Eliminar'"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <ProductFormDialog v-model:visible="dialogVisible" :product="editing" @saved="onSaved" />
        <LabelPrintDialog v-model:visible="labelDialogVisible" :ids="labelIds" />
    </div>
</template>
