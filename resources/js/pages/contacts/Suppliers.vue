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
import ToggleSwitch from 'primevue/toggleswitch';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Message from 'primevue/message';
import { AxiosError } from 'axios';
import { suppliersApi, DOC_TYPES, type Supplier } from '@/services/contacts';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();

const rows = ref<Supplier[]>([]);
const total = ref(0);
const loading = ref(true);
const params = reactive({ page: 1, per_page: 10, search: '', sort_by: 'created_at', sort_dir: 'desc' as 'asc' | 'desc' });

const dialog = ref(false);
const saving = ref(false);
const errors = ref<Record<string, string[]>>({});
const editingId = ref<number | null>(null);
const blank = () => ({ doc_type: 'RUC', doc_number: '', name: '', contact_name: '', email: '', phone: '', address: '', notes: '', is_active: true });
const form = ref(blank());

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await suppliersApi.list(params);
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

function openCreate(): void { editingId.value = null; errors.value = {}; form.value = blank(); dialog.value = true; }
function openEdit(s: Supplier): void {
    editingId.value = s.id;
    errors.value = {};
    form.value = { doc_type: s.doc_type, doc_number: s.doc_number ?? '', name: s.name, contact_name: s.contact_name ?? '', email: s.email ?? '', phone: s.phone ?? '', address: s.address ?? '', notes: s.notes ?? '', is_active: s.is_active };
    dialog.value = true;
}
async function submit(): Promise<void> {
    saving.value = true;
    errors.value = {};
    try {
        await suppliersApi.save(form.value, editingId.value ?? undefined);
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
function confirmDelete(s: Supplier): void {
    confirm.require({
        message: `¿Eliminar el proveedor «${s.name}»?`,
        header: 'Confirmar', icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar', rejectLabel: 'Cancelar', acceptClass: 'p-button-danger',
        accept: async () => { await suppliersApi.remove(s.id); toast.add({ severity: 'success', summary: 'Eliminado', life: 2000 }); load(); },
    });
}

onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Proveedores</h1>
                <p class="text-sm text-slate-500">{{ total }} proveedores</p>
            </div>
            <Button v-if="auth.can('suppliers.create')" label="Nuevo proveedor" icon="pi pi-plus" @click="openCreate" />
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="mb-3">
                <IconField>
                    <InputIcon class="pi pi-search" />
                    <InputText v-model="params.search" placeholder="Buscar…" class="w-80" @input="onSearch" />
                </IconField>
            </div>
            <DataTable
                :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                :first="(params.page - 1) * params.per_page" :rows-per-page-options="[10, 25, 50]" removable-sort class="text-sm"
                @page="onPage" @sort="onSort"
            >
                <template #empty><div class="py-8 text-center text-slate-400">Sin proveedores.</div></template>
                <Column header="RUC / Doc.">
                    <template #body="{ data }"><span class="font-medium">{{ data.doc_type }}</span> {{ data.doc_number ?? '—' }}</template>
                </Column>
                <Column header="Razón social" field="name" sortable />
                <Column header="Contacto">
                    <template #body="{ data }">{{ data.contact_name ?? '—' }}</template>
                </Column>
                <Column header="Teléfono">
                    <template #body="{ data }">{{ data.phone ?? '—' }}</template>
                </Column>
                <Column header="Estado">
                    <template #body="{ data }"><Tag :value="data.is_active ? 'Activo' : 'Inactivo'" :severity="data.is_active ? 'success' : 'danger'" /></template>
                </Column>
                <Column header="" header-style="width:6rem">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-1">
                            <Button v-if="auth.can('suppliers.edit')" icon="pi pi-pencil" text rounded size="small" @click="openEdit(data)" />
                            <Button v-if="auth.can('suppliers.delete')" icon="pi pi-trash" text rounded size="small" severity="danger" @click="confirmDelete(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog v-model:visible="dialog" modal :header="editingId ? 'Editar proveedor' : 'Nuevo proveedor'" :style="{ width: '600px' }">
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
                    <label class="mb-1 block text-sm font-medium">Razón social *</label>
                    <InputText v-model="form.name" class="w-full" :invalid="!!errors.name" />
                    <Message v-if="errors.name" severity="error" size="small" variant="simple">{{ errors.name[0] }}</Message>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Persona de contacto</label>
                    <InputText v-model="form.contact_name" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Teléfono</label>
                    <InputText v-model="form.phone" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Correo</label>
                    <InputText v-model="form.email" class="w-full" :invalid="!!errors.email" />
                    <Message v-if="errors.email" severity="error" size="small" variant="simple">{{ errors.email[0] }}</Message>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Dirección</label>
                    <InputText v-model="form.address" class="w-full" />
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">Observaciones</label>
                    <Textarea v-model="form.notes" class="w-full" rows="2" auto-resize />
                </div>
                <div class="flex items-center gap-2">
                    <ToggleSwitch v-model="form.is_active" input-id="sup-active" />
                    <label for="sup-active" class="text-sm font-medium">Proveedor activo</label>
                </div>
            </div>
            <template #footer>
                <Button label="Cancelar" text @click="dialog = false" />
                <Button label="Guardar" icon="pi pi-check" :loading="saving" @click="submit" />
            </template>
        </Dialog>
    </div>
</template>
