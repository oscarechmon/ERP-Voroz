<script setup lang="ts">
import { ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import ProgressSpinner from 'primevue/progressspinner';
import { useToast } from 'primevue/usetoast';
import { productsApi, type ProductLabel } from '@/services/catalog';

const props = defineProps<{ visible: boolean; ids: number[] }>();
const emit = defineEmits<{ 'update:visible': [boolean] }>();

const toast = useToast();
const labels = ref<ProductLabel[]>([]);
const loading = ref(false);
const copies = ref(1);

const money = (n: number): string => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n);

async function loadLabels(): Promise<void> {
    if (!props.ids.length) return;
    loading.value = true;
    labels.value = [];
    try {
        labels.value = await Promise.all(props.ids.map((id) => productsApi.label(id)));
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron generar las etiquetas', life: 3000 });
        close();
    } finally {
        loading.value = false;
    }
}

// Carga las etiquetas cada vez que se abre el diálogo.
watch(
    () => props.visible,
    (open) => {
        if (open) {
            copies.value = 1;
            loadLabels();
        }
    },
);

function close(): void {
    emit('update:visible', false);
}

const esc = (s: string): string =>
    s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

/** Genera un documento imprimible con sólo las etiquetas y lanza la impresión. */
function print(): void {
    const n = Math.max(1, copies.value || 1);
    const cells = labels.value
        .flatMap((l) => Array.from({ length: n }, () => l))
        .map(
            (l) => `
            <div class="label">
                <div class="name">${esc(l.name)}</div>
                <img class="barcode" src="${l.barcode_png}" alt="" />
                <div class="foot"><span class="code">${esc(l.code)}</span><span class="price">${esc(money(l.price))}</span></div>
            </div>`,
        )
        .join('');

    const html = `<!doctype html><html><head><meta charset="utf-8"><title>Etiquetas</title>
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; font-family: Arial, Helvetica, sans-serif; }
            .sheet { display: flex; flex-wrap: wrap; gap: 3mm; padding: 5mm; }
            .label {
                width: 50mm; height: 30mm; border: 1px dashed #bbb; border-radius: 2mm;
                padding: 2mm; display: flex; flex-direction: column; align-items: center;
                justify-content: space-between; text-align: center; page-break-inside: avoid;
            }
            .name { font-size: 9pt; font-weight: 700; line-height: 1.1; max-height: 22pt; overflow: hidden; }
            .barcode { max-width: 100%; height: 12mm; object-fit: contain; }
            .foot { width: 100%; display: flex; align-items: center; justify-content: space-between; }
            .code { font-size: 7pt; color: #555; }
            .price { font-size: 11pt; font-weight: 800; }
            @media print { .label { border-color: transparent; } @page { margin: 6mm; } }
        </style></head>
        <body><div class="sheet">${cells}</div>
        <script>window.onload=function(){window.focus();window.print();setTimeout(function(){window.close();},300);};<\/script>
        </body></html>`;

    const w = window.open('', '_blank', 'width=800,height=600');
    if (!w) {
        toast.add({ severity: 'warn', summary: 'Bloqueado', detail: 'Permite las ventanas emergentes para imprimir', life: 4000 });
        return;
    }
    w.document.open();
    w.document.write(html);
    w.document.close();
}
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        header="Imprimir etiquetas"
        :style="{ width: '640px' }"
        :dismissable-mask="true"
        @update:visible="close"
    >
        <div v-if="loading" class="grid place-items-center py-10">
            <ProgressSpinner style="width: 3rem; height: 3rem" />
        </div>

        <div v-else class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">{{ labels.length }} etiqueta(s) · vista previa</p>
                <div class="flex items-center gap-2">
                    <label class="text-sm">Copias por etiqueta</label>
                    <InputNumber v-model="copies" :min="1" :max="99" show-buttons button-layout="horizontal" class="w-32" input-class="w-10 text-center" />
                </div>
            </div>

            <div class="grid max-h-[50vh] grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3">
                <div
                    v-for="(l, i) in labels"
                    :key="i"
                    class="flex flex-col items-center justify-between gap-1 rounded-lg border border-[var(--surface-border)] bg-white p-2 text-center"
                >
                    <span class="line-clamp-2 text-xs font-bold text-slate-800">{{ l.name }}</span>
                    <img :src="l.barcode_png" alt="" class="h-10 w-full object-contain" />
                    <div class="flex w-full items-center justify-between">
                        <span class="text-[10px] text-slate-500">{{ l.code }}</span>
                        <span class="text-sm font-extrabold text-slate-900">{{ money(l.price) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <Button label="Cerrar" text @click="close" />
            <Button label="Imprimir" icon="pi pi-print" :disabled="loading || !labels.length" @click="print" />
        </template>
    </Dialog>
</template>
