<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import DataTable, { type DataTablePageEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import { AxiosError } from 'axios';
import { commissionsApi, RULE_TYPES, type Commission, type CommissionRule } from '@/services/commissions';
import { employeesApi, type Employee } from '@/services/staff';
import { productsApi, type Product } from '@/services/catalog';
import { isoDate } from '@/services/agenda';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();
const money = (n: number): string => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);
const dayLabel = (s: string): string => new Date(`${s}T00:00:00`).toLocaleDateString('es-PE');
const tab = ref('commissions');

const employees = ref<Employee[]>([]);
const services = ref<Product[]>([]);

// --- Comisiones ----------------------------------------------------------------
const rows = ref<Commission[]>([]);
const total = ref(0);
const totals = ref({ pending: 0, paid: 0 });
const loading = ref(true);
const range = ref<Date[] | null>(null);
const params = reactive({ page: 1, per_page: 15, employee_id: null as number | null, status: 'pending' as string | null });
const selected = ref<Commission[]>([]);
const paying = ref(false);
const statusOptions = [{ label: 'Pendientes', value: 'pending' }, { label: 'Pagadas', value: 'paid' }];
const selectedAmount = computed(() => selected.value.reduce((s, c) => s + c.amount, 0));

async function load(): Promise<void> {
    loading.value = true;
    selected.value = [];
    try {
        const res = await commissionsApi.list({
            ...params,
            from: range.value?.[0] ? isoDate(range.value[0]) : undefined,
            to: range.value?.[1] ? isoDate(range.value[1]) : (range.value?.[0] ? isoDate(range.value[0]) : undefined),
        });
        rows.value = res.data;
        total.value = res.meta.total;
        totals.value = res.totals;
    } finally {
        loading.value = false;
    }
}
const reload = (): void => { params.page = 1; load(); };
const onPage = (e: DataTablePageEvent): void => { params.page = e.page + 1; params.per_page = e.rows; load(); };

function pay(): void {
    const ids = selected.value.filter((c) => c.status === 'pending').map((c) => c.id);
    if (!ids.length) return;
    confirm.require({
        message: `¿Marcar como pagadas ${ids.length} comisiones por ${money(selectedAmount.value)}?`,
        header: 'Pagar comisiones', icon: 'pi pi-wallet',
        acceptLabel: 'Pagar', rejectLabel: 'Cancelar',
        accept: async () => {
            paying.value = true;
            try {
                const res = await commissionsApi.pay(ids);
                toast.add({ severity: 'success', summary: `${res.paid} comisiones pagadas`, life: 2500 });
                load();
            } finally {
                paying.value = false;
            }
        },
    });
}

// --- Reglas -----------------------------------------------------------------------
const rules = ref<CommissionRule[]>([]);
const rulesLoading = ref(false);
const ruleDialog = ref(false);
const ruleSaving = ref(false);
const ruleErrors = ref<Record<string, string[]>>({});
const ruleId = ref<number | null>(null);
const ruleForm = ref({ employee_id: null as number | null, service_id: null as number | null, type: 'percentage' as 'percentage' | 'fixed', value: 10, is_active: true });

async function loadRules(): Promise<void> {
    rulesLoading.value = true;
    try {
        rules.value = await commissionsApi.rules();
    } finally {
        rulesLoading.value = false;
    }
}
async function openRule(rule?: CommissionRule): Promise<void> {
    if (!services.value.length) services.value = (await productsApi.list({ type: 'service', is_active: 1, per_page: 100, sort_by: 'name', sort_dir: 'asc' })).data;
    ruleId.value = rule?.id ?? null;
    ruleErrors.value = {};
    ruleForm.value = rule
        ? { employee_id: rule.employee_id, service_id: rule.service_id, type: rule.type, value: rule.value, is_active: rule.is_active }
        : { employee_id: null, service_id: null, type: 'percentage', value: 10, is_active: true };
    ruleDialog.value = true;
}
async function saveRule(): Promise<void> {
    ruleSaving.value = true;
    ruleErrors.value = {};
    try {
        await commissionsApi.saveRule(ruleForm.value, ruleId.value ?? undefined);
        ruleDialog.value = false;
        loadRules();
    } catch (e) {
        const ax = e as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;
        if (ax.response?.data?.errors) ruleErrors.value = ax.response.data.errors;
        else toast.add({ severity: 'warn', summary: 'No se pudo guardar', detail: ax.response?.data?.message, life: 4000 });
    } finally {
        ruleSaving.value = false;
    }
}
function removeRule(rule: CommissionRule): void {
    confirm.require({
        message: '¿Eliminar esta regla? Las comisiones ya generadas no cambian.',
        header: 'Confirmar', icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar', rejectLabel: 'Cancelar', acceptClass: 'p-button-danger',
        accept: async () => { await commissionsApi.removeRule(rule.id); loadRules(); },
    });
}
const ruleValue = (r: CommissionRule): string => (r.type === 'percentage' ? `${r.value}%` : money(r.value));

function onTab(value: string | number): void {
    tab.value = String(value);
    if (tab.value === 'rules' && !rules.value.length) loadRules();
}

