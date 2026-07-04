import http from '@/lib/http';
import type { Paginated, TableQuery } from '@/types';

export interface Customer {
    id: number;
    doc_type: string;
    doc_number: string | null;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    notes: string | null;
    is_active: boolean;
    created_at: string | null;
}

export interface Supplier extends Customer {
    contact_name: string | null;
}

export const DOC_TYPES = [
    { label: 'DNI', value: 'DNI' },
    { label: 'RUC', value: 'RUC' },
    { label: 'Carné de extranjería', value: 'CE' },
    { label: 'Pasaporte', value: 'PASAPORTE' },
];

const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

function contactApi<T>(resource: string) {
    return {
        async list(params: TableQuery): Promise<Paginated<T>> {
            return unwrap<Paginated<T>>(await http.get(`/${resource}`, { params }));
        },
        async options(): Promise<T[]> {
            return unwrap<T[]>(await http.get(`/${resource}`, { params: { all: 1 } }));
        },
        async save(payload: Partial<T>, id?: number): Promise<T> {
            const res = id ? await http.put(`/${resource}/${id}`, payload) : await http.post(`/${resource}`, payload);
            return unwrap<T>(res);
        },
        async remove(id: number): Promise<void> {
            await http.delete(`/${resource}/${id}`);
        },
    };
}

export const customersApi = contactApi<Customer>('customers');
export const suppliersApi = contactApi<Supplier>('suppliers');
