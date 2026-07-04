<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import DataTable, { type DataTablePageEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import ToggleSwitch from 'primevue/toggleswitch';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Message from 'primevue/message';
import { AxiosError } from 'axios';
import { categoriesApi, type Category, type Option } from '@/services/catalog';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();

const rows = ref<Category[]>([]);
const total = ref(0);
const loading = ref(true);
const parents = ref<Option[]>([]);
const params = reactive({ page: 1, per_page: 10, search: '' });

const dialog = ref(false);
const saving = ref(false);
const errors = ref<Record<string, string[]>>({});
const editingId = ref<number | null>(null);
const form = ref({ name: '', parent_id: null as number | null, description: '', is_active: true });

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await categoriesApi.list(params);
        rows.value = res.data;
        total.value = res.meta.total;
    } finally {
        loading.value = false;
    }
}

let t: number | undefined;
const onSearch = (): void => {
    window.clearTimeout(t);
    t = window.setTimeout(() => { params.page = 1; load(); }, 350);
};
const onPage = (e: DataTablePageEvent): void => { params.page = e.page + 1; params.per_page = e.rows; load(); };

async function openCreate(): Promise<void> {
    editingId.value = null;
    errors.value = {};
    form.value = { name: '', parent_id: null, description: '', is_active: true };
    parents.value = await categoriesApi.options();
    dialog.value = true;
}
async function openEdit(c: Category): Promise<void> {
    editingId.value = c.id;
    errors.value = {};
    form.value = { name: c.name, parent_id: c.parent_id, description: c.description ?? '', is_active: c.is_active };
    parents.value = (await categoriesApi.options()).filter((o) => o.id !== c.id);
    dialog.value = true;
}

async function submit(): Promise<void> {
    saving.value = true;
    errors.value = {};
    try {
        await categoriesApi.save(form.value, editingId.value ?? undefined);
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

function confirmDelete(c: Category): void {
    confirm.require({
        message: `¿Eliminar la categoría «${c.name}»?`,
        header: 'Confirmar',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar',
        rejectLabel: 'Cancelar',
        acceptClass: 'p-button-danger',
        accept: async () => {
            await categoriesApi.remove(c.id);
            toast.add({ severity: 'success', summary: 'Eliminada', life: 2000 });
            load();
        },
    });
}

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Categorías</h1>
                <p class="text-sm text-slate-500">{{ total }} categorías</p>
            </div>
            <Button v-if="auth.can('categories.create')" label="Nueva categoría" icon="pi pi-plus" @click="openCreate" />
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="mb-3">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="params.search" placeholder="Buscar…" class="w-72" @input="onSearch" />
                </IconField>
            </div>
            <DataTable
                :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                :first="(params.page - 1) * params.per_page" :rows-per-page-options="[10, 25, 50]" class="text-sm" @page="onPage"
            >
                <template #empty><div class="py-8 text-center text-slate-400">Sin categorías.</div></template>
                <Column header="Nombre" field="name" />
                <Column header="Padre">
                    <template #body="{ data }">{{ data.parent?.name ?? '—' }}</template>
                </Column>
                <Column header="Estado">
                    <template #body="{ data }"><Tag :value="data.is_active ? 'Activa' : 'Inactiva'" :severity="data.is_active ? 'success' : 'danger'" /></template>
                </Column>
                <Column header="" header-style="width:6rem">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-1">
                            <Button v-if="auth.can('categories.edit')" icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                            <Button v-if="auth.can('categories.delete')" icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog v-model:visible="dialog" modal :header="editingId ? 'Editar categoría' : 'Nueva categoría'" :style="{ width: '460px' }">
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">Nombre *</label>
                    <InputText v-model="form.name" class="w-full" :invalid="!!errors.name" />
                    <Message v-if="errors.name" severity="error" size="small" variant="simple">{{ errors.name[0] }}</Message>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Categoría padre</label>
                    <Select v-model="form.parent_id" :options="parents" option-label="name" option-value="id" class="w-full" filter show-clear placeholder="Ninguna (categoría raíz)" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Descripción</label>
                    <Textarea v-model="form.description" class="w-full" rows="2" auto-resize />
                </div>
                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="form.is_active" input-id="cat-active" />
                    <label for="cat-active" class="text-sm font-medium">Activa</label>
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="dialog = false" />
                <Button label="Guardar" icon="pi pi-check" :loading="saving" @click="submit" />
            </template>
        </Dialog>
    </div>
</template>
