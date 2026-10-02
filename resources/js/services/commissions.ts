import http from '@/lib/http';
import type { Paginated } from '@/types';

export interface Commission {
    id: number;
    employee: string | null;
    employee_id: number;
    service: string | null;
    attendance_id: number | null;
    base_amount: number;
    type: 'percentage' | 'fixed';
    value: number;
    amount: number;
    status: 'pending' | 'paid';
    generated_at: string;
    paid_at: string | null;
    paid_by: string | null;
}

export interface CommissionPage extends Paginated<Commission> {
    totals: { pending: number; paid: number };
}

export interface CommissionRule {
    id: number;
    employee_id: number | null;
    employee: string | null;
    service_id: number | null;
    service: string | null;
    type: 'percentage' | 'fixed';
    value: number;
    is_active: boolean;
}

export const RULE_TYPES = [
    { label: 'Porcentaje', value: 'percentage' },
    { label: 'Monto fijo', value: 'fixed' },
];

const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

export const commissionsApi = {
    async list(params: Record<string, unknown>): Promise<CommissionPage> {
        return unwrap<CommissionPage>(await http.get('/commissions', { params }));
    },
    async pay(ids: number[]): Promise<{ paid: number }> {
        return unwrap<{ paid: number }>(await http.post('/commissions/pay', { ids }));
    },
    async rules(): Promise<CommissionRule[]> {
        return unwrap<CommissionRule[]>(await http.get('/commission-rules'));
    },
    async saveRule(payload: Partial<CommissionRule>, id?: number): Promise<CommissionRule> {
        const res = id ? await http.put(`/commission-rules/${id}`, payload) : await http.post('/commission-rules', payload);
        return unwrap<CommissionRule>(res);
    },
    async removeRule(id: number): Promise<void> {
        await http.delete(`/commission-rules/${id}`);
    },
};
