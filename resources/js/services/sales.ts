import http from '@/lib/http';
import type { Paginated, TableQuery } from '@/types';

export interface SaleItemInput {
    product_id: number;
    quantity: number;
    price?: number;
    discount?: number;
}

export interface PaymentInput {
    method: string;
    amount: number;
    reference?: string;
}

export interface SalePayload {
    doc_type: string;
    customer_id?: number | null;
    warehouse_id?: number | null;
    discount?: number;
    notes?: string;
    items: SaleItemInput[];
    payments: PaymentInput[];
}

export interface Sale {
    id: number;
    doc_type: string;
    full_number: string;
    subtotal: number;
    tax: number;
    discount: number;
    total: number;
    tax_percent: number;
    paid: number;
    change: number;
    status: string;
    payment_status: string;
    sold_at: string | null;
    cancelled_at?: string | null;
    cancel_reason?: string | null;
    cancelled_by?: string | null;
    customer?: { id: number; name: string; doc_number: string | null } | null;
    user?: string;
    items?: { description: string; quantity: number; price: number; subtotal: number }[];
    payments?: { method: string; amount: number }[];
}

export const DOC_TYPES = [
    { label: 'Ticket', value: 'ticket' },
    { label: 'Boleta', value: 'boleta' },
    { label: 'Factura', value: 'factura' },
    { label: 'Cotización', value: 'cotizacion' },
];

export const PAYMENT_METHODS = [
    { label: 'Efectivo', value: 'efectivo', icon: 'pi pi-money-bill' },
    { label: 'Yape', value: 'yape', icon: 'pi pi-mobile' },
    { label: 'Plin', value: 'plin', icon: 'pi pi-mobile' },
    { label: 'Transferencia', value: 'transferencia', icon: 'pi pi-arrow-right-arrow-left' },
    { label: 'Tarjeta', value: 'tarjeta', icon: 'pi pi-credit-card' },
];

const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

export const salesApi = {
    async list(params: TableQuery): Promise<Paginated<Sale>> {
        return unwrap<Paginated<Sale>>(await http.get('/sales', { params }));
    },
    async get(id: number): Promise<Sale> {
        return unwrap<Sale>(await http.get(`/sales/${id}`));
    },
    async checkout(payload: SalePayload): Promise<Sale> {
        return unwrap<Sale>(await http.post('/sales', payload));
    },
    /** Anula una venta y devuelve el stock al inventario. */
    async cancel(id: number, reason?: string): Promise<Sale> {
        return unwrap<Sale>(await http.post(`/sales/${id}/cancel`, { reason }));
    },
    /** URL del ticket PDF (se abre en nueva pestaña). */
    ticketUrl(id: number): string {
        return `/api/v1/sales/${id}/ticket`;
    },
};
