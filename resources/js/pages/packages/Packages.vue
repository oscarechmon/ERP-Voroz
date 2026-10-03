<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import DataTable, { type DataTablePageEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import ToggleSwitch from 'primevue/toggleswitch';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Message from 'primevue/message';
import { AxiosError } from 'axios';
import { packagesApi, customerPackagesApi, CUSTOMER_PACKAGE_STATUS, type Package, type PackagePayload, type CustomerPackage } from '@/services/packages';
import { productsApi, type Product } from '@/services/catalog';
import { customersApi, type Customer } from '@/services/contacts';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();
const money = (n: number): string => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);
const day = (s: string | null): string => (s ? new Date(`${s}T00:00:00`).toLocaleDateString('es-PE') : '—');

const tab = ref('catalog');

// --- Catálogo -------------------------------------------------------------
const rows = ref<Package[]>([]);
const total = ref(0);
const loading = ref(true);
const params = reactive({ page: 1, per_page: 15, search: '', sort_by: 'name', sort_dir: 'asc' as 'asc' | 'desc' });
const services = ref<Product[]>([]);

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await packagesApi.list(params);
        rows.value = res.data;
        total.value = res.meta.total;
    } finally {
        loading.value = false;
    }
}
let t: number | undefined;
const onSearch = (): void => { window.clearTimeout(t); t = window.setTimeout(() => { params.page = 1; load(); }, 350); };
const onPage = (e: DataTablePageEvent): void => { params.page = e.page + 1; params.per_page = e.rows; load(); };

const dialog = ref(false);
const saving = ref(false);
const errors = ref<Record<string, string[]>>({});
const editingId = ref<number | null>(null);
const blank = (): PackagePayload => ({ name: '', description: '', price: 0, total_sessions: 4, validity_days: null, is_active: true, web_published: false, service_ids: [] });
const form = ref<PackagePayload>(blank());

async function loadServices(): Promise<void> {
    if (!services.value.length) {
        services.value = (await productsApi.list({ type: 'service', is_active: 1, per_page: 100, sort_by: 'name', sort_dir: 'asc' })).data;
    }
}
async function openCreate(): Promise<void> {
    await loadServices();
    editingId.value = null;
    errors.value = {};
    form.value = blank();
    dialog.value = true;
}
async function openEdit(p: Package): Promise<void> {
    await loadServices();
    editingId.value = p.id;
    errors.value = {};
    form.value = {
        name: p.name, description: p.description ?? '', price: p.price, total_sessions: p.total_sessions,
        validity_days: p.validity_days, is_active: p.is_active, web_published: p.web_published, service_ids: p.services.map((s) => s.id),
    };
    dialog.value = true;
}
async function submit(): Promise<void> {
    saving.value = true;
    errors.value = {};
    try {
        await packagesApi.save(form.value, editingId.value ?? undefined);
        dialog.value = false;
        toast.add({ severity: 'success', summary: 'Guardado', detail: 'El paquete ya se puede vender en el punto de venta.', life: 3000 });
        load();
    } catch (e) {
        const ax = e as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;
        if (ax.response?.status === 422) errors.value = ax.response.data.errors ?? {};
        else toast.add({ severity: 'error', summary: 'No se pudo guardar', detail: ax.response?.data?.message, life: 4000 });
    } finally {
        saving.value = false;
    }
}
function confirmDelete(p: Package): void {
    confirm.require({
        message: `¿Eliminar el paquete «${p.name}»? Los clientes que ya lo compraron conservan sus sesiones.`,
        header: 'Confirmar', icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar', rejectLabel: 'Cancelar', acceptClass: 'p-button-danger',
        accept: async () => { await packagesApi.remove(p.id); toast.add({ severity: 'success', summary: 'Eliminado', life: 2000 }); load(); },
    });
}

// --- Paquetes de clientes -------------------------------------------------
const owned = ref<CustomerPackage[]>([]);
const ownedTotal = ref(0);
const ownedLoading = ref(false);
const ownedParams = reactive({ page: 1, per_page: 15, search: '', status: null as string | null, sort_by: 'purchased_at', sort_dir: 'desc' as 'asc' | 'desc' });
const statusOptions = Object.entries(CUSTOMER_PACKAGE_STATUS).map(([value, s]) => ({ value, label: s.label }));

async function loadOwned(): Promise<void> {
    ownedLoading.value = true;
    try {
        const res = await customerPackagesApi.list(ownedParams);
        owned.value = res.data;
        ownedTotal.value = res.meta.total;
    } finally {
        ownedLoading.value = false;
    }
}
let t2: number | undefined;
const onOwnedSearch = (): void => { window.clearTimeout(t2); t2 = window.setTimeout(() => { ownedParams.page = 1; loadOwned(); }, 350); };
const onOwnedPage = (e: DataTablePageEvent): void => { ownedParams.page = e.page + 1; ownedParams.per_page = e.rows; loadOwned(); };

