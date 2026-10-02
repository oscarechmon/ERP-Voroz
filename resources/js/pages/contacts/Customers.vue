<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import DataTable, { type DataTablePageEvent, type DataTableSortEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
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
import { customersApi, DOC_TYPES, GENDERS, HOW_KNEW, type Customer } from '@/services/contacts';
import { customerPackagesApi, CUSTOMER_PACKAGE_STATUS, type CustomerPackage } from '@/services/packages';
import { attendancesApi, isoDate, type Attendance } from '@/services/agenda';
import { salesApi, type Sale } from '@/services/sales';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();
const money = (n: number): string => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);
const dayLabel = (s: string | null | undefined): string => (s ? new Date(`${s.slice(0, 10)}T00:00:00`).toLocaleDateString('es-PE') : '—');

const rows = ref<Customer[]>([]);
const total = ref(0);
const loading = ref(true);
const params = reactive({ page: 1, per_page: 10, search: '', sort_by: 'created_at', sort_dir: 'desc' as 'asc' | 'desc' });

const dialog = ref(false);
const formTab = ref('data');
const saving = ref(false);
const errors = ref<Record<string, string[]>>({});
const editingId = ref<number | null>(null);
const blank = () => ({
    doc_type: 'DNI', doc_number: '', name: '', email: '', phone: '', whatsapp: '', address: '', district: '',
    birth_date: null as Date | null, gender: null as string | null, how_knew: null as string | null, notes: '',
    allergies: '', restrictions: '', contraindications: '', medications: '', relevant_info: '', is_active: true,
});
const form = ref(blank());

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await customersApi.list(params);
        rows.value = res.data;
        total.value = res.meta.total;
    } finally {
        loading.value = false;
    }
}

let t: number | undefined;
const onSearch = (): void => { window.clearTimeout(t); t = window.setTimeout(() => { params.page = 1; load(); }, 350); };
const onPage = (e: DataTablePageEvent): void => { params.page = e.page + 1; params.per_page = e.rows; load(); };
const onSort = (e: DataTableSortEvent): void => { params.sort_by = (e.sortField as string) || 'created_at'; params.sort_dir = e.sortOrder === 1 ? 'asc' : 'desc'; load(); };

function openCreate(): void { editingId.value = null; errors.value = {}; formTab.value = 'data'; form.value = blank(); dialog.value = true; }
function openEdit(c: Customer): void {
    editingId.value = c.id;
    errors.value = {};
    formTab.value = 'data';
    form.value = {
        doc_type: c.doc_type, doc_number: c.doc_number ?? '', name: c.name, email: c.email ?? '', phone: c.phone ?? '',
        whatsapp: c.whatsapp ?? '', address: c.address ?? '', district: c.district ?? '',
        birth_date: c.birth_date ? new Date(`${c.birth_date}T00:00:00`) : null, gender: c.gender ?? null, how_knew: c.how_knew ?? null,
        notes: c.notes ?? '', allergies: c.allergies ?? '', restrictions: c.restrictions ?? '', contraindications: c.contraindications ?? '',
        medications: c.medications ?? '', relevant_info: c.relevant_info ?? '', is_active: c.is_active,
    };
    dialog.value = true;
}
async function submit(): Promise<void> {
    saving.value = true;
    errors.value = {};
    try {
        const { birth_date, ...rest } = form.value;
        await customersApi.save({ ...rest, birth_date: birth_date ? isoDate(birth_date) : null } as Partial<Customer>, editingId.value ?? undefined);
        dialog.value = false;
        toast.add({ severity: 'success', summary: 'Guardado', life: 2000 });
        load();
    } catch (e) {
        const ax = e as AxiosError<{ errors?: Record<string, string[]> }>;
        if (ax.response?.status === 422) {
            errors.value = ax.response.data.errors ?? {};
            const clinical = ['allergies', 'restrictions', 'contraindications', 'medications', 'relevant_info'];
            if (Object.keys(errors.value).every((k) => clinical.includes(k))) formTab.value = 'clinical';
            else formTab.value = 'data';
        }
    } finally {
        saving.value = false;
    }
}
function confirmDelete(c: Customer): void {
    confirm.require({
        message: `¿Eliminar el cliente «${c.name}»?`,
        header: 'Confirmar', icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar', rejectLabel: 'Cancelar', acceptClass: 'p-button-danger',
        accept: async () => { await customersApi.remove(c.id); toast.add({ severity: 'success', summary: 'Eliminado', life: 2000 }); load(); },
    });
}

// --- Ficha: paquetes, atenciones y saldos ------------------------------------
const profile = ref<Customer | null>(null);
const profileLoading = ref(false);
const profilePackages = ref<CustomerPackage[]>([]);
const profileAttendances = ref<Attendance[]>([]);
const profileSales = ref<Sale[]>([]);

