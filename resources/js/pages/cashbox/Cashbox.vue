<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import { AxiosError } from 'axios';
import { cashboxApi, type CashSession } from '@/services/cashbox';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();

const session = ref<CashSession | null>(null);
const loading = ref(true);
const openingAmount = ref(0);
const busy = ref(false);

const moveDialog = ref(false);
const move = ref({ type: 'income' as 'income' | 'expense', amount: 0, reason: '' });

const closeDialog = ref(false);
const counted = ref(0);
const closeNotes = ref('');

const money = (n: number | null): string => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n ?? 0);
const dt = (s: string | null): string => (s ? new Date(s).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' }) : '');

const expectedNow = (): number => {
    if (!session.value) return 0;
    return session.value.opening_amount + session.value.income - session.value.expense; // + ventas efectivo (se calcula al cerrar)
};

async function refresh(): Promise<void> {
    loading.value = true;
    try {
        session.value = await cashboxApi.current();
    } finally {
        loading.value = false;
    }
}

async function openCash(): Promise<void> {
    busy.value = true;
    try {
        session.value = await cashboxApi.open(openingAmount.value);
        toast.add({ severity: 'success', summary: 'Caja abierta', life: 2500 });
    } catch (e) {
        const ax = e as AxiosError<{ message?: string }>;
        toast.add({ severity: 'error', summary: 'Error', detail: ax.response?.data?.message, life: 4000 });
    } finally {
        busy.value = false;
    }
}

async function submitMove(): Promise<void> {
    busy.value = true;
    try {
        session.value = await cashboxApi.movement(move.value.type, move.value.amount, move.value.reason);
        moveDialog.value = false;
        move.value = { type: 'income', amount: 0, reason: '' };
        toast.add({ severity: 'success', summary: 'Movimiento registrado', life: 2000 });
    } catch (e) {
        const ax = e as AxiosError<{ message?: string }>;
        toast.add({ severity: 'error', summary: 'Error', detail: ax.response?.data?.message, life: 4000 });
    } finally {
        busy.value = false;
    }
}

async function submitClose(): Promise<void> {
    busy.value = true;
    try {
        const closed = await cashboxApi.close(counted.value, closeNotes.value || undefined);
        closeDialog.value = false;
        const diff = closed.difference ?? 0;
        confirm.require({
            header: 'Arqueo de caja',
            message: `Esperado: ${money(closed.expected_amount)} · Contado: ${money(closed.counted_amount)}\nDiferencia: ${money(diff)} ${diff === 0 ? '(cuadra)' : diff > 0 ? '(sobrante)' : '(faltante)'}`,
            icon: 'pi pi-check-circle',
            acceptLabel: 'Entendido',
            rejectClass: 'hidden',
        });
        session.value = null;
        counted.value = 0;
        closeNotes.value = '';
    } catch (e) {
        const ax = e as AxiosError<{ message?: string }>;
        toast.add({ severity: 'error', summary: 'Error', detail: ax.response?.data?.message, life: 4000 });
    } finally {
        busy.value = false;
    }
}

onMounted(refresh);
</script>

<template>
    <div class="space-y-5">
        <h1 class="text-2xl font-bold tracking-tight">Caja</h1>

        <div v-if="loading" class="grid place-items-center py-20 text-slate-400"><i class="pi pi-spin pi-spinner text-2xl"></i></div>

        <!-- Sin caja abierta: apertura -->
        <div v-else-if="!session" class="mx-auto max-w-md rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-8 text-center shadow-sm">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-500/10"><i class="pi pi-wallet text-2xl"></i></span>
            <h2 class="mt-4 text-lg font-semibold">No hay una caja abierta</h2>
            <p class="mt-1 text-sm text-slate-500">Ingresa el monto inicial para abrir tu turno.</p>
            <div class="mt-6 text-left">
                <label class="mb-1 block text-sm font-medium">Monto de apertura</label>
                <InputNumber v-model="openingAmount" mode="currency" currency="PEN" locale="es-PE" class="w-full" :min="0" />
            </div>
            <Button v-if="auth.can('cashbox.create')" label="Abrir caja" icon="pi pi-lock-open" class="mt-4 w-full" :loading="busy" @click="openCash" />
        </div>

        <!-- Caja abierta -->
        <div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
                        <p class="text-xs text-slate-500">Apertura</p>
                        <p class="mt-1 text-lg font-bold">{{ money(session.opening_amount) }}</p>
                    </div>
                    <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
                        <p class="text-xs text-slate-500">Ingresos</p>
                        <p class="mt-1 text-lg font-bold text-emerald-600">{{ money(session.income) }}</p>
                    </div>
                    <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
                        <p class="text-xs text-slate-500">Egresos</p>
                        <p class="mt-1 text-lg font-bold text-rose-600">{{ money(session.expense) }}</p>
                    </div>
                    <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
                        <p class="text-xs text-slate-500">En caja (aprox.)</p>
                        <p class="mt-1 text-lg font-bold">{{ money(expectedNow()) }}</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
                    <h3 class="mb-3 font-semibold">Movimientos del turno</h3>
                    <ul class="divide-y divide-[var(--surface-border)]">
                        <li v-for="mv in session.movements ?? []" :key="mv.id" class="flex items-center justify-between py-2.5">
                            <div>
                                <p class="text-sm font-medium">{{ mv.reason }}</p>
                                <p class="text-xs text-slate-400">{{ dt(mv.created_at) }}</p>
                            </div>
                            <span class="font-semibold" :class="mv.type === 'income' ? 'text-emerald-600' : 'text-rose-600'">
                                {{ mv.type === 'income' ? '+' : '-' }}{{ money(mv.amount) }}
                            </span>
                        </li>
                        <li v-if="!(session.movements?.length)" class="py-6 text-center text-sm text-slate-400">Sin movimientos manuales</li>
                    </ul>
                </div>
            </div>

            <!-- Acciones -->
            <div class="h-min space-y-3 rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-500">Turno abierto</span>
                    <Tag value="Abierta" severity="success" />
                </div>
                <p class="text-xs text-slate-400">Desde {{ dt(session.opened_at) }} · {{ session.register }}</p>
                <Button v-if="auth.can('cashbox.create')" label="Registrar movimiento" icon="pi pi-plus" outlined class="w-full" @click="moveDialog = true" />
                <Button v-if="auth.can('cashbox.edit')" label="Cerrar caja (arqueo)" icon="pi pi-lock" severity="danger" class="w-full" @click="counted = expectedNow(); closeDialog = true" />
            </div>
        </div>

        <!-- Diálogo movimiento -->
        <Dialog v-model:visible="moveDialog" modal header="Registrar movimiento" :style="{ width: '420px' }">
            <div class="space-y-4">
                <Select v-model="move.type" :options="[{ label: 'Ingreso', value: 'income' }, { label: 'Egreso', value: 'expense' }]" option-label="label" option-value="value" class="w-full" />
                <div>
                    <label class="mb-1 block text-sm font-medium">Monto</label>
                    <InputNumber v-model="move.amount" mode="currency" currency="PEN" locale="es-PE" class="w-full" :min="0" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Motivo</label>
                    <InputText v-model="move.reason" class="w-full" placeholder="Propina, gasto, retiro…" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="moveDialog = false" />
                <Button label="Registrar" icon="pi pi-check" :loading="busy" @click="submitMove" />
            </template>
        </Dialog>

        <!-- Diálogo cierre -->
        <Dialog v-model:visible="closeDialog" modal header="Cerrar caja — Arqueo" :style="{ width: '440px' }">
            <div class="space-y-4">
                <p class="text-sm text-slate-500">Cuenta el efectivo físico en la caja e ingrésalo para calcular la diferencia.</p>
                <div>
                    <label class="mb-1 block text-sm font-medium">Monto contado</label>
                    <InputNumber v-model="counted" mode="currency" currency="PEN" locale="es-PE" class="w-full" :min="0" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Observaciones</label>
                    <InputText v-model="closeNotes" class="w-full" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="closeDialog = false" />
                <Button label="Cerrar caja" icon="pi pi-lock" severity="danger" :loading="busy" @click="submitClose" />
            </template>
        </Dialog>
    </div>
</template>
