<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import { AxiosError } from 'axios';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const toast = useToast();

const email = ref('');
const password = ref('');
const remember = ref(true);
const loading = ref(false);

async function submit(): Promise<void> {
    loading.value = true;
    try {
        await auth.login(email.value, password.value, remember.value);
        const redirect = (route.query.redirect as string) || '/';
        router.push(redirect);
    } catch (e) {
        const err = e as AxiosError<{ message?: string }>;
        toast.add({
            severity: 'error',
            summary: 'No se pudo iniciar sesión',
            detail: err.response?.data?.message ?? 'Credenciales incorrectas',
            life: 4000,
        });
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="grid min-h-screen lg:grid-cols-2">
        <!-- Panel de marca -->
        <div class="relative hidden overflow-hidden bg-brand-600 lg:block">
            <div class="absolute inset-0 bg-gradient-to-br from-brand-500 via-brand-600 to-brand-800"></div>
            <div class="relative z-10 flex h-full flex-col justify-between p-12 text-white">
                <div class="flex items-center gap-3">
                    <div class="grid h-11 w-11 place-items-center rounded-2xl bg-white/15 backdrop-blur">
                        <i class="pi pi-bolt text-2xl"></i>
                    </div>
                    <span class="text-2xl font-bold">Sistema ERP</span>
                </div>
                <div>
                    <h1 class="text-4xl font-bold leading-tight">Gestiona tu negocio<br />sin fricción.</h1>
                    <p class="mt-4 max-w-md text-white/80">
                        Ventas, inventario, compras, caja y reportes en un solo lugar. Rápido, moderno y listo para crecer contigo.
                    </p>
                </div>
                <p class="text-sm text-white/60">© {{ new Date().getFullYear() }} · Sistema de ventas</p>
            </div>
        </div>

        <!-- Formulario -->
        <div class="flex items-center justify-center bg-[var(--surface-ground)] p-6">
            <div class="w-full max-w-sm">
                <div class="mb-8 text-center lg:hidden">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-brand-500 text-white">
                        <i class="pi pi-bolt text-xl"></i>
                    </div>
                </div>
                <h2 class="text-2xl font-bold">Bienvenido de nuevo</h2>
                <p class="mt-1 text-sm text-slate-500">Ingresa tus credenciales para continuar.</p>

                <form class="mt-8 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Correo electrónico</label>
                        <InputText v-model="email" type="email" class="w-full" placeholder="tucorreo@empresa.com" autofocus />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Contraseña</label>
                        <Password v-model="password" class="w-full" input-class="w-full" :feedback="false" toggle-mask placeholder="••••••••" />
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox v-model="remember" :binary="true" /> Recordarme
                        </label>
                        <a class="text-sm font-medium text-brand-600 hover:underline" href="#">¿Olvidaste tu contraseña?</a>
                    </div>
                    <Button type="submit" label="Iniciar sesión" class="w-full" :loading="loading" />
                </form>
            </div>
        </div>
    </div>
</template>
