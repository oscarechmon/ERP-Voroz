import { defineStore } from 'pinia';
import http, { ensureCsrf } from '@/lib/http';
import type { AuthUser } from '@/types';

interface AuthState {
    user: AuthUser | null;
    ready: boolean;
}

/**
 * Store de autenticación (Sanctum SPA por cookie de sesión).
 * Centraliza el usuario actual, sus roles/permisos y las acciones de sesión.
 */
export const useAuthStore = defineStore('auth', {
    state: (): AuthState => ({
        user: null,
        ready: false,
    }),

    getters: {
        isAuthenticated: (state): boolean => state.user !== null,
        /** Verifica un permiso concreto (Super Admin siempre pasa). */
        can:
            (state) =>
            (permission: string): boolean => {
                if (!state.user) return false;
                if (state.user.roles.includes('Super Administrador')) return true;
                return state.user.permissions.includes(permission);
            },
        hasRole:
            (state) =>
            (role: string): boolean =>
                state.user?.roles.includes(role) ?? false,
    },

    actions: {
        /** Carga el usuario autenticado al iniciar la app; nunca lanza. */
        async bootstrap(): Promise<void> {
            // La primera vez la sesión ya viene en la página (app.blade.php):
            // no hace falta preguntarle al servidor. Se usa una sola vez.
            const boot = window as Window & { __SISTEMA_SESION__?: AuthUser | null };
            if (boot.__SISTEMA_SESION__ !== undefined) {
                this.user = boot.__SISTEMA_SESION__;
                delete boot.__SISTEMA_SESION__;
                this.ready = true;
                return;
            }

            try {
                const { data } = await http.get('/auth/me');
                this.user = data.data;
            } catch {
                this.user = null;
            } finally {
                this.ready = true;
            }
        },

        async login(email: string, password: string, remember = false): Promise<void> {
            await ensureCsrf();
            await http.post('/auth/login', { email, password, remember });
            const { data } = await http.get('/auth/me');
            this.user = data.data;
        },

        async logout(): Promise<void> {
            try {
                await http.post('/auth/logout');
            } finally {
                this.user = null;
            }
        },
    },
});
