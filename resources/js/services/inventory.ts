import http from '@/lib/http';
import type { Paginated, TableQuery } from '@/types';

export interface StockRow {
    id: number;
    product_id: number;
    warehouse_id: number;
    quantity: number;
    avg_cost: number;
    is_low: boolean | null;
    product?: { id: number; code: string; name: string; stock_min: number; price: number };
    warehouse?: { id: number; name: string };
}

export interface Movement {
    id: number;
    type: string;
    quantity: number;
    cost: number;
    balance: number;
    notes: string | null;
    reference_type: string | null;
    product?: { id: number; code: string; name: string };
    warehouse?: { id: number; name: string };
    user?: string | null;
    created_at: string | null;
}

export interface Warehouse {
    id: number;
    name: string;
    branch: string | null;
    is_default: boolean;
}

const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

export const inventoryApi = {
    async stock(params: TableQuery): Promise<Paginated<StockRow>> {
        return unwrap<Paginated<StockRow>>(await http.get('/stock', { params }));
    },
    async summary(): Promise<{ out_of_stock: number; low_stock: number; total_valued: number }> {
        return unwrap(await http.get('/stock/summary'));
    },
    async kardex(params: TableQuery): Promise<Paginated<Movement>> {
        return unwrap<Paginated<Movement>>(await http.get('/kardex', { params }));
    },
    async adjust(payload: { product_id: number; warehouse_id: number; quantity: number; notes?: string }): Promise<Movement> {
        return unwrap<Movement>(await http.post('/inventory/adjustments', payload));
    },
    async warehouses(): Promise<Warehouse[]> {
        return unwrap<Warehouse[]>(await http.get('/warehouses'));
    },
};

/** Etiquetas legibles de los tipos de movimiento del kardex. */
export const MOVEMENT_LABELS: Record<string, { label: string; severity: string }> = {
    in: { label: 'Entrada', severity: 'success' },
    out: { label: 'Salida', severity: 'danger' },
    adjustment: { label: 'Ajuste', severity: 'warn' },
    transfer_in: { label: 'Transf. entrada', severity: 'info' },
    transfer_out: { label: 'Transf. salida', severity: 'info' },
};
