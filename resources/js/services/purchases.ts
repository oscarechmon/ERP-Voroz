import http from '@/lib/http';
import type { Paginated, TableQuery } from '@/types';

export interface PurchaseItemInput {
    product_id: number;
    quantity: number;
    cost: number;
}

export interface Purchase {
    id: number;
    number: string;
    supplier_doc: string | null;
    doc_type: string;
    subtotal: number;
    tax: number;
    discount: number;
    total: number;
    status: string;
    purchased_at: string | null;
    supplier?: { id: number; name: string; doc_number: string | null } | null;
    user?: string;
    items?: { description: string; quantity: number; cost: number; subtotal: number }[];
}

const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

export const purchasesApi = {
    async list(params: TableQuery): Promise<Paginated<Purchase>> {
        return unwrap<Paginated<Purchase>>(await http.get('/purchases', { params }));
    },
    async get(id: number): Promise<Purchase> {
        return unwrap<Purchase>(await http.get(`/purchases/${id}`));
    },
    async register(payload: {
        supplier_id: number;
        warehouse_id: number;
        supplier_doc?: string;
        notes?: string;
        items: PurchaseItemInput[];
    }): Promise<Purchase> {
        return unwrap<Purchase>(await http.post('/purchases', payload));
    },
};