onMounted(async () => {
    employees.value = await employeesApi.options();
    load();
});
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Comisiones</h1>
                <p class="text-sm text-slate-500">Se generan solas al registrar cada atención, según las reglas</p>
            </div>
            <Button v-if="tab === 'rules' && auth.can('commissions.edit')" label="Nueva regla" icon="pi pi-plus" @click="openRule()" />
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <Tabs :value="tab" @update:value="onTab">
                <TabList>
                    <Tab value="commissions">Comisiones</Tab>
                    <Tab value="rules">Reglas</Tab>
                </TabList>
                <TabPanels>
                    <TabPanel value="commissions">
                        <div class="mb-3 grid grid-cols-2 gap-3 md:w-96">
                            <div class="rounded-xl bg-amber-50 p-3 dark:bg-amber-500/10"><p class="text-xs text-slate-500">Pendiente</p><p class="text-lg font-bold">{{ money(totals.pending) }}</p></div>
                            <div class="rounded-xl bg-emerald-50 p-3 dark:bg-emerald-500/10"><p class="text-xs text-slate-500">Pagado</p><p class="text-lg font-bold">{{ money(totals.paid) }}</p></div>
                        </div>
                        <div class="mb-3 flex flex-wrap items-center gap-3">
                            <Select v-model="params.employee_id" :options="employees" option-label="name" option-value="id" show-clear placeholder="Todo el personal" class="w-56" @change="reload" />
                            <Select v-model="params.status" :options="statusOptions" option-label="label" option-value="value" show-clear placeholder="Todas" class="w-40" @change="reload" />
                            <DatePicker v-model="range" selection-mode="range" date-format="dd/mm/yy" show-icon show-button-bar placeholder="Rango de fechas" class="w-64" @update:model-value="reload" />
                            <Button
                                v-if="auth.can('commissions.pay')" class="ml-auto" icon="pi pi-wallet" :loading="paying"
                                :label="selected.length ? `Pagar ${money(selectedAmount)}` : 'Pagar seleccionadas'" :disabled="!selected.length" @click="pay"
                            />
                        </div>
                        <DataTable
                            v-model:selection="selected" :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                            :first="(params.page - 1) * params.per_page" :rows-per-page-options="[15, 30, 50]" data-key="id" class="text-sm" @page="onPage"
                        >
                            <template #empty><div class="py-8 text-center text-slate-400">Sin comisiones.</div></template>
                            <Column selection-mode="multiple" header-style="width:3rem" />
                            <Column header="Fecha"><template #body="{ data }">{{ dayLabel(data.generated_at) }}</template></Column>
                            <Column header="Empleado" field="employee" />
                            <Column header="Servicio"><template #body="{ data }">{{ data.service ?? '—' }}</template></Column>
                            <Column header="Base"><template #body="{ data }">{{ money(data.base_amount) }}</template></Column>
                            <Column header="Regla"><template #body="{ data }">{{ data.type === 'percentage' ? `${data.value}%` : 'Fijo' }}</template></Column>
                            <Column header="Comisión"><template #body="{ data }"><span class="font-semibold">{{ money(data.amount) }}</span></template></Column>
                            <Column header="Estado">
                                <template #body="{ data }">
                                    <Tag :value="data.status === 'paid' ? 'Pagada' : 'Pendiente'" :severity="data.status === 'paid' ? 'success' : 'warn'" />
                                    <p v-if="data.paid_by" class="mt-1 text-xs text-slate-400">{{ data.paid_by }}</p>
                                </template>
                            </Column>
                        </DataTable>
                    </TabPanel>

                    <TabPanel value="rules">
                        <p class="mb-3 text-sm text-slate-500">Se aplica la regla más específica: empleado + servicio, luego solo empleado, luego solo servicio y por último la general.</p>
                        <DataTable :value="rules" :loading="rulesLoading" class="text-sm">
                            <template #empty><div class="py-8 text-center text-slate-400">Sin reglas: ninguna atención genera comisión.</div></template>
                            <Column header="Empleado"><template #body="{ data }">{{ data.employee ?? 'Todos' }}</template></Column>
                            <Column header="Servicio"><template #body="{ data }">{{ data.service ?? 'Todos' }}</template></Column>
                            <Column header="Comisión"><template #body="{ data }"><span class="font-semibold">{{ ruleValue(data) }}</span></template></Column>
                            <Column header="Estado">
                                <template #body="{ data }"><Tag :value="data.is_active ? 'Activa' : 'Inactiva'" :severity="data.is_active ? 'success' : 'secondary'" /></template>
                            </Column>
                            <Column header="" header-style="width:6rem">
                                <template #body="{ data }">
                                    <div v-if="auth.can('commissions.edit')" class="flex justify-end gap-1">
                                        <Button icon="pi pi-pencil" text rounded size="small" @click="openRule(data)" />
                                        <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="removeRule(data)" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>

        <Dialog v-model:visible="ruleDialog" modal :header="ruleId ? 'Editar regla' : 'Nueva regla'" :style="{ width: '460px' }">
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">Empleado</label>
                    <Select v-model="ruleForm.employee_id" :options="employees" option-label="name" option-value="id" show-clear placeholder="Todos" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Servicio</label>
                    <Select v-model="ruleForm.service_id" :options="services" option-label="name" option-value="id" show-clear filter placeholder="Todos" class="w-full" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Tipo</label>
                        <Select v-model="ruleForm.type" :options="RULE_TYPES" option-label="label" option-value="value" class="w-full" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">{{ ruleForm.type === 'percentage' ? 'Porcentaje' : 'Monto' }}</label>
                        <InputNumber v-model="ruleForm.value" :min="0" :max="ruleForm.type === 'percentage' ? 100 : undefined" :max-fraction-digits="2" :suffix="ruleForm.type === 'percentage' ? ' %' : ''" class="w-full" :invalid="!!ruleErrors.value" />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="ruleForm.is_active" input-id="rule-active" />
                    <label for="rule-active" class="text-sm font-medium">Activa</label>
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="ruleDialog = false" />
                <Button label="Guardar" icon="pi pi-check" :loading="ruleSaving" @click="saveRule" />
            </template>
        </Dialog>
    </div>
</template>
