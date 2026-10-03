<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import ToggleSwitch from 'primevue/toggleswitch';
import Button from 'primevue/button';
import Message from 'primevue/message';
import { AxiosError } from 'axios';
import { brandsApi, categoriesApi, productsApi, unitsApi, PRODUCT_TYPES, type Option, type Product, type ProductType } from '@/services/catalog';

const props = defineProps<{ visible: boolean; product: Product | null }>();
const emit = defineEmits<{ 'update:visible': [boolean]; saved: [] }>();

const categories = ref<Option[]>([]);
const brands = ref<Option[]>([]);
const units = ref<Option[]>([]);
const saving = ref(false);
const errors = ref<Record<string, string[]>>({});
const imageFile = ref<File | null>(null);
const currentImage = ref<string | null>(null);
// Lo que se ve: la imagen recién elegida o la que ya tiene.
const preview = computed(() => (imageFile.value ? URL.createObjectURL(imageFile.value) : currentImage.value));

const blank = () => ({
    type: 'product' as ProductType,
    name: '',
    code: '',
    barcode: '',
    sku: '',
    description: '',
    category_id: null as number | null,
    brand_id: null as number | null,
    unit_id: null as number | null,
    cost: 0,
    price: 0,
    wholesale_price: null as number | null,
    offer_price: null as number | null,
    stock_min: 0,
    stock_max: null as number | null,
    is_active: true,
    // null en lo que llegó de la web y nadie tocó aquí: no se cambia al guardar.
    web_published: false as boolean | null,
    duration_minutes: null as number | null,
});

const form = ref(blank());
const isEdit = computed(() => props.product !== null);
const isService = computed(() => form.value.type === 'service');
const noun = computed(() => (isService.value ? 'servicio' : 'producto'));
const title = computed(() => (isEdit.value ? `Editar ${noun.value}` : `Nuevo ${noun.value}`));

// Carga las opciones de selects una vez al abrir por primera vez.
async function ensureOptions(): Promise<void> {
    if (categories.value.length) return;
    [categories.value, brands.value, units.value] = await Promise.all([
        categoriesApi.options(),
        brandsApi.options(),
        unitsApi.options(),
    ]);
}

watch(
    () => props.visible,
    async (open) => {
        if (!open) return;
        errors.value = {};
        imageFile.value = null;
        currentImage.value = props.product?.image_url ?? null;
        await ensureOptions();
        if (props.product) {
            const p = props.product;
            form.value = {
                type: p.type,
                name: p.name,
                code: p.code,
                barcode: p.barcode ?? '',
                sku: p.sku ?? '',
                description: p.description ?? '',
                category_id: p.category_id,
                brand_id: p.brand_id,
                unit_id: p.unit_id,
                cost: p.cost,
                price: p.price,
                wholesale_price: p.wholesale_price,
                offer_price: p.offer_price,
                stock_min: p.stock_min,
                stock_max: p.stock_max,
                is_active: p.is_active,
                web_published: p.web_published,
                duration_minutes: p.duration_minutes,
            };
        } else {
            form.value = blank();
        }
    },
);

function onFile(e: Event): void {
    const target = e.target as HTMLInputElement;
    imageFile.value = target.files?.[0] ?? null;
}

const err = (field: string): string | undefined => errors.value[field]?.[0];

async function submit(): Promise<void> {
    saving.value = true;
    errors.value = {};
    try {
        const payload = { ...form.value, image: imageFile.value };
        await productsApi.save(payload, props.product?.id);
        emit('saved');
    } catch (e) {
        const ax = e as AxiosError<{ errors?: Record<string, string[]>; message?: string }>;
        if (ax.response?.status === 422) {
            errors.value = ax.response.data.errors ?? {};
        }
    } finally {
        saving.value = false;
    }
}

