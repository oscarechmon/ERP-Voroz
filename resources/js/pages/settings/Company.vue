<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import ToggleSwitch from 'primevue/toggleswitch';
import Button from 'primevue/button';
import Message from 'primevue/message';
import { AxiosError } from 'axios';
import { companyApi, type Company } from '@/services/settings';
import { useAuthStore } from '@/stores/auth';

const toast = useToast();
const auth = useAuthStore();
const loading = ref(true);
const saving = ref(false);
const errors = ref<Record<string, string[]>>({});
const logoFile = ref<File | null>(null);
const form = ref<Company | null>(null);

async function load(): Promise<void> {
    loading.value = true;
    try {
        form.value = await companyApi.get();
    } finally {
        loading.value = false;
    }
}

function onLogo(e: Event): void {
    logoFile.value = (e.target as HTMLInputElement).files?.[0] ?? null;
}

async function save(): Promise<void> {
    if (!form.value) return;
    saving.value = true;
    errors.value = {};
    try {
        form.value = await companyApi.update({ ...form.value, logo: logoFile.value });
        logoFile.value = null;
        toast.add({ severity: 'success', summary: 'Guardado', detail: 'Datos de la empresa actualizados', life: 2500 });
    } catch (e) {
        const ax = e as AxiosError<{ errors?: Record<string, string[]> }>;
        if (ax.response?.status === 422) errors.value = ax.response.data.errors ?? {};
    } finally {
        saving.value = false;
    }
}

const err = (f: string): string | undefined => errors.value[f]?.[0];
onMounted(load);
</script>

<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Datos de la empresa</h1>
            <p class="text-sm text-slate-500">Información que aparece en comprobantes y reportes.</p>
        </div>

        <div v-if="form" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="space-y-4 rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-5 shadow-sm lg:col-span-2">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Razón social *</label>
                        <InputText v-model="form.business_name" class="w-full" :invalid="!!err('business_name')" />
                        <Message v-if="err('business_name')" severity="error" size="small" variant="simple">{{ err('business_name') }}</Message>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Nombre comercial</label>
                        <InputText v-model="form.trade_name" class="w-full" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">RUC *</label>
                        <InputText v-model="form.ruc" class="w-full" :invalid="!!err('ruc')" />
                        <Message v-if="err('ruc')" severity="error" size="small" variant="simple">{{ err('ruc') }}</Message>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Dirección</label>
                        <InputText v-model="form.address" class="w-full" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Teléfono</label>
                        <InputText v-model="form.phone" class="w-full" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Correo</label>
                        <InputText v-model="form.email" class="w-full" :invalid="!!err('email')" />
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-5 shadow-sm">
                    <label class="mb-2 block text-sm font-medium">Logo</label>
                    <div class="mb-3 grid h-24 w-full place-items-center overflow-hidden rounded-xl bg-slate-50 dark:bg-white/5">
                        <img v-if="form.logo_url" :src="form.logo_url" class="max-h-full object-contain" alt="logo" />
                        <i v-else class="pi pi-image text-3xl text-slate-300"></i>
                    </div>
                    <input type="file" accept="image/*" class="text-sm" @change="onLogo" />
                </div>

                <div class="space-y-4 rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-5 shadow-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Moneda</label>
                            <InputText v-model="form.currency" class="w-full" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Símbolo</label>
                            <InputText v-model="form.currency_symbol" class="w-full" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">IGV (%)</label>
                        <InputNumber v-model="form.igv_percent" class="w-full" :min="0" :max="100" suffix=" %" />
                    </div>
                    <div class="flex items-center gap-2">
                        <ToggleSwitch v-model="form.prices_include_igv" input-id="incl" />
                        <label for="incl" class="text-sm font-medium">Los precios incluyen IGV</label>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3">
                <Button v-if="auth.can('settings.edit')" label="Guardar cambios" icon="pi pi-check" :loading="saving" @click="save" />
            </div>
        </div>
    </div>
</template>
