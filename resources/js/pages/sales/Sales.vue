<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import DataTable, { type DataTablePageEvent, type DataTableSortEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { AxiosError } from 'axios';
import { salesApi, DOC_TYPES, type Sale } from '@/services/sales';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const toast = useToast();
const rows = ref<Sale[]>([]);
const total = ref(0);
const loading = ref(true);
const params = reactive({ page: 1, per_page: 15, search: '', doc_type: null as string | null, sort_by: 'sold_at', sort_dir: 'desc' as 'asc' | 'desc' });

const money = (n: number): string => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);
const dt = (s: string | null): string => (s ? new Date(s).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' }) : '');

const docSeverity: Record<string, string> = { ticket: 'secondary', boleta: 'info', factura: 'success', cotizacion: 'warn' };

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await salesApi.list(params);
        rows.value = res.data;
        total.value = res.meta.total;
    } finally {
        loading.value = false;
    }
}

let t: number | undefined;
const onSearch = (): void => { window.clearTimeout(t); t = window.setTimeout(() => { params.page = 1; load(); }, 350); };
const onPage = (e: DataTablePageEvent): void => { params.page = e.page + 1; params.per_page = e.rows; load(); };
const onSort = (e: DataTableSortEvent): void => { params.sort_by = (e.sortField as string) || 'sold_at'; params.sort_dir = e.sortOrder === 1 ? 'asc' : 'desc'; load(); };
const reload = (): void => { params.page = 1; load(); };
const openTicket = (s: Sale): void => { window.open(salesApi.ticketUrl(s.id), '_blank'); };

// --- Anulación de venta --------------------------------------------------
const cancelDialog = ref(false);
const cancelTarget = ref<Sale | null>(null);
const cancelReason = ref('');
const cancelling = ref(false);

const askCancel = (s: Sale): void => {
    cancelTarget.value = s;
    cancelReason.value = '';
    cancelDialog.value = true;
};

async function confirmCancel(): Promise<void> {
    if (!cancelTarget.value) return;
    cancelling.value = true;
    try {
        await salesApi.cancel(cancelTarget.value.id, cancelReason.value.trim() || undefined);
        toast.add({ severity: 'success', summary: 'Venta anulada', detail: 'El stock fue devuelto al inventario.', life: 3000 });
        cancelDialog.value = false;
        load();
    } catch (e) {
        const ax = e as AxiosError<{ message?: string }>;
        toast.add({ severity: 'warn', summary: 'No se pudo anular', detail: ax.response?.data?.message, life: 4000 });
    } finally {
        cancelling.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Ventas</h1>
                <p class="text-sm text-slate-500">{{ total }} comprobantes emitidos</p>
            </div>
            <router-link v-if="auth.can('sales.create')" to="/pos">
                <Button label="Nueva venta (POS)" icon="pi pi-desktop" />
            </router-link>
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="params.search" placeholder="Buscar comprobante…" class="w-64" @input="onSearch" />
                </IconField>
                <Select v-model="params.doc_type" :options="DOC_TYPES" option-label="label" option-value="value" class="w-44" show-clear placeholder="Todos los tipos" @change="reload" />
            </div>

            <DataTable
                :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                :first="(params.page - 1) * params.per_page" :rows-per-page-options="[15, 30, 50]" removable-sort class="text-sm"
                @page="onPage" @sort="onSort"
            >
                <template #empty><div class="py-8 text-center text-slate-400">Sin ventas registradas.</div></template>
                <Column header="Comprobante" field="full_number" sortable>
                    <template #body="{ data }">
                        <span class="font-semibold">{{ data.full_number }}</span>
                        <Tag class="ml-2" :value="data.doc_type" :severity="docSeverity[data.doc_type] ?? 'secondary'" />
                    </template>
                </Column>
                <Column header="Fecha" field="sold_at" sortable>
                    <template #body="{ data }"><span class="text-xs">{{ dt(data.sold_at) }}</span></template>
                </Column>
                <Column header="Cliente">
                    <template #body="{ data }">{{ data.customer?.name ?? 'Público general' }}</template>
                </Column>
                <Column header="Vendedor">
                    <template #body="{ data }">{{ data.user ?? '—' }}</template>
                </Column>
                <Column header="Total" field="total" sortable>
                    <template #body="{ data }"><span class="font-semibold">{{ money(data.total) }}</span></template>
                </Column>
                <Column header="Estado">
                    <template #body="{ data }">
                        <Tag v-if="data.status === 'completed'" value="Completada" severity="success" />
                        <Tag v-else-if="data.status === 'quotation'" value="Cotización" severity="warn" />
                        <Tag v-else value="Anulada" severity="danger" />
                    </template>
                </Column>
                <Column header="" header-style="width:7rem">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-1">
                            <Button v-if="auth.can('sales.print')" icon="pi pi-print" text rounded size="small" @click="openTicket(data)" v-tooltip.top="'Imprimir'" />
                            <Button
                                v-if="auth.can('sales.cancel') && data.status === 'completed'"
                                icon="pi pi-ban" text rounded size="small" severity="danger"
                                @click="askCancel(data)" v-tooltip.top="'Anular venta'"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Diálogo de anulación -->
        <Dialog v-model:visible="cancelDialog" modal header="Anular venta" :style="{ width: '460px' }">
            <div class="space-y-4">
                <div class="flex items-start gap-3 rounded-xl bg-rose-50 p-3 text-sm dark:bg-rose-500/10">
                    <i class="pi pi-exclamation-triangle mt-0.5 text-rose-500"></i>
                    <p class="text-slate-600 dark:text-slate-300">
                        Vas a anular el comprobante <span class="font-semibold">{{ cancelTarget?.full_number }}</span>
                        por <span class="font-semibold">{{ money(cancelTarget?.total ?? 0) }}</span>.
                        El stock de los productos volverá al inventario y quedará registrado en auditoría quién realizó la anulación.
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Motivo <span class="text-slate-400">(opcional)</span></label>
                    <Textarea v-model="cancelReason" rows="3" class="w-full" placeholder="Ej. Error de digitación, devolución del cliente…" maxlength="255" autofocus />
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="cancelDialog = false" :disabled="cancelling" />
                <Button label="Anular venta" icon="pi pi-ban" severity="danger" :loading="cancelling" @click="confirmCancel" />
            </template>
        </Dialog>
    </div>
</template>
