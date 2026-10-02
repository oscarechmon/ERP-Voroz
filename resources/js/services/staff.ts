import http from '@/lib/http';
import type { Paginated, TableQuery } from '@/types';

export interface Employee {
    id: number;
    name: string;
    position: string | null;
    phone: string | null;
    doc_number: string | null;
    is_active: boolean;
    user: { id: number; name: string; email: string } | null;
    services: { id: number; name: string }[];
}

export interface EmployeePayload {
    name: string;
    position?: string | null;
    phone?: string | null;
    doc_number?: string | null;
    user_id?: number | null;
    is_active: boolean;
    service_ids: number[];
}

const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

export const employeesApi = {
    async list(params: TableQuery): Promise<Paginated<Employee>> {
        return unwrap<Paginated<Employee>>(await http.get('/employees', { params }));
    },
    /** Activos, para los selectores. Con `serviceId`, solo quienes atienden ese servicio. */
    async options(serviceId?: number | null): Promise<Employee[]> {
        return unwrap<Employee[]>(await http.get('/employees', { params: { all: 1, service_id: serviceId ?? undefined } }));
    },
    async save(payload: EmployeePayload, id?: number): Promise<Employee> {
        const res = id ? await http.put(`/employees/${id}`, payload) : await http.post('/employees', payload);
        return unwrap<Employee>(res);
    },
    async remove(id: number): Promise<void> {
        await http.delete(`/employees/${id}`);
    },
};
