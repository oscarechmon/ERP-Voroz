<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import DataTable, { type DataTablePageEvent } from 'primevue/datatable';
import Column from 'primevue/column';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { auditApi, type AuditEntry, type AuditFilters } from '@/services/audit';

const rows = ref<AuditEntry[]>([]);
const total = ref(0);
const loading = ref(true);
const expanded = ref<Record<number, boolean>>({});
const filters = ref<AuditFilters>({ events: [], models: [] });
const params = reactive({ page: 1, per_page: 20, event: null as string | null, model: null as string | null, from: '', to: '' });

const eventSeverity: Record<string, string> = { created: 'success', updated: 'info', deleted: 'danger', restored: 'warn' };
const dt = (s: string | null): string => (s ? new Date(s).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'medium' }) : '');

function toDateStr(v: string | Date): string | undefined {
    if (!v) return undefined;
    const d = typeof v === 'string' ? new Date(v) : v;
    return d.toISOString().slice(0, 10);
}

async function load(): Promise<void> {
    loading.value = true;
    try {
        const res = await auditApi.list({ ...params, from: toDateStr(params.from), to: toDateStr(params.to) });
        rows.value = res.data;
        total.value = res.meta.total;
    } finally {
        loading.value = false;
    }
}

const onPage = (e: DataTablePageEvent): void => { params.page = e.page + 1; params.per_page = e.rows; load(); };
const reload = (): void => { params.page = 1; load(); };
const changed = (r: AuditEntry): string[] => Object.keys({ ...r.old_values, ...r.new_values });

onMounted(async () => {
    filters.value = await auditApi.filters();
    load();
});
</script>

<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Auditoría</h1>
            <p class="text-sm text-slate-500">Registro de todas las acciones: quién, qué, cuándo y desde dónde.</p>
        </div>

        <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <Select v-model="params.event" :options="filters.events" option-label="label" option-value="value" class="w-44" show-clear placeholder="Todos los eventos" @change="reload" />
                <Select v-model="params.model" :options="filters.models" option-label="label" option-value="value" class="w-44" show-clear placeholder="Todos los modelos" @change="reload" />
                <DatePicker v-model="params.from" date-format="yy-mm-dd" placeholder="Desde" show-icon class="w-40" @update:model-value="reload" />
                <DatePicker v-model="params.to" date-format="yy-mm-dd" placeholder="Hasta" show-icon class="w-40" @update:model-value="reload" />
                <Button icon="pi pi-refresh" text rounded @click="reload" />
            </div>

            <DataTable
                :value="rows" :loading="loading" lazy paginator :rows="params.per_page" :total-records="total"
                :first="(params.page - 1) * params.per_page" :rows-per-page-options="[20, 50, 100]" class="text-sm" @page="onPage"
            >
                <template #empty><div class="py-8 text-center text-slate-400">Sin registros de auditoría.</div></template>
                <Column header="Fecha">
                    <template #body="{ data }"><span class="text-xs">{{ dt(data.created_at) }}</span></template>
                </Column>
                <Column header="Usuario">
                    <template #body="{ data }">{{ data.user }}</template>
                </Column>
                <Column header="Acción">
                    <template #body="{ data }">
                        <Tag :value="data.event_label" :severity="eventSeverity[data.event] ?? 'secondary'" />
                        <span class="ml-2 text-slate-500">{{ data.model }} #{{ data.auditable_id }}</span>
                    </template>
                </Column>
                <Column header="IP">
                    <template #body="{ data }"><span class="text-xs text-slate-400">{{ data.ip_address ?? '—' }}</span></template>
                </Column>
                <Column header="" header-style="width:4rem">
                    <template #body="{ data }">
                        <Button
                            v-if="changed(data).length" icon="pi pi-eye" text rounded size="small"
                            @click="expanded[data.id] = !expanded[data.id]" v-tooltip.left="'Ver cambios'"
                        />
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Panel de detalle de cambios (expandible por fila) -->
        <div v-for="r in rows.filter((x) => expanded[x.id])" :key="r.id" class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-4 shadow-sm">
            <div class="mb-2 flex items-center justify-between">
                <p class="text-sm font-semibold">{{ r.user }} · {{ r.event_label }} {{ r.model }} #{{ r.auditable_id }} · {{ dt(r.created_at) }}</p>
                <Button icon="pi pi-times" text rounded size="small" @click="expanded[r.id] = false" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-slate-400">
                            <th class="py-1 pr-4">Campo</th><th class="py-1 pr-4">Antes</th><th class="py-1">Después</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="k in changed(r)" :key="k" class="border-t border-[var(--surface-border)]">
                            <td class="py-1.5 pr-4 font-medium">{{ k }}</td>
                            <td class="py-1.5 pr-4 text-rose-600">{{ r.old_values[k] ?? '—' }}</td>
                            <td class="py-1.5 text-emerald-600">{{ r.new_values[k] ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
