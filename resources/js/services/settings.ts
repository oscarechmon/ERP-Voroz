import http from '@/lib/http';

export interface Company {
    id: number;
    business_name: string;
    trade_name: string | null;
    ruc: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    logo_url: string | null;
    currency: string;
    currency_symbol: string;
    igv_percent: number;
    prices_include_igv: boolean;
    is_active: boolean;
}

export interface Branch {
    id: number;
    name: string;
    code: string | null;
    address: string | null;
    phone: string | null;
    is_main: boolean;
    is_active: boolean;
    warehouses_count?: number;
}

export interface Warehouse {
    id: number;
    branch_id: number;
    name: string;
    code: string | null;
    branch: string | null;
    is_default: boolean;
    is_active: boolean;
}

const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

export const companyApi = {
    async get(): Promise<Company> {
        return unwrap<Company>(await http.get('/settings/company'));
    },
    /** Actualiza la empresa; usa multipart si hay logo (con method spoofing). */
    async update(payload: Partial<Company> & { logo?: File | null }): Promise<Company> {
        const fd = new FormData();
        Object.entries(payload).forEach(([k, v]) => {
            if (v === null || v === undefined) return;
            if (k === 'logo' && v instanceof File) fd.append('logo', v);
            else if (k !== 'logo') fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : String(v));
        });
        fd.append('_method', 'PUT');
        return unwrap<Company>(await http.post('/settings/company', fd));
    },
};

export const branchesApi = {
    async list(): Promise<Branch[]> {
        return unwrap<Branch[]>(await http.get('/settings/branches'));
    },
    async save(payload: Partial<Branch>, id?: number): Promise<Branch> {
        const res = id ? await http.put(`/settings/branches/${id}`, payload) : await http.post('/settings/branches', payload);
        return unwrap<Branch>(res);
    },
    async remove(id: number): Promise<void> {
        await http.delete(`/settings/branches/${id}`);
    },
};

export const warehousesApi = {
    async list(): Promise<Warehouse[]> {
        return unwrap<Warehouse[]>(await http.get('/warehouses'));
    },
    async save(payload: Partial<Warehouse>, id?: number): Promise<Warehouse> {
        const res = id ? await http.put(`/settings/warehouses/${id}`, payload) : await http.post('/settings/warehouses', payload);
        return unwrap<Warehouse>(res);
    },
    async remove(id: number): Promise<void> {
        await http.delete(`/settings/warehouses/${id}`);
    },
};
