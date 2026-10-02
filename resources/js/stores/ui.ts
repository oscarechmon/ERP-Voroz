import { defineStore } from 'pinia';

interface UiState {
    dark: boolean;
    sidebarCollapsed: boolean;
    sidebarMobileOpen: boolean;
}

const STORAGE_KEY = 'sistema.ui';

/** Persiste las preferencias de interfaz (tema y sidebar) en localStorage. */
function loadState(): Partial<UiState> {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}');
    } catch {
        return {};
    }
}

/**
 * Store de interfaz: modo oscuro/claro y estado del sidebar (colapsable + móvil).
 * Aplica/retira la clase `.app-dark` en <html> para activar el tema de PrimeVue.
 */
export const useUiStore = defineStore('ui', {
    state: (): UiState => {
        const saved = loadState();
        const prefersDark = window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
        return {
            dark: saved.dark ?? prefersDark,
            sidebarCollapsed: saved.sidebarCollapsed ?? false,
            sidebarMobileOpen: false,
        };
    },

    actions: {
        persist(): void {
            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({ dark: this.dark, sidebarCollapsed: this.sidebarCollapsed }),
            );
        },

        applyTheme(): void {
            document.documentElement.classList.toggle('app-dark', this.dark);
        },

        toggleDark(): void {
            this.dark = !this.dark;
            this.applyTheme();
            this.persist();
        },

        toggleSidebar(): void {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            this.persist();
        },

        toggleMobileSidebar(force?: boolean): void {
            this.sidebarMobileOpen = force ?? !this.sidebarMobileOpen;
        },
    },
});
