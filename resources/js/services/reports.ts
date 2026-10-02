import http, { apiUrl } from '@/lib/http';

export interface ReportData {
    title: string;
    headings: string[];
    rows: (string | number)[][];
    summary: Record<string, string | number>;
}

export const REPORT_TYPES = [
    { label: 'Ventas', value: 'sales', icon: 'pi pi-shopping-cart', dated: true },
    { label: 'Utilidad', value: 'profit', icon: 'pi pi-percentage', dated: true },
    { label: 'Compras', value: 'purchases', icon: 'pi pi-truck', dated: true },
    { label: 'Vendedores', value: 'sellers', icon: 'pi pi-users', dated: true },
    { label: 'Stock valorizado', value: 'stock', icon: 'pi pi-database', dated: false },
];

const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

export const reportsApi = {
    async get(type: string, from?: string, to?: string): Promise<ReportData> {
        return unwrap<ReportData>(await http.get(`/reports/${type}`, { params: { from, to } }));
    },
    /** URL de exportación (se abre en nueva pestaña; usa la sesión activa). */
    exportUrl(type: string, format: 'xlsx' | 'csv' | 'pdf', from?: string, to?: string): string {
        const qs = new URLSearchParams({ export: format });
        if (from) qs.append('from', from);
        if (to) qs.append('to', to);
        return apiUrl(`/reports/${type}?${qs.toString()}`);
    },
};