async function openProfile(c: Customer): Promise<void> {
    profile.value = c;
    profileLoading.value = true;
    try {
        const [pkgs, atts, sales] = await Promise.all([
            auth.can('packages.view') ? customerPackagesApi.all({ customer_id: c.id }) : Promise.resolve([]),
            auth.can('attendances.view') ? attendancesApi.list({ customer_id: c.id, per_page: 10 }).then((r) => r.data) : Promise.resolve([]),
            auth.can('sales.view') ? salesApi.list({ customer_id: c.id, per_page: 10, sort_by: 'sold_at', sort_dir: 'desc' }).then((r) => r.data) : Promise.resolve([]),
        ]);
        profilePackages.value = pkgs;
        profileAttendances.value = atts;
        profileSales.value = sales;
    } finally {
        profileLoading.value = false;
    }
}

const alerts = (c: Customer): string[] => [c.allergies, c.contraindications].filter((v): v is string => !!v);

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Clientes</h1>
                <p class="text-sm text-slate-500">{{ total }} clientes registrados</p>
            </div>
            <Button v-if="auth.can('customers.create')" label="Nuevo cliente" icon="pi pi-plus" @click="openCreate" />
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="mb-3">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="params.search" placeholder="Buscar por nombre, código, documento, correo…" class="w-80" @input="onSearch" />
                </IconField>
            </div>
            <DataTable
                :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                :first="(params.page - 1) * params.per_page" :rows-per-page-options="[10, 25, 50]" removable-sort class="text-sm"
                @page="onPage" @sort="onSort"
            >
                <template #empty><div class="py-8 text-center text-slate-400">Sin clientes.</div></template>
                <Column header="Código"><template #body="{ data }"><span class="text-xs text-slate-500">{{ data.code ?? '—' }}</span></template></Column>
                <Column header="Nombre / Razón social" field="name" sortable>
                    <template #body="{ data }">
                        <span class="font-medium">{{ data.name }}</span>
                        <i v-if="alerts(data).length" class="pi pi-exclamation-triangle ml-2 text-amber-500" v-tooltip.top="alerts(data).join(' · ')"></i>
                    </template>
                </Column>
                <Column header="Documento">
                    <template #body="{ data }"><span class="font-medium">{{ data.doc_type }}</span> {{ data.doc_number ?? '—' }}</template>
                </Column>
                <Column header="Teléfono">
                    <template #body="{ data }">{{ data.whatsapp ?? data.phone ?? '—' }}</template>
                </Column>
                <Column header="Correo">
                    <template #body="{ data }">{{ data.email ?? '—' }}</template>
                </Column>
                <Column header="Estado">
                    <template #body="{ data }"><Tag :value="data.is_active ? 'Activo' : 'Inactivo'" :severity="data.is_active ? 'success' : 'danger'" /></template>
                </Column>
                <Column header="" header-style="width:8rem">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-1">
                            <Button icon="pi pi-id-card" text rounded size="small" v-tooltip.top="'Ficha'" @click="openProfile(data)" />
                            <Button v-if="auth.can('customers.edit')" icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                            <Button v-if="auth.can('customers.delete')" icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog v-model:visible="dialog" modal :header="editingId ? 'Editar cliente' : 'Nuevo cliente'" :style="{ width: '680px' }">
            <Tabs v-model:value="formTab">
                <TabList>
                    <Tab value="data">Datos</Tab>
                    <Tab value="clinical">Ficha del centro</Tab>
                </TabList>
                <TabPanels>
                    <TabPanel value="data">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Tipo de documento</label>
                                <Select v-model="form.doc_type" :options="DOC_TYPES" option-label="label" option-value="value" class="w-full" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Número de documento</label>
                                <InputText v-model="form.doc_number" class="w-full" :invalid="!!errors.doc_number" />
                                <Message v-if="errors.doc_number" severity="error" size="small" variant="simple">{{ errors.doc_number[0] }}</Message>
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium">Nombre / Razón social *</label>
                                <InputText v-model="form.name" class="w-full" :invalid="!!errors.name" />
                                <Message v-if="errors.name" severity="error" size="small" variant="simple">{{ errors.name[0] }}</Message>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Teléfono</label>
                                <InputText v-model="form.phone" class="w-full" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">WhatsApp</label>
                                <InputText v-model="form.whatsapp" class="w-full" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium">Correo</label>
                                <InputText v-model="form.email" class="w-full" :invalid="!!errors.email" />
                                <Message v-if="errors.email" severity="error" size="small" variant="simple">{{ errors.email[0] }}</Message>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Dirección</label>
                                <InputText v-model="form.address" class="w-full" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Distrito</label>
                                <InputText v-model="form.district" class="w-full" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Fecha de nacimiento</label>
                                <DatePicker v-model="form.birth_date" date-format="dd/mm/yy" show-icon :max-date="new Date()" class="w-full" :invalid="!!errors.birth_date" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Sexo</label>
                                <Select v-model="form.gender" :options="GENDERS" option-label="label" option-value="value" show-clear class="w-full" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium">¿Cómo nos conoció?</label>
                                <Select v-model="form.how_knew" :options="HOW_KNEW" editable show-clear class="w-full" />
                            </div>
                            <div class="flex items-center gap-2">
                                <ToggleSwitch v-model="form.is_active" input-id="cust-active" />
                                <label for="cust-active" class="text-sm font-medium">Cliente activo</label>
                            </div>
                        </div>
                    </TabPanel>
                    <TabPanel value="clinical">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Alergias</label>
                                <Textarea v-model="form.allergies" rows="2" auto-resize class="w-full" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Contraindicaciones</label>
                                <Textarea v-model="form.contraindications" rows="2" auto-resize class="w-full" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Medicación</label>
                                <Textarea v-model="form.medications" rows="2" auto-resize class="w-full" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Restricciones</label>
                                <Textarea v-model="form.restrictions" rows="2" auto-resize class="w-full" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium">Información relevante</label>
                                <Textarea v-model="form.relevant_info" rows="2" auto-resize class="w-full" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium">Observaciones</label>
                                <Textarea v-model="form.notes" rows="2" auto-resize class="w-full" />
                            </div>
                        </div>
                    </TabPanel>
                </TabPanels>
            </Tabs>
            <template #footer>
                <Button label="Cancelar" text @click="dialog = false" />
                <Button label="Guardar" icon="pi pi-check" :loading="saving" @click="submit" />
            </template>
        </Dialog>

        <!-- Ficha -->
        <Dialog :visible="!!profile" modal :header="profile ? `${profile.name}${profile.code ? ' · ' + profile.code : ''}` : ''" :style="{ width: '760px' }" @update:visible="profile = null">
            <div v-if="profile" class="space-y-5 text-sm">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div><p class="text-xs text-slate-400">Documento</p><p>{{ profile.doc_type }} {{ profile.doc_number ?? '—' }}</p></div>
                    <div><p class="text-xs text-slate-400">Contacto</p><p>{{ profile.whatsapp ?? profile.phone ?? '—' }}</p></div>
                    <div><p class="text-xs text-slate-400">Nacimiento</p><p>{{ dayLabel(profile.birth_date) }}</p></div>
                    <div><p class="text-xs text-slate-400">Nos conoció por</p><p>{{ profile.how_knew ?? '—' }}</p></div>
                </div>
                <div v-if="alerts(profile).length" class="rounded-xl bg-amber-50 p-3 dark:bg-amber-500/10">
                    <p class="font-medium text-amber-700 dark:text-amber-400"><i class="pi pi-exclamation-triangle mr-1"></i>Tener en cuenta</p>
                    <p v-if="profile.allergies">Alergias: {{ profile.allergies }}</p>
                    <p v-if="profile.contraindications">Contraindicaciones: {{ profile.contraindications }}</p>
                </div>

                <div v-if="profileLoading" class="py-6 text-center text-slate-400"><i class="pi pi-spin pi-spinner"></i></div>
                <template v-else>
                    <div v-if="auth.can('packages.view')">
                        <p class="mb-2 font-semibold">Paquetes</p>
                        <p v-if="!profilePackages.length" class="text-slate-400">Sin paquetes.</p>
                        <div v-for="p in profilePackages" :key="p.id" class="mb-1 flex items-center justify-between rounded-lg border border-[var(--surface-border)] px-3 py-2">
                            <span>{{ p.package_name }} <span class="text-xs text-slate-400">· desde {{ dayLabel(p.purchased_at) }}{{ p.expires_at ? ` · vence ${dayLabel(p.expires_at)}` : '' }}</span></span>
                            <span class="flex items-center gap-2">
                                <span class="font-semibold">{{ p.used_sessions }}/{{ p.total_sessions }}</span>
                                <Tag :value="CUSTOMER_PACKAGE_STATUS[p.status]?.label" :severity="CUSTOMER_PACKAGE_STATUS[p.status]?.severity" />
                            </span>
                        </div>
                    </div>
                    <div v-if="auth.can('attendances.view')">
                        <p class="mb-2 font-semibold">Últimas atenciones</p>
                        <p v-if="!profileAttendances.length" class="text-slate-400">Sin atenciones.</p>
                        <div v-for="a in profileAttendances" :key="a.id" class="flex justify-between border-b border-[var(--surface-border)] py-1">
                            <span>{{ dayLabel(a.attended_at) }} · {{ a.service }}<span v-if="a.session_number" class="text-xs text-slate-400"> (sesión {{ a.session_number }})</span></span>
                            <span class="text-slate-500">{{ a.employee }}</span>
                        </div>
                    </div>
                    <div v-if="auth.can('sales.view')">
                        <p class="mb-2 font-semibold">Últimas ventas</p>
                        <p v-if="!profileSales.length" class="text-slate-400">Sin ventas.</p>
                        <div v-for="s in profileSales" :key="s.id" class="flex justify-between border-b border-[var(--surface-border)] py-1">
                            <span>{{ s.full_number }} · {{ dayLabel(s.sold_at) }}</span>
                            <span>
                                {{ money(s.total) }}
                                <Tag v-if="s.status === 'completed' && (s.balance ?? 0) > 0" class="ml-1" :value="`Saldo ${money(s.balance ?? 0)}`" severity="warn" />
                                <Tag v-if="s.status === 'cancelled'" class="ml-1" value="Anulada" severity="danger" />
                            </span>
                        </div>
                    </div>
                </template>
            </div>
        </Dialog>
    </div>
</template>
