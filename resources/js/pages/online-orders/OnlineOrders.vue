<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import DataTable, { type DataTablePageEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import { AxiosError } from 'axios';
import { onlineOrdersApi, ORDER_STATUS, type OnlineOrder, type OnlineOrderStatus } from '@/services/onlineOrders';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const auth = useAuthStore();
const money = (n: number): string => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);
const dt = (s: string | null): string => (s ? new Date(s).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' }) : '');

const rows = ref<OnlineOrder[]>([]);
const total = ref(0);
const loading = ref(true);
const params = reactive({ page: 1, per_page: 15, search: '', status: 'active' as string | null, fulfillment: null as string | null });

const statusOptions = [
    { label: 'En curso (por atender)', value: 'active' },
    ...Object.entries(ORDER_STATUS).map(([value, s]) => ({ label: s.label, value })),
];
const fulfillmentOptions = [{ label: 'Delivery', value: 'delivery' }, { label: 'Recojo en el centro', value: 'pickup' }];

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await onlineOrdersApi.list(params);
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

// --- Detalle y seguimiento ------------------------------------------------
const detail = ref<OnlineOrder | null>(null);
const note = ref('');
const moving = ref<string | null>(null);

async function open(o: OnlineOrder): Promise<void> {
    note.value = '';
    detail.value = await onlineOrdersApi.get(o.id);
}

async function move(to: OnlineOrderStatus): Promise<void> {
    if (!detail.value) return;
    moving.value = to;
    try {
        detail.value = await onlineOrdersApi.status(detail.value.id, to, note.value.trim() || undefined);
        note.value = '';
        toast.add({
            severity: 'success',
            summary: `Pedido ${detail.value.code}: ${ORDER_STATUS[to].label}`,
            detail: to === 'cancelled' ? 'Se anuló su venta y el stock volvió al almacén. El reembolso se hace desde Izipay.' : 'El cliente lo verá en su seguimiento.',
            life: 4000,
        });
        load();
    } catch (e) {
        const ax = e as AxiosError<{ message?: string }>;
        toast.add({ severity: 'warn', summary: 'No se pudo cambiar', detail: ax.response?.data?.message, life: 4000 });
    } finally {
        moving.value = null;
    }
}

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Pedidos online</h1>
            <p class="text-sm text-slate-500">Pedidos de la tienda web pagados con Izipay y su seguimiento de entrega</p>
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap gap-3">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="params.search" placeholder="Código, cliente o correo…" class="w-72" @input="onSearch" />
                </IconField>
                <Select v-model="params.status" :options="statusOptions" option-label="label" option-value="value" show-clear placeholder="Todos los estados" class="w-56" @change="reload" />
                <Select v-model="params.fulfillment" :options="fulfillmentOptions" option-label="label" option-value="value" show-clear placeholder="Entrega" class="w-48" @change="reload" />
            </div>
            <DataTable
                :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                :first="(params.page - 1) * params.per_page" :rows-per-page-options="[15, 30, 50]" class="text-sm" @page="onPage"
            >
                <template #empty><div class="py-8 text-center text-slate-400">No hay pedidos con ese filtro.</div></template>
                <Column header="Pedido"><template #body="{ data }"><span class="font-semibold">{{ data.code }}</span></template></Column>
                <Column header="Fecha"><template #body="{ data }"><span class="text-xs">{{ dt(data.ordered_at) }}</span></template></Column>
                <Column header="Cliente">
                    <template #body="{ data }">
                        <p class="font-medium">{{ data.customer_name ?? data.recipient_name }}</p>
                        <p class="text-xs text-slate-400">{{ data.customer_email }}</p>
                    </template>
                </Column>
                <Column header="Entrega">
                    <template #body="{ data }">
                        <i :class="['pi mr-1', data.fulfillment === 'delivery' ? 'pi-truck' : 'pi-shop']"></i>
                        {{ data.fulfillment === 'delivery' ? (data.district ?? 'Delivery') : 'Recojo' }}
                    </template>
                </Column>
                <Column header="Total"><template #body="{ data }"><span class="font-semibold">{{ money(data.total) }}</span></template></Column>
                <Column header="Estado">
                    <template #body="{ data }"><Tag :value="ORDER_STATUS[data.status as OnlineOrderStatus].label" :severity="ORDER_STATUS[data.status as OnlineOrderStatus].severity" /></template>
                </Column>
                <Column header="" header-style="width:4rem">
                    <template #body="{ data }"><Button icon="pi pi-eye" text rounded size="small" @click="open(data)" /></template>
                </Column>
            </DataTable>
        </div>

        <Dialog :visible="!!detail" modal :header="detail ? `Pedido ${detail.code}` : ''" :style="{ width: '640px' }" @update:visible="detail = null">
            <div v-if="detail" class="space-y-4 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <Tag :value="ORDER_STATUS[detail.status].label" :severity="ORDER_STATUS[detail.status].severity" />
                    <span v-if="detail.sale" class="text-xs text-slate-500">Venta {{ detail.sale.full_number }}{{ detail.sale.status === 'cancelled' ? ' (anulada)' : '' }}</span>
                    <span v-if="detail.payment_reference" class="text-xs text-slate-500">· Izipay {{ detail.payment_reference }}</span>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-xs text-slate-400">Cliente</p>
                        <p class="font-medium">{{ detail.customer_name }}</p>
                        <p class="text-xs">{{ detail.customer_email }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">{{ detail.fulfillment === 'delivery' ? 'Entregar a' : 'Recoge' }}</p>
                        <p class="font-medium">{{ detail.recipient_name }} · {{ detail.phone }}</p>
                        <p v-if="detail.fulfillment === 'delivery'" class="text-xs">{{ detail.address }}, {{ detail.district }}<span v-if="detail.reference"> ({{ detail.reference }})</span></p>
                    </div>
                </div>
                <p v-if="detail.notes" class="rounded-lg bg-slate-50 p-2 text-xs dark:bg-white/5">{{ detail.notes }}</p>

                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="(i, k) in detail.items" :key="k" class="border-b border-[var(--surface-border)]">
                            <td class="py-1">{{ i.quantity }} × {{ i.name }}</td>
                            <td class="py-1 text-right">{{ money(i.subtotal) }}</td>
                        </tr>
                        <tr v-if="detail.delivery_fee > 0"><td class="py-1 text-slate-500">Delivery</td><td class="py-1 text-right">{{ money(detail.delivery_fee) }}</td></tr>
                        <tr><td class="py-1 font-bold">Total</td><td class="py-1 text-right font-bold">{{ money(detail.total) }}</td></tr>
                    </tbody>
                </table>

                <div>
                    <p class="mb-1 text-xs text-slate-400">Seguimiento</p>
                    <ol class="space-y-1 border-l-2 border-[var(--surface-border)] pl-3">
                        <li v-for="(h, k) in detail.histories" :key="k">
                            <span class="font-medium">{{ ORDER_STATUS[h.status as OnlineOrderStatus]?.label ?? h.status }}</span>
                            <span class="text-xs text-slate-400"> · {{ dt(h.happened_at) }}{{ h.user_name ? ` · ${h.user_name}` : '' }}</span>
                            <p v-if="h.note" class="text-xs" :class="h.internal ? 'italic text-amber-600' : 'text-slate-500'">{{ h.note }}</p>
                        </li>
                    </ol>
                </div>

                <div v-if="detail.next_statuses.length && auth.can('online_orders.edit')" class="space-y-2 border-t border-[var(--surface-border)] pt-3">
                    <Textarea v-model="note" rows="2" auto-resize class="w-full" placeholder="Nota para el cliente (opcional): n.º de seguimiento, hora de entrega…" maxlength="500" />
                    <div class="flex flex-wrap justify-end gap-2">
                        <Button
                            v-for="s in detail.next_statuses" :key="s"
                            :label="s === 'cancelled' ? 'Anular pedido' : `Pasar a «${ORDER_STATUS[s].label}»`"
                            :severity="s === 'cancelled' ? 'danger' : undefined" :outlined="s === 'cancelled'"
                            :icon="s === 'cancelled' ? 'pi pi-ban' : 'pi pi-arrow-right'"
                            :loading="moving === s" :disabled="!!moving" @click="move(s)"
                        />
                    </div>
                </div>
            </div>
        </Dialog>
    </div>
</template>
