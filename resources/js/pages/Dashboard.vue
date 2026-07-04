<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';
import { useUiStore } from '@/stores/ui';
import StatCard from '@/components/StatCard.vue';

interface Metrics {
    sales_today: number;
    sales_month: number;
    sales_year: number;
    products_sold: number;
    out_of_stock: number;
    low_stock: number;
    new_customers: number;
    profit_month: number;
    sales_by_day: { date: string; total: number }[];
    sales_by_category: { category: string; total: number }[];
    top_products: { name: string; qty: number; total: number }[];
    top_sellers: { name: string; total: number; count: number }[];
    recent_sales: { full_number: string; customer: string; total: number; sold_at: string | null }[];
}

const ui = useUiStore();
const loading = ref(true);
const m = ref<Metrics | null>(null);

const currency = (n: number): string => new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(n ?? 0);
const dt = (s: string | null): string => (s ? new Date(s).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' }) : '');

onMounted(async () => {
    try {
        const { data } = await http.get('/dashboard/metrics');
        m.value = data.data;
    } catch {
        m.value = null;
    } finally {
        loading.value = false;
    }
});

const salesChart = computed(() => {
    const rows = m.value?.sales_by_day ?? [];
    return {
        series: [{ name: 'Ventas', data: rows.map((r) => r.total) }],
        options: {
            chart: { type: 'area', toolbar: { show: false }, fontFamily: 'inherit' },
            colors: ['#3366ff'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
            xaxis: { categories: rows.map((r) => r.date), labels: { style: { colors: '#94a3b8' } } },
            yaxis: { labels: { style: { colors: '#94a3b8' } } },
            grid: { borderColor: ui.dark ? '#262a36' : '#e6e8f0', strokeDashArray: 4 },
            theme: { mode: ui.dark ? 'dark' : 'light' },
            tooltip: { theme: ui.dark ? 'dark' : 'light' },
        },
    };
});

const categoryChart = computed(() => {
    const rows = m.value?.sales_by_category ?? [];
    return {
        series: rows.map((r) => r.total),
        options: {
            chart: { type: 'donut', fontFamily: 'inherit' },
            labels: rows.map((r) => r.category),
            colors: ['#3366ff', '#22c55e', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4'],
            legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
            dataLabels: { enabled: true, formatter: (v: number) => `${v.toFixed(0)}%` },
            theme: { mode: ui.dark ? 'dark' : 'light' },
            stroke: { width: 0 },
        },
    };
});
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Dashboard</h1>
            <p class="text-sm text-slate-500">Resumen general de tu negocio en tiempo real.</p>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Ventas de hoy" :value="currency(m?.sales_today ?? 0)" icon="pi pi-dollar" tone="brand" :loading="loading" />
            <StatCard label="Ventas del mes" :value="currency(m?.sales_month ?? 0)" icon="pi pi-calendar" tone="emerald" :loading="loading" />
            <StatCard label="Ventas del año" :value="currency(m?.sales_year ?? 0)" icon="pi pi-chart-line" tone="violet" :loading="loading" />
            <StatCard label="Utilidad del mes" :value="currency(m?.profit_month ?? 0)" icon="pi pi-percentage" tone="emerald" :loading="loading" hint="Precio − costo de lo vendido" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Productos vendidos (mes)" :value="m?.products_sold ?? 0" icon="pi pi-box" tone="brand" :loading="loading" />
            <StatCard label="Clientes nuevos" :value="m?.new_customers ?? 0" icon="pi pi-user-plus" tone="amber" :loading="loading" />
            <StatCard label="Sin stock" :value="m?.out_of_stock ?? 0" icon="pi pi-exclamation-triangle" tone="rose" :loading="loading" hint="Requieren reposición" />
            <StatCard label="Stock bajo" :value="m?.low_stock ?? 0" icon="pi pi-arrow-down" tone="amber" :loading="loading" />
        </div>

        <!-- Gráficos -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-5 shadow-sm lg:col-span-2">
                <h3 class="mb-4 font-semibold">Ventas de los últimos 14 días</h3>
                <apexchart type="area" height="300" :options="salesChart.options" :series="salesChart.series" />
            </div>
            <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-5 shadow-sm">
                <h3 class="mb-4 font-semibold">Ventas por categoría</h3>
                <apexchart v-if="categoryChart.series.length" type="donut" height="300" :options="categoryChart.options" :series="categoryChart.series" />
                <div v-else class="grid h-[300px] place-items-center text-sm text-slate-400">Sin datos aún</div>
            </div>
        </div>

        <!-- Top productos + Últimas ventas -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-5 shadow-sm">
                <h3 class="mb-4 font-semibold">Productos más vendidos</h3>
                <ul class="space-y-3">
                    <li v-for="(p, i) in m?.top_products ?? []" :key="i" class="flex items-center gap-3">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-brand-50 text-xs font-bold text-brand-600 dark:bg-brand-500/10">{{ i + 1 }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm">{{ p.name }}</span>
                        <span class="text-xs text-slate-400">{{ p.qty }} u</span>
                        <span class="w-24 text-right text-sm font-semibold">{{ currency(p.total) }}</span>
                    </li>
                    <li v-if="!loading && !(m?.top_products?.length)" class="py-6 text-center text-sm text-slate-400">Sin datos</li>
                </ul>
            </div>

            <div class="rounded-2xl border border-[var(--surface-border)] bg-[var(--surface-card)] p-5 shadow-sm">
                <h3 class="mb-4 font-semibold">Últimas ventas</h3>
                <ul class="divide-y divide-[var(--surface-border)]">
                    <li v-for="(s, i) in m?.recent_sales ?? []" :key="i" class="flex items-center justify-between py-2.5">
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ s.full_number }}</p>
                            <p class="truncate text-xs text-slate-400">{{ s.customer }} · {{ dt(s.sold_at) }}</p>
                        </div>
                        <span class="text-sm font-semibold">{{ currency(s.total) }}</span>
                    </li>
                    <li v-if="!loading && !(m?.recent_sales?.length)" class="py-6 text-center text-sm text-slate-400">Sin ventas aún</li>
                </ul>
            </div>
        </div>
    </div>
</template>