const assignDialog = ref(false);
const assigning = ref(false);
const customers = ref<Customer[]>([]);
const packageOptions = ref<Package[]>([]);
const assignForm = ref({ customer_id: null as number | null, package_id: null as number | null, price: null as number | null });

async function openAssign(): Promise<void> {
    [customers.value, packageOptions.value] = await Promise.all([customersApi.options(), packagesApi.options()]);
    assignForm.value = { customer_id: null, package_id: null, price: null };
    assignDialog.value = true;
}
async function assign(): Promise<void> {
    if (!assignForm.value.customer_id || !assignForm.value.package_id) return;
    assigning.value = true;
    try {
        await customerPackagesApi.assign({ customer_id: assignForm.value.customer_id, package_id: assignForm.value.package_id, price: assignForm.value.price });
        assignDialog.value = false;
        toast.add({ severity: 'success', summary: 'Paquete asignado', life: 2500 });
        tab.value = 'owned';
        loadOwned();
    } catch (e) {
        const ax = e as AxiosError<{ message?: string }>;
        toast.add({ severity: 'error', summary: 'No se pudo asignar', detail: ax.response?.data?.message, life: 4000 });
    } finally {
        assigning.value = false;
    }
}

function onTab(value: string | number): void {
    tab.value = String(value);
    if (tab.value === 'owned' && !owned.value.length) loadOwned();
}

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Paquetes</h1>
                <p class="text-sm text-slate-500">Paquetes de sesiones y el saldo de cada cliente. Se venden en el punto de venta.</p>
            </div>
            <div class="flex gap-2">
                <Button v-if="auth.can('packages.assign')" label="Asignar a cliente" icon="pi pi-user-plus" outlined @click="openAssign" />
                <Button v-if="auth.can('packages.create')" label="Nuevo paquete" icon="pi pi-plus" @click="openCreate" />
            </div>
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <Tabs :value="tab" @update:value="onTab">
                <TabList>
                    <Tab value="catalog">Catálogo</Tab>
                    <Tab value="owned">Paquetes de clientes</Tab>
                </TabList>
                <TabPanels>
                    <TabPanel value="catalog">
                        <div class="mb-3">
                            <IconField>
                                <InputIcon class="pi pi-search" />
                                <InputText v-model="params.search" placeholder="Buscar paquete…" class="w-72" @input="onSearch" />
                            </IconField>
                        </div>
                        <DataTable
                            :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                            :first="(params.page - 1) * params.per_page" :rows-per-page-options="[15, 30, 50]" class="text-sm" @page="onPage"
                        >
                            <template #empty><div class="py-8 text-center text-slate-400">No hay paquetes en el catálogo.</div></template>
                            <Column header="Paquete">
                                <template #body="{ data }">
                                    <p class="font-medium">{{ data.name }}</p>
                                    <p class="text-xs text-slate-400">{{ data.code }}</p>
                                </template>
                            </Column>
                            <Column header="Servicios incluidos">
                                <template #body="{ data }">
                                    <div class="flex flex-wrap gap-1"><Tag v-for="s in data.services" :key="s.id" :value="s.name" severity="secondary" /></div>
                                </template>
                            </Column>
                            <Column header="Sesiones" field="total_sessions" />
                            <Column header="Precio"><template #body="{ data }"><span class="font-semibold">{{ money(data.price) }}</span></template></Column>
                            <Column header="Vigencia"><template #body="{ data }">{{ data.validity_days ? `${data.validity_days} días` : 'Sin vencimiento' }}</template></Column>
                            <Column header="Estado">
                                <template #body="{ data }"><Tag :value="data.is_active ? 'Activo' : 'Inactivo'" :severity="data.is_active ? 'success' : 'danger'" /></template>
                            </Column>
                            <Column header="" header-style="width:6rem">
                                <template #body="{ data }">
                                    <div class="flex justify-end gap-1">
                                        <Button v-if="auth.can('packages.edit')" icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                                        <Button v-if="auth.can('packages.delete')" icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(data)" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </TabPanel>

                    <TabPanel value="owned">
                        <div class="mb-3 flex flex-wrap gap-3">
                            <IconField>
                                <InputIcon class="pi pi-search" />
                                <InputText v-model="ownedParams.search" placeholder="Cliente o paquete…" class="w-72" @input="onOwnedSearch" />
                            </IconField>
                            <Select v-model="ownedParams.status" :options="statusOptions" option-label="label" option-value="value" show-clear placeholder="Todos los estados" class="w-48" @change="ownedParams.page = 1; loadOwned()" />
                        </div>
                        <DataTable
                            :value="owned" :loading="ownedLoading" lazy paginator :rows="ownedParams.per_page" :total-records="ownedTotal"
                            :first="(ownedParams.page - 1) * ownedParams.per_page" :rows-per-page-options="[15, 30, 50]" class="text-sm" @page="onOwnedPage"
                        >
                            <template #empty><div class="py-8 text-center text-slate-400">Ningún cliente tiene paquetes todavía.</div></template>
                            <Column header="Cliente">
                                <template #body="{ data }">
                                    <p class="font-medium">{{ data.customer?.name }}</p>
                                    <p class="text-xs text-slate-400">{{ data.customer?.code }}</p>
                                </template>
                            </Column>
                            <Column header="Paquete" field="package_name" />
                            <Column header="Sesiones">
                                <template #body="{ data }">
                                    <span class="font-semibold">{{ data.used_sessions }}/{{ data.total_sessions }}</span>
                                    <span class="ml-1 text-xs text-slate-400">({{ data.remaining_sessions }} disponibles)</span>
                                </template>
                            </Column>
                            <Column header="Precio"><template #body="{ data }">{{ money(data.price) }}</template></Column>
                            <Column header="Compra"><template #body="{ data }">{{ day(data.purchased_at) }}</template></Column>
                            <Column header="Vence"><template #body="{ data }">{{ day(data.expires_at) }}</template></Column>
                            <Column header="Estado">
                                <template #body="{ data }">
                                    <Tag :value="CUSTOMER_PACKAGE_STATUS[data.status]?.label ?? data.status" :severity="CUSTOMER_PACKAGE_STATUS[data.status]?.severity" />
                                </template>
                            </Column>
                        </DataTable>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>

        <Dialog v-model:visible="dialog" modal :header="editingId ? 'Editar paquete' : 'Nuevo paquete'" :style="{ width: '620px' }">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Nombre *</label>
                    <InputText v-model="form.name" class="w-full" :invalid="!!errors.name" placeholder="Ej. 6 sesiones de radiofrecuencia" />
                    <Message v-if="errors.name" severity="error" size="small" variant="simple">{{ errors.name[0] }}</Message>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Servicios incluidos *</label>
                    <MultiSelect v-model="form.service_ids" :options="services" option-label="name" option-value="id" class="w-full" filter display="chip" :invalid="!!errors.service_ids" />
                    <Message v-if="errors.service_ids" severity="error" size="small" variant="simple">{{ errors.service_ids[0] }}</Message>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Sesiones *</label>
                    <InputNumber v-model="form.total_sessions" :min="1" class="w-full" :invalid="!!errors.total_sessions" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Precio *</label>
                    <InputNumber v-model="form.price" mode="currency" currency="PEN" locale="es-PE" class="w-full" :invalid="!!errors.price" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Vigencia (días)</label>
                    <InputNumber v-model="form.validity_days" :min="1" class="w-full" placeholder="Sin vencimiento" />
                </div>
                <div class="flex items-end gap-2 pb-2">
                    <ToggleSwitch v-model="form.is_active" input-id="pkg-active" />
                    <label for="pkg-active" class="text-sm font-medium">Se vende</label>
                </div>
                <div class="flex items-center gap-2">
                    <ToggleSwitch
                        :model-value="form.web_published ?? false"
                        input-id="pkg-web"
                        @update:model-value="(value: boolean) => (form.web_published = value)"
                    />
                    <label for="pkg-web" class="text-sm font-medium">Publicar en la web</label>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Descripción</label>
                    <Textarea v-model="form.description" rows="2" auto-resize class="w-full" />
                    <p class="mt-1 text-xs text-slate-400">Es la que se muestra en la web.</p>
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="dialog = false" />
                <Button label="Guardar" icon="pi pi-check" :loading="saving" @click="submit" />
            </template>
        </Dialog>

        <Dialog v-model:visible="assignDialog" modal header="Asignar paquete a un cliente" :style="{ width: '480px' }">
            <div class="space-y-4">
                <p class="rounded-xl bg-amber-50 p-3 text-sm text-slate-600 dark:bg-amber-500/10 dark:text-slate-300">
                    Para cortesías o canjes: no genera venta ni cobro. Lo que se vende, se cobra en el <span class="font-semibold">punto de venta</span>.
                </p>
                <div>
                    <label class="mb-1 block text-sm font-medium">Cliente *</label>
                    <Select v-model="assignForm.customer_id" :options="customers" option-label="name" option-value="id" filter class="w-full" placeholder="Buscar cliente" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Paquete *</label>
                    <Select v-model="assignForm.package_id" :options="packageOptions" option-label="name" option-value="id" filter class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Valor <span class="text-slate-400">(para comisiones; vacío = precio del paquete)</span></label>
                    <InputNumber v-model="assignForm.price" mode="currency" currency="PEN" locale="es-PE" class="w-full" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="assignDialog = false" />
                <Button label="Asignar" icon="pi pi-check" :loading="assigning" :disabled="!assignForm.customer_id || !assignForm.package_id" @click="assign" />
            </template>
        </Dialog>
    </div>
</template>
