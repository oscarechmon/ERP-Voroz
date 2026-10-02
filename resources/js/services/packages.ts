import http from '@/lib/http';
import type { Paginated, TableQuery } from '@/types';

export interface Package {
    id: number;
    product_id: number | null;
    code?: string | null;
    name: string;
    description: string | null;
    price: number;
    total_sessions: number;
    validity_days: number | null;
    is_active: boolean;
    services: { id: number; name: string }[];
}

export interface PackagePayload {
    name: string;
    description?: string | null;
    price: number;
    total_sessions: number;
    validity_days?: number | null;
    is_active: boolean;
    service_ids: number[];
}

export interface CustomerPackage {
    id: number;
    customer: { id: number; code: string | null; name: string } | null;
    package_id: number | null;
    package_name: string;
    sale_id: number | null;
    price: number;
    total_sessions: number;
    used_sessions: number;
    remaining_sessions: number;
    purchased_at: string;
    expires_at: string | null;
    status: 'active' | 'completed' | 'expired' | 'cancelled';
    sessions?: { session_number: number; attendance_id: number | null; consumed_at: string }[];
}

export const CUSTOMER_PACKAGE_STATUS: Record<string, { label: string; severity: string }> = {
    active: { label: 'Activo', severity: 'success' },
    completed: { label: 'Completado', severity: 'info' },
    expired: { label: 'Vencido', severity: 'warn' },
    cancelled: { label: 'Anulado', severity: 'danger' },
};

const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

export const packagesApi = {
    async list(params: TableQuery): Promise<Paginated<Package>> {
        return unwrap<Paginated<Package>>(await http.get('/packages', { params }));
    },
    async options(): Promise<Package[]> {
        return unwrap<Package[]>(await http.get('/packages', { params: { all: 1 } }));
    },
    async save(payload: PackagePayload, id?: number): Promise<Package> {
        const res = id ? await http.put(`/packages/${id}`, payload) : await http.post('/packages', payload);
        return unwrap<Package>(res);
    },
    async remove(id: number): Promise<void> {
        await http.delete(`/packages/${id}`);
    },
};

export const customerPackagesApi = {
    async list(params: TableQuery): Promise<Paginated<CustomerPackage>> {
        return unwrap<Paginated<CustomerPackage>>(await http.get('/customer-packages', { params }));
    },
    /** Sin paginar: p. ej. los paquetes usables de un cliente para un servicio. */
    async all(params: Record<string, unknown>): Promise<CustomerPackage[]> {
        return unwrap<CustomerPackage[]>(await http.get('/customer-packages', { params: { ...params, all: 1 } }));
    },
    async assign(payload: { customer_id: number; package_id: number; price?: number | null; purchased_at?: string | null }): Promise<CustomerPackage> {
        return unwrap<CustomerPackage>(await http.post('/customer-packages', payload));
    },
};
