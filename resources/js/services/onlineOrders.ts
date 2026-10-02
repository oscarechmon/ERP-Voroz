import http from '@/lib/http';
import type { Paginated, TableQuery } from '@/types';

export type OnlineOrderStatus =
    | 'pending_payment' | 'payment_failed' | 'paid' | 'preparing'
    | 'shipped' | 'ready_for_pickup' | 'delivered' | 'cancelled';

export interface OnlineOrder {
    id: number;
    code: string;
    status: OnlineOrderStatus;
    next_statuses: OnlineOrderStatus[];
    fulfillment: 'delivery' | 'pickup';
    customer_id: number | null;
    customer_name: string | null;
    customer_email: string | null;
    recipient_name: string;
    phone: string | null;
    address: string | null;
    district: string | null;
    reference: string | null;
    notes: string | null;
    subtotal: number;
    delivery_fee: number;
    total: number;
    gateway: string | null;
    payment_reference: string | null;
    paid_at: string | null;
    ordered_at: string | null;
    sale?: { id: number; full_number: string; status: string } | null;
    items?: { product_id: number | null; item_type: string; name: string; unit_price: number; quantity: number; subtotal: number }[];
    histories?: { status: string; note: string | null; internal: boolean; user_name: string | null; happened_at: string }[];
}

export const ORDER_STATUS: Record<OnlineOrderStatus, { label: string; severity: string }> = {
    pending_payment: { label: 'Pendiente de pago', severity: 'warn' },
    payment_failed: { label: 'Pago rechazado', severity: 'danger' },
    paid: { label: 'Pagado', severity: 'info' },
    preparing: { label: 'En preparación', severity: 'info' },
    shipped: { label: 'En camino', severity: 'contrast' },
    ready_for_pickup: { label: 'Listo para recoger', severity: 'contrast' },
    delivered: { label: 'Entregado', severity: 'success' },
    cancelled: { label: 'Anulado', severity: 'secondary' },
};

const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

export const onlineOrdersApi = {
    async list(params: TableQuery): Promise<Paginated<OnlineOrder>> {
        return unwrap<Paginated<OnlineOrder>>(await http.get('/online-orders', { params }));
    },
    async get(id: number): Promise<OnlineOrder> {
        return unwrap<OnlineOrder>(await http.get(`/online-orders/${id}`));
    },
    async status(id: number, status: OnlineOrderStatus, note?: string): Promise<OnlineOrder> {
        return unwrap<OnlineOrder>(await http.post(`/online-orders/${id}/status`, { status, note }));
    },
};
