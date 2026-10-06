import http from '@/lib/http';
import type { Paginated, TableQuery } from '@/types';

export type OnlineOrderStatus =
    | 'pending_payment' | 'payment_failed' | 'paid' | 'preparing'
    | 'shipped' | 'ready_for_pickup' | 'delivered' | 'cancelled';

/** delivery = en Lima; province = envío a provincia por agencia (Shalom). */
export type Fulfillment = 'delivery' | 'province' | 'pickup';

export const FULFILLMENT: Record<Fulfillment, { label: string; short: string; icon: string }> = {
    delivery: { label: 'Delivery en Lima', short: 'Delivery', icon: 'pi pi-truck' },
    province: { label: 'Envío a provincia (Shalom)', short: 'Provincia', icon: 'pi pi-send' },
    pickup: { label: 'Recojo en el centro', short: 'Recojo', icon: 'pi pi-shop' },
};

export const DOCUMENT_TYPE: Record<'dni' | 'ce', string> = { dni: 'DNI', ce: 'CE' };

/** Costo de un tipo de envío y si la web lo ofrece. */
export interface ShippingRate {
    enabled: boolean;
    fee: number;
    label: string;
}

export type ShippingRates = Record<'delivery' | 'province', ShippingRate>;

export interface OnlineOrder {
    id: number;
    code: string;
    status: OnlineOrderStatus;
    next_statuses: OnlineOrderStatus[];
    fulfillment: Fulfillment;
    fulfillment_label: string;
    customer_id: number | null;
    customer_name: string | null;
    customer_email: string | null;
    recipient_name: string;
    document_type: 'dni' | 'ce' | null;
    document_number: string | null;
    phone: string | null;
    address: string | null;
    district: string | null;
    reference: string | null;
    department: string | null;
    province: string | null;
    agency: string | null;
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
    async shipping(): Promise<ShippingRates> {
        return unwrap<ShippingRates>(await http.get('/online-orders/shipping'));
    },
    async saveShipping(rates: Record<'delivery' | 'province', { enabled: boolean; fee: number }>): Promise<ShippingRates> {
        return unwrap<ShippingRates>(await http.put('/online-orders/shipping', rates));
    },
};
