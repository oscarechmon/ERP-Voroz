<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import { AxiosError } from 'axios';
import { branchesApi, warehousesApi, type Branch, type Warehouse } from '@/services/settings';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();
const rows = ref<Warehouse[]>([]);
const branches = ref<Branch[]>([]);
const loading = ref(true);

const dialog = ref(false);
const saving = ref(false);
const errors = ref<Record<string, string[]>>({});
const editingId = ref<number | null>(null);
const blank = () => ({ branch_id: null as number | null, name: '', code: '', is_default: false, is_active: true });
const form = ref(blank());

async function load(): Promise<void> {
    loading.value = true;
    try { rows.value = await warehousesApi.list(); } finally { loading.value = false; }
}
async function openCreate(): Promise<void> {
    editingId.value = null; errors.value = {}; form.value = blank();
    if (!branches.value.length) branches.value = await branchesApi.list();
    dialog.value = true;
}
async function openEdit(w: Warehouse): Promise<void> {
    editingId.value = w.id; errors.value = {};
    form.value = { branch_id: w.branch_id, name: w.name, code: w.code ?? '', is_default: w.is_default, is_active: w.is_active };
    if (!branches.value.length) branches.value = await branchesApi.list();
    dialog.value = true;
}
async function submit(): Promise<void> {
    saving.value = true; errors.value = {};
    try {
        await warehousesApi.save(form.value, editingId.value ?? undefined);
        dialog.value = false; toast.add({ severity: 'success', summary: 'Guardado', life: 2000 }); load();
    } catch (e) {
        const ax = e as AxiosError<{ errors?: Record<string, string[]> }>;
        if (ax.response?.status === 422) errors.value = ax.response.data.errors ?? {};
    } finally { saving.value = false; }
}
function confirmDelete(w: Warehouse): void {
    confirm.require({
        message: `¿Eliminar el almacén «${w.name}»?`, header: 'Confirmar', icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar', rejectLabel: 'Cancelar', acceptClass: 'p-button-danger',
        accept: async () => { await warehousesApi.remove(w.id); toast.add({ severity: 'success', summary: 'Eliminado', life: 2000 }); load(); },
    });
}
onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Almacenes</h1>
                <p class="text-sm text-slate-500">{{ rows.length }} almacenes</p>
            </div>
            <Button v-if="auth.can('settings.edit')" label="Nuevo almacén" icon="pi pi-plus" @click="openCreate" />
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <DataTable :value="rows" :loading="loading" class="text-sm">
                <template #empty><div class="py-8 text-center text-slate-400">Sin almacenes.</div></template>
                <Column header="Nombre" field="name">
                    <template #body="{ data }">
                        {{ data.name }} <Tag v-if="data.is_default" value="Por defecto" severity="info" class="ml-1" />
                    </template>
                </Column>
                <Column header="Código" field="code" />
                <Column header="Sucursal" field="branch" />
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

        <Dialog v-model:visible="dialog" modal :header="editingId ? 'Editar almacén' : 'Nuevo almacén'" :style="{ width: '460px' }">
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">Sucursal *</label>
                    <Select v-model="form.branch_id" :options="branches" option-label="name" option-value="id" class="w-full" :invalid="!!errors.branch_id" placeholder="Seleccionar" />
                    <Message v-if="errors.branch_id" severity="error" size="small" variant="simple">{{ errors.branch_id[0] }}</Message>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Nombre *</label>
                    <InputText v-model="form.name" class="w-full" :invalid="!!errors.name" />
                    <Message v-if="errors.name" severity="error" size="small" variant="simple">{{ errors.name[0] }}</Message>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Código</label>
                    <InputText v-model="form.code" class="w-full" />
                </div>
                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="form.is_default" input-id="w-default" />
                    <label for="w-default" class="text-sm font-medium">Almacén por defecto</label>
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="dialog = false" />
                <Button label="Guardar" icon="pi pi-check" :loading="saving" @click="submit" />
            </template>
        </Dialog>
    </div>
</template>
