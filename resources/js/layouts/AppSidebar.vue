<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { menu, type MenuItem } from '@/router/menu';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const auth = useAuthStore();
const ui = useUiStore();
const route = useRoute();

/** Filtra el menú según los permisos del usuario (oculta lo no autorizado). */
function visible(items: MenuItem[]): MenuItem[] {
    return items
        .map((item) => {
            const children = item.children ? visible(item.children) : undefined;
            const allowed = item.permission ? auth.can(item.permission) : true;
            if (item.children) {
                return children && children.length ? { ...item, children } : null;
            }
            return allowed ? item : null;
        })
        .filter((i): i is MenuItem => i !== null);
}

const items = computed(() => visible(menu));

// Control de grupos expandidos (acordeón del sidebar).
const openGroups = ref<Set<string>>(new Set(items.value.filter((i) => i.children).map((i) => i.label)));
function toggleGroup(label: string): void {
    openGroups.value.has(label) ? openGroups.value.delete(label) : openGroups.value.add(label);
    openGroups.value = new Set(openGroups.value);
}

const isActive = (to?: string): boolean => (to ? route.path === to : false);
const collapsed = computed(() => ui.sidebarCollapsed);
</script>

<template>
    <aside
        class="flex h-full flex-col border-r border-[var(--surface-border)] bg-[var(--surface-card)] transition-all duration-200"
        :class="collapsed ? 'w-[76px]' : 'w-64'"
    >
        <!-- Marca -->
        <div class="flex h-16 items-center gap-3 px-4">
            <div class="grid h-9 w-9 place-items-center rounded-xl bg-brand-500 text-white shadow-sm">
                <i class="pi pi-bolt text-lg"></i>
            </div>
            <span v-if="!collapsed" class="text-lg font-bold tracking-tight">Voroz<span class="text-brand-500">ERP</span></span>
        </div>

        <!-- Navegación -->
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
            <template v-for="item in items" :key="item.label">
                <!-- Ítem simple -->
                <router-link
                    v-if="!item.children"
                    :to="item.to!"
                    class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                    :class="isActive(item.to)
                        ? 'bg-brand-500 text-white shadow-sm'
                        : 'text-slate-600 hover:bg-brand-50 dark:text-slate-300 dark:hover:bg-white/5'"
                    v-tooltip.right="collapsed ? item.label : undefined"
                >
                    <i :class="item.icon" class="text-base"></i>
                    <span v-if="!collapsed">{{ item.label }}</span>
                </router-link>

                <!-- Grupo con hijos -->
                <div v-else>
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-brand-50 dark:text-slate-300 dark:hover:bg-white/5"
                        @click="toggleGroup(item.label)"
                        v-tooltip.right="collapsed ? item.label : undefined"
                    >
                        <i :class="item.icon" class="text-base"></i>
                        <span v-if="!collapsed" class="flex-1 text-left">{{ item.label }}</span>
                        <i
                            v-if="!collapsed"
                            class="pi pi-chevron-down text-xs transition-transform"
                            :class="{ 'rotate-180': openGroups.has(item.label) }"
                        ></i>
                    </button>
                    <transition name="fade">
                        <div v-if="openGroups.has(item.label) && !collapsed" class="mt-1 space-y-1 pl-4">
                            <router-link
                                v-for="child in item.children"
                                :key="child.label"
                                :to="child.to!"
                                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors"
                                :class="isActive(child.to)
                                    ? 'bg-brand-50 font-semibold text-brand-700 dark:bg-white/10 dark:text-white'
                                    : 'text-slate-500 hover:bg-brand-50 dark:text-slate-400 dark:hover:bg-white/5'"
                            >
                                <i :class="child.icon" class="text-sm"></i>
                                <span>{{ child.label }}</span>
                            </router-link>
                        </div>
                    </transition>
                </div>
            </template>
        </nav>

        <!-- Colapsar -->
        <div class="border-t border-[var(--surface-border)] p-3">
            <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-brand-50 dark:hover:bg-white/5"
                @click="ui.toggleSidebar()"
            >
                <i class="pi" :class="collapsed ? 'pi-angle-double-right' : 'pi-angle-double-left'"></i>
                <span v-if="!collapsed">Colapsar</span>
            </button>
        </div>
    </aside>
</template>
