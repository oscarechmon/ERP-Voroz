<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import ToggleSwitch from 'primevue/toggleswitch';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import { AxiosError } from 'axios';
import { branchesApi, type Branch } from '@/services/settings';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();
const rows = ref<Branch[]>([]);
const loading = ref(true);

const dialog = ref(false);
const saving = ref(false);
const errors = ref<Record<string, string[]>>({});
const editingId = ref<number | null>(null);
const blank = () => ({ name: '', code: '', address: '', phone: '', is_main: false, is_active: true });
const form = ref(blank());

async function load(): Promise<void> {
    loading.value = true;
    try { rows.value = await branchesApi.list(); } finally { loading.value = false; }
}
function openCreate(): void { editingId.value = null; errors.value = {}; form.value = blank(); dialog.value = true; }
function openEdit(b: Branch): void {
    editingId.value = b.id; errors.value = {};
    form.value = { name: b.name, code: b.code ?? '', address: b.address ?? '', phone: b.phone ?? '', is_main: b.is_main, is_active: b.is_active };
    dialog.value = true;
}
async function submit(): Promise<void> {
    saving.value = true; errors.value = {};
    try {
        await branchesApi.save(form.value, editingId.value ?? undefined);
        dialog.value = false; toast.add({ severity: 'success', summary: 'Guardado', life: 2000 }); load();
    } catch (e) {
        const ax = e as AxiosError<{ errors?: Record<string, string[]> }>;
        if (ax.response?.status === 422) errors.value = ax.response.data.errors ?? {};
    } finally { saving.value = false; }
}
function confirmDelete(b: Branch): void {
    confirm.require({
        message: `¿Eliminar la sucursal «${b.name}»?`, header: 'Confirmar', icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar', rejectLabel: 'Cancelar', acceptClass: 'p-button-danger',
        accept: async () => { await branchesApi.remove(b.id); toast.add({ severity: 'success', summary: 'Eliminada', life: 2000 }); load(); },
    });
}
onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Sucursales</h1>
                <p class="text-sm text-slate-500">{{ rows.length }} sucursales</p>
            </div>
            <Button v-if="auth.can('settings.edit')" label="Nueva sucursal" icon="pi pi-plus" @click="openCreate" />
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <DataTable :value="rows" :loading="loading" class="text-sm">
                <template #empty><div class="py-8 text-center text-slate-400">Sin sucursales.</div></template>
                <Column header="Nombre" field="name">
                    <template #body="{ data }">
                        {{ data.name }} <Tag v-if="data.is_main" value="Principal" severity="info" class="ml-1" />
                    </template>
                </Column>
                <Column header="Código" field="code" />
                <Column header="Dirección" field="address" />
                <Column header="Almacenes">
                    <template #body="{ data }">{{ data.warehouses_count ?? 0 }}</template>
                </Column>
                <Column header="Estado">
                    <template #body="{ data }"><Tag :value="data.is_active ? 'Activa' : 'Inactiva'" :severity="data.is_active ? 'success' : 'danger'" /></template>
                </Column>
                <Column header="" header-style="width:6rem">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-1">
                            <Button v-if="auth.can('settings.edit')" icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                            <Button v-if="auth.can('settings.edit')" icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog v-model:visible="dialog" modal :header="editingId ? 'Editar sucursal' : 'Nueva sucursal'" :style="{ width: '480px' }">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Nombre *</label>
                    <InputText v-model="form.name" class="w-full" :invalid="!!errors.name" />
                    <Message v-if="errors.name" severity="error" size="small" variant="simple">{{ errors.name[0] }}</Message>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Código</label>
                    <InputText v-model="form.code" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Teléfono</label>
                    <InputText v-model="form.phone" class="w-full" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Dirección</label>
                    <InputText v-model="form.address" class="w-full" />
                </div>
                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="form.is_main" input-id="b-main" />
                    <label for="b-main" class="text-sm font-medium">Sucursal principal</label>
                </div>
                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="form.is_active" input-id="b-active" />
                    <label for="b-active" class="text-sm font-medium">Activa</label>
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="dialog = false" />
                <Button label="Guardar" icon="pi pi-check" :loading="saving" @click="submit" />
            </template>
        </Dialog>
    </div>
</template>
