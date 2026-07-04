<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Dialog from 'primevue/dialog';
import Checkbox from 'primevue/checkbox';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import { AxiosError } from 'axios';
import { rolesApi, type Role, type PermissionGroup } from '@/services/admin';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();

const roles = ref<Role[]>([]);
const groups = ref<PermissionGroup[]>([]);
const loading = ref(true);

const dialog = ref(false);
const saving = ref(false);
const errors = ref<Record<string, string[]>>({});
const editingId = ref<number | null>(null);
const isSystem = ref(false);
const form = ref<{ name: string; permissions: string[] }>({ name: '', permissions: [] });

const SYSTEM_ROLES = ['Super Administrador', 'Administrador'];

async function load(): Promise<void> {
    loading.value = true;
    try {
        roles.value = await rolesApi.list();
        if (!groups.value.length) groups.value = await rolesApi.permissions();
    } finally {
        loading.value = false;
    }
}

function openCreate(): void {
    editingId.value = null;
    isSystem.value = false;
    errors.value = {};
    form.value = { name: '', permissions: [] };
    dialog.value = true;
}
function openEdit(r: Role): void {
    editingId.value = r.id;
    isSystem.value = SYSTEM_ROLES.includes(r.name);
    errors.value = {};
    form.value = { name: r.name, permissions: [...r.permissions] };
    dialog.value = true;
}

const allNames = computed(() => groups.value.flatMap((g) => g.permissions.map((p) => p.name)));
const allSelected = computed(() => allNames.value.length > 0 && allNames.value.every((n) => form.value.permissions.includes(n)));

function toggleAll(): void {
    form.value.permissions = allSelected.value ? [] : [...allNames.value];
}
function moduleChecked(g: PermissionGroup): boolean {
    return g.permissions.every((p) => form.value.permissions.includes(p.name));
}
function toggleModule(g: PermissionGroup): void {
    const names = g.permissions.map((p) => p.name);
    form.value.permissions = moduleChecked(g)
        ? form.value.permissions.filter((n) => !names.includes(n))
        : [...new Set([...form.value.permissions, ...names])];
}

async function submit(): Promise<void> {
    saving.value = true;
    errors.value = {};
    try {
        await rolesApi.save(form.value, editingId.value ?? undefined);
        dialog.value = false;
        toast.add({ severity: 'success', summary: 'Guardado', life: 2000 });
        load();
    } catch (e) {
        const ax = e as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;
        if (ax.response?.status === 422 && ax.response.data.errors) errors.value = ax.response.data.errors;
        else toast.add({ severity: 'warn', summary: 'No permitido', detail: ax.response?.data?.message, life: 3500 });
    } finally {
        saving.value = false;
    }
}

function confirmDelete(r: Role): void {
    confirm.require({
        message: `¿Eliminar el rol «${r.name}»?`,
        header: 'Confirmar', icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar', rejectLabel: 'Cancelar', acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await rolesApi.remove(r.id);
                toast.add({ severity: 'success', summary: 'Eliminado', life: 2000 });
                load();
            } catch (e) {
                const ax = e as AxiosError<{ message?: string }>;
                toast.add({ severity: 'warn', summary: 'No permitido', detail: ax.response?.data?.message, life: 3500 });
            }
        },
    });
}

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Roles y permisos</h1>
                <p class="text-sm text-slate-500">{{ roles.length }} roles configurados</p>
            </div>
            <Button v-if="auth.can('roles.create')" label="Nuevo rol" icon="pi pi-plus" @click="openCreate" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div v-for="r in roles" :key="r.id" class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10"><i class="pi pi-shield"></i></span>
                        <div>
                            <p class="font-semibold">{{ r.name }}</p>
                            <p class="text-xs text-slate-400">{{ r.users_count ?? 0 }} usuario(s)</p>
                        </div>
                    </div>
                    <Tag :value="`${r.permissions_count} permisos`" severity="secondary" />
                </div>
                <div class="mt-4 flex gap-1">
                    <Button v-if="auth.can('roles.edit')" icon="pi pi-pencil" label="Editar" size="small" text @click="openEdit(r)" />
                    <Button v-if="auth.can('roles.delete')" icon="pi pi-trash" size="small" text severity="danger" @click="confirmDelete(r)" />
                </div>
            </div>
        </div>

        <Dialog v-model:visible="dialog" modal :header="editingId ? 'Editar rol' : 'Nuevo rol'" :style="{ width: '760px' }" :dismissable-mask="true">
            <div class="space-y-4">
                <div class="flex items-end gap-4">
                    <div class="flex-1">
                        <label class="mb-1 block text-sm font-medium">Nombre del rol *</label>
                        <InputText v-model="form.name" class="w-full" :disabled="isSystem" :invalid="!!errors.name" />
                        <Message v-if="errors.name" severity="error" size="small" variant="simple">{{ errors.name[0] }}</Message>
                    </div>
                    <label class="flex items-center gap-2 pb-2 text-sm">
                        <Checkbox :model-value="allSelected" :binary="true" @change="toggleAll" /> Seleccionar todo
                    </label>
                </div>

                <p v-if="isSystem" class="rounded-lg bg-amber-50 p-2 text-xs text-amber-700 dark:bg-amber-500/10">
                    Rol del sistema: se pueden ajustar permisos pero no eliminar. El Super Administrador siempre tiene acceso total.
                </p>

                <div class="grid max-h-[50vh] grid-cols-1 gap-3 overflow-y-auto sm:grid-cols-2">
                    <div v-for="g in groups" :key="g.module" class="rounded-xl border border-[var(--surface-border)] p-3">
                        <label class="mb-2 flex items-center gap-2 border-b border-[var(--surface-border)] pb-2 text-sm font-semibold capitalize">
                            <Checkbox :model-value="moduleChecked(g)" :binary="true" @change="toggleModule(g)" /> {{ g.module }}
                        </label>
                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                            <label v-for="p in g.permissions" :key="p.name" class="flex items-center gap-1.5 text-sm">
                                <Checkbox v-model="form.permissions" :value="p.name" /> {{ p.action }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <span class="mr-auto text-sm text-slate-400">{{ form.permissions.length }} permisos seleccionados</span>
                <Button label="Cancelar" text @click="dialog = false" />
                <Button label="Guardar" icon="pi pi-check" :loading="saving" @click="submit" />
            </template>
        </Dialog>
    </div>
</template>
