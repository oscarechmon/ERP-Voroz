<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import DataTable, { type DataTablePageEvent, type DataTableSortEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
import ToggleSwitch from 'primevue/toggleswitch';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Message from 'primevue/message';
import { AxiosError } from 'axios';
import { employeesApi, type Employee, type EmployeePayload } from '@/services/staff';
import { productsApi, type Product } from '@/services/catalog';
import { usersApi } from '@/services/admin';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();

const rows = ref<Employee[]>([]);
const total = ref(0);
const loading = ref(true);
const params = reactive({ page: 1, per_page: 15, search: '', sort_by: 'name', sort_dir: 'asc' as 'asc' | 'desc' });

const services = ref<Product[]>([]);
const users = ref<{ id: number; name: string; email: string }[]>([]);

const dialog = ref(false);
const saving = ref(false);
const errors = ref<Record<string, string[]>>({});
const editingId = ref<number | null>(null);
const blank = (): EmployeePayload => ({ name: '', position: '', phone: '', doc_number: '', user_id: null, is_active: true, service_ids: [] });
const form = ref<EmployeePayload>(blank());

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await employeesApi.list(params);
        rows.value = res.data;
        total.value = res.meta.total;
    } finally {
        loading.value = false;
    }
}

let t: number | undefined;
const onSearch = (): void => { window.clearTimeout(t); t = window.setTimeout(() => { params.page = 1; load(); }, 350); };
const onPage = (e: DataTablePageEvent): void => { params.page = e.page + 1; params.per_page = e.rows; load(); };
const onSort = (e: DataTableSortEvent): void => { params.sort_by = (e.sortField as string) || 'name'; params.sort_dir = e.sortOrder === -1 ? 'desc' : 'asc'; load(); };

async function loadOptions(): Promise<void> {
    if (!services.value.length) {
        services.value = (await productsApi.list({ type: 'service', is_active: 1, per_page: 100, sort_by: 'name', sort_dir: 'asc' })).data;
    }
    if (!users.value.length && auth.can('users.view')) {
        users.value = (await usersApi.list({ per_page: 100 })).data;
    }
}

async function openCreate(): Promise<void> {
    await loadOptions();
    editingId.value = null;
    errors.value = {};
    form.value = blank();
    dialog.value = true;
}

async function openEdit(e: Employee): Promise<void> {
    await loadOptions();
    editingId.value = e.id;
    errors.value = {};
    form.value = {
        name: e.name, position: e.position ?? '', phone: e.phone ?? '', doc_number: e.doc_number ?? '',
        user_id: e.user?.id ?? null, is_active: e.is_active, service_ids: e.services.map((s) => s.id),
    };
    dialog.value = true;
}

async function submit(): Promise<void> {
    saving.value = true;
    errors.value = {};
    try {
        await employeesApi.save(form.value, editingId.value ?? undefined);
        dialog.value = false;
        toast.add({ severity: 'success', summary: 'Guardado', life: 2000 });
        load();
    } catch (e) {
        const ax = e as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;
        if (ax.response?.status === 422) errors.value = ax.response.data.errors ?? {};
        else toast.add({ severity: 'error', summary: 'No se pudo guardar', detail: ax.response?.data?.message, life: 4000 });
    } finally {
        saving.value = false;
    }
}

function confirmDelete(e: Employee): void {
    confirm.require({
        message: `¿Eliminar a «${e.name}»? Sus citas, atenciones y comisiones se conservan.`,
        header: 'Confirmar', icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar', rejectLabel: 'Cancelar', acceptClass: 'p-button-danger',
        accept: async () => { await employeesApi.remove(e.id); toast.add({ severity: 'success', summary: 'Eliminado', life: 2000 }); load(); },
    });
}

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Personal</h1>
                <p class="text-sm text-slate-500">Especialistas y recepción, con los servicios que atiende cada uno</p>
            </div>
            <Button v-if="auth.can('employees.create')" label="Nuevo empleado" icon="pi pi-plus" @click="openCreate" />
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="mb-3">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="params.search" placeholder="Buscar por nombre, cargo, teléfono…" class="w-80" @input="onSearch" />
                </IconField>
            </div>
            <DataTable
                :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                :first="(params.page - 1) * params.per_page" :rows-per-page-options="[15, 30, 50]" class="text-sm"
                @page="onPage" @sort="onSort"
            >
                <template #empty><div class="py-8 text-center text-slate-400">Sin personal registrado.</div></template>
                <Column header="Nombre" field="name" sortable>
                    <template #body="{ data }">
                        <p class="font-medium">{{ data.name }}</p>
                        <p class="text-xs text-slate-400">{{ data.position ?? '—' }}</p>
                    </template>
                </Column>
                <Column header="Servicios que atiende">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-1">
                            <Tag v-for="s in data.services" :key="s.id" :value="s.name" severity="secondary" />
                            <span v-if="!data.services.length" class="text-xs text-slate-400">Todos</span>
                        </div>
                    </template>
                </Column>
                <Column header="Teléfono"><template #body="{ data }">{{ data.phone ?? '—' }}</template></Column>
                <Column header="Usuario del sistema">
                    <template #body="{ data }"><span class="text-xs">{{ data.user?.email ?? '—' }}</span></template>
                </Column>
                <Column header="Estado">
                    <template #body="{ data }"><Tag :value="data.is_active ? 'Activo' : 'Inactivo'" :severity="data.is_active ? 'success' : 'danger'" /></template>
                </Column>
                <Column header="" header-style="width:6rem">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-1">
                            <Button v-if="auth.can('employees.edit')" icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                            <Button v-if="auth.can('employees.delete')" icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog v-model:visible="dialog" modal :header="editingId ? 'Editar empleado' : 'Nuevo empleado'" :style="{ width: '600px' }">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Nombre *</label>
                    <InputText v-model="form.name" class="w-full" :invalid="!!errors.name" />
                    <Message v-if="errors.name" severity="error" size="small" variant="simple">{{ errors.name[0] }}</Message>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Cargo</label>
                    <InputText v-model="form.position" class="w-full" placeholder="Cosmiatra, recepción…" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Teléfono</label>
                    <InputText v-model="form.phone" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Documento</label>
                    <InputText v-model="form.doc_number" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Usuario del sistema</label>
                    <Select v-model="form.user_id" :options="users" option-label="name" option-value="id" class="w-full" show-clear filter placeholder="Sin acceso" :invalid="!!errors.user_id" />
                    <Message v-if="errors.user_id" severity="error" size="small" variant="simple">{{ errors.user_id[0] }}</Message>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Servicios que atiende</label>
                    <MultiSelect v-model="form.service_ids" :options="services" option-label="name" option-value="id" class="w-full" filter display="chip" placeholder="Vacío = atiende todos" />
                </div>
                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="form.is_active" input-id="emp-active" />
                    <label for="emp-active" class="text-sm font-medium">Activo</label>
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="dialog = false" />
                <Button label="Guardar" icon="pi pi-check" :loading="saving" @click="submit" />
            </template>
        </Dialog>
    </div>
</template>
