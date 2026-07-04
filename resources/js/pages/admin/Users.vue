<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import DataTable, { type DataTablePageEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Message from 'primevue/message';
import { AxiosError } from 'axios';
import { usersApi, rolesApi, type ManagedUser, type RoleOption } from '@/services/admin';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();

const rows = ref<ManagedUser[]>([]);
const total = ref(0);
const loading = ref(true);
const roles = ref<RoleOption[]>([]);
const params = reactive({ page: 1, per_page: 15, search: '' });

const dialog = ref(false);
const saving = ref(false);
const errors = ref<Record<string, string[]>>({});
const editingId = ref<number | null>(null);
const blank = () => ({ name: '', email: '', phone: '', password: '', password_confirmation: '', role: '', is_active: true });
const form = ref(blank());

const dt = (s: string | null): string => (s ? new Date(s).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' }) : 'Nunca');

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await usersApi.list(params);
        rows.value = res.data;
        total.value = res.meta.total;
    } finally {
        loading.value = false;
    }
}

let t: number | undefined;
const onSearch = (): void => { window.clearTimeout(t); t = window.setTimeout(() => { params.page = 1; load(); }, 350); };
const onPage = (e: DataTablePageEvent): void => { params.page = e.page + 1; params.per_page = e.rows; load(); };

async function openCreate(): Promise<void> {
    editingId.value = null;
    errors.value = {};
    form.value = blank();
    if (!roles.value.length) roles.value = await rolesApi.options();
    dialog.value = true;
}
async function openEdit(u: ManagedUser): Promise<void> {
    editingId.value = u.id;
    errors.value = {};
    form.value = { name: u.name, email: u.email, phone: u.phone ?? '', password: '', password_confirmation: '', role: u.roles[0] ?? '', is_active: u.is_active };
    if (!roles.value.length) roles.value = await rolesApi.options();
    dialog.value = true;
}

async function submit(): Promise<void> {
    saving.value = true;
    errors.value = {};
    try {
        const payload: Record<string, unknown> = { ...form.value };
        if (!payload.password) { delete payload.password; delete payload.password_confirmation; }
        await usersApi.save(payload, editingId.value ?? undefined);
        dialog.value = false;
        toast.add({ severity: 'success', summary: 'Guardado', life: 2000 });
        load();
    } catch (e) {
        const ax = e as AxiosError<{ errors?: Record<string, string[]> }>;
        if (ax.response?.status === 422) errors.value = ax.response.data.errors ?? {};
    } finally {
        saving.value = false;
    }
}

async function toggle(u: ManagedUser): Promise<void> {
    try {
        await usersApi.toggle(u.id);
        load();
    } catch (e) {
        const ax = e as AxiosError<{ message?: string }>;
        toast.add({ severity: 'warn', summary: 'No permitido', detail: ax.response?.data?.message, life: 3500 });
    }
}

function confirmDelete(u: ManagedUser): void {
    confirm.require({
        message: `¿Eliminar al usuario «${u.name}»?`,
        header: 'Confirmar', icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar', rejectLabel: 'Cancelar', acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await usersApi.remove(u.id);
                toast.add({ severity: 'success', summary: 'Eliminado', life: 2000 });
                load();
            } catch (e) {
                const ax = e as AxiosError<{ message?: string }>;
                toast.add({ severity: 'warn', summary: 'No permitido', detail: ax.response?.data?.message, life: 3500 });
            }
        },
    });
}

const err = (f: string): string | undefined => errors.value[f]?.[0];
const initials = (name: string): string => name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase();

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Usuarios</h1>
                <p class="text-sm text-slate-500">{{ total }} usuarios del sistema</p>
            </div>
            <Button v-if="auth.can('users.create')" label="Nuevo usuario" icon="pi pi-plus" @click="openCreate" />
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="mb-3">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="params.search" placeholder="Buscar por nombre o correo…" class="w-72" @input="onSearch" />
                </IconField>
            </div>
            <DataTable
                :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                :first="(params.page - 1) * params.per_page" :rows-per-page-options="[15, 30, 50]" class="text-sm" @page="onPage"
            >
                <template #empty><div class="py-8 text-center text-slate-400">Sin usuarios.</div></template>
                <Column header="Usuario">
                    <template #body="{ data }">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-500 text-xs font-semibold text-white">{{ initials(data.name) }}</span>
                            <div>
                                <p class="font-medium">{{ data.name }}</p>
                                <p class="text-xs text-slate-400">{{ data.email }}</p>
                            </div>
                        </div>
                    </template>
                </Column>
                <Column header="Rol">
                    <template #body="{ data }"><Tag v-for="r in data.roles" :key="r" :value="r" class="mr-1" /></template>
                </Column>
                <Column header="Último acceso">
                    <template #body="{ data }"><span class="text-xs">{{ dt(data.last_login_at) }}</span></template>
                </Column>
                <Column header="Activo">
                    <template #body="{ data }">
                        <ToggleSwitch :model-value="data.is_active" :disabled="!auth.can('users.edit')" @update:model-value="toggle(data)" />
                    </template>
                </Column>
                <Column header="" header-style="width:6rem">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-1">
                            <Button v-if="auth.can('users.edit')" icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                            <Button v-if="auth.can('users.delete')" icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog v-model:visible="dialog" modal :header="editingId ? 'Editar usuario' : 'Nuevo usuario'" :style="{ width: '520px' }">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Nombre *</label>
                    <InputText v-model="form.name" class="w-full" :invalid="!!err('name')" />
                    <Message v-if="err('name')" severity="error" size="small" variant="simple">{{ err('name') }}</Message>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Correo *</label>
                    <InputText v-model="form.email" class="w-full" :invalid="!!err('email')" />
                    <Message v-if="err('email')" severity="error" size="small" variant="simple">{{ err('email') }}</Message>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Teléfono</label>
                    <InputText v-model="form.phone" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Rol *</label>
                    <Select v-model="form.role" :options="roles" option-label="name" option-value="name" class="w-full" :invalid="!!err('role')" placeholder="Seleccionar" />
                    <Message v-if="err('role')" severity="error" size="small" variant="simple">{{ err('role') }}</Message>
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <ToggleSwitch v-model="form.is_active" input-id="u-active" />
                    <label for="u-active" class="text-sm font-medium">Usuario activo</label>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Contraseña {{ editingId ? '(dejar vacío para no cambiar)' : '*' }}</label>
                    <Password v-model="form.password" class="w-full" input-class="w-full" :feedback="false" toggle-mask :invalid="!!err('password')" />
                    <Message v-if="err('password')" severity="error" size="small" variant="simple">{{ err('password') }}</Message>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Confirmar contraseña</label>
                    <Password v-model="form.password_confirmation" class="w-full" input-class="w-full" :feedback="false" toggle-mask />
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="dialog = false" />
                <Button label="Guardar" icon="pi pi-check" :loading="saving" @click="submit" />
            </template>
        </Dialog>
    </div>
</template>