const close = () => emit('update:visible', false);
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :header="title"
        :style="{ width: '720px' }"
        :dismissable-mask="true"
        @update:visible="close"
    >
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">Tipo</label>
                <SelectButton v-model="form.type" :options="PRODUCT_TYPES" option-label="label" option-value="value" :allow-empty="false" />
                <p v-if="isService" class="mt-1 text-xs text-slate-400">Los servicios se venden pero no llevan stock.</p>
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">Nombre *</label>
                <InputText v-model="form.name" class="w-full" :invalid="!!err('name')" />
                <Message v-if="err('name')" severity="error" size="small" variant="simple">{{ err('name') }}</Message>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Código interno</label>
                <InputText v-model="form.code" class="w-full" placeholder="Auto (PRD-000001)" :invalid="!!err('code')" />
                <Message v-if="err('code')" severity="error" size="small" variant="simple">{{ err('code') }}</Message>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Código de barras</label>
                <InputText v-model="form.barcode" class="w-full" placeholder="Auto EAN-13" />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Categoría</label>
                <Select v-model="form.category_id" :options="categories" option-label="name" option-value="id" class="w-full" filter show-clear placeholder="Seleccionar" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Marca</label>
                <Select v-model="form.brand_id" :options="brands" option-label="name" option-value="id" class="w-full" filter show-clear placeholder="Seleccionar" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Unidad</label>
                <Select v-model="form.unit_id" :options="units" option-label="name" option-value="id" class="w-full" show-clear placeholder="Seleccionar" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">SKU</label>
                <InputText v-model="form.sku" class="w-full" />
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Costo *</label>
                <InputNumber v-model="form.cost" mode="currency" currency="PEN" locale="es-PE" class="w-full" :invalid="!!err('cost')" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Precio venta *</label>
                <InputNumber v-model="form.price" mode="currency" currency="PEN" locale="es-PE" class="w-full" :invalid="!!err('price')" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Precio mayorista</label>
                <InputNumber v-model="form.wholesale_price" mode="currency" currency="PEN" locale="es-PE" class="w-full" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Precio oferta</label>
                <InputNumber v-model="form.offer_price" mode="currency" currency="PEN" locale="es-PE" class="w-full" />
            </div>

            <div v-if="!isService">
                <label class="mb-1 block text-sm font-medium">Stock mínimo</label>
                <InputNumber v-model="form.stock_min" class="w-full" :min="0" />
            </div>
            <div v-if="!isService">
                <label class="mb-1 block text-sm font-medium">Stock máximo</label>
                <InputNumber v-model="form.stock_max" class="w-full" :min="0" />
            </div>

            <div v-if="isService">
                <label class="mb-1 block text-sm font-medium">Duración (minutos)</label>
                <InputNumber v-model="form.duration_minutes" class="w-full" :min="5" :max="600" placeholder="Ej. 60" :invalid="!!err('duration_minutes')" />
                <Message v-if="err('duration_minutes')" severity="error" size="small" variant="simple">{{ err('duration_minutes') }}</Message>
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">Descripción</label>
                <Textarea v-model="form.description" class="w-full" rows="2" auto-resize />
                <p class="mt-1 text-xs text-slate-400">Es la que se muestra en la web.</p>
            </div>

            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">Imagen</label>
                <div class="flex items-center gap-3">
                    <span v-if="preview" class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-lg bg-slate-100 dark:bg-white/5">
                        <img :src="preview" class="h-full w-full object-cover" alt="" />
                    </span>
                    <input type="file" accept="image/*" class="text-sm" @change="onFile" />
                </div>
                <Message v-if="err('image')" severity="error" size="small" variant="simple">{{ err('image') }}</Message>
            </div>

            <div class="flex items-center gap-2">
                <ToggleSwitch v-model="form.is_active" input-id="active" />
                <label for="active" class="text-sm font-medium">Activo</label>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <ToggleSwitch
                        :model-value="form.web_published ?? false"
                        input-id="web-published"
                        @update:model-value="(value: boolean) => (form.web_published = value)"
                    />
                    <label for="web-published" class="text-sm font-medium">Publicar en la web</label>
                </div>
                <p class="mt-1 text-xs text-slate-400">Se muestra en sinexcusas.org.pe con su imagen, descripción y precio.</p>
            </div>
        </div>

        <template #footer>
            <Button label="Cancelar" text @click="close" />
            <Button :label="isEdit ? 'Guardar cambios' : `Crear ${noun}`" icon="pi pi-check" :loading="saving" @click="submit" />
        </template>
    </Dialog>
</template>
