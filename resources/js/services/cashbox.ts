import http from '@/lib/http';
import type { Paginated, TableQuery } from '@/types';

export interface CashMovement {
    id: number;
    type: string;
    amount: number;
    reason: string;
    created_at: string | null;
}

export interface CashSession {
    id: number;
    register: string | null;
    user: string | null;
    opening_amount: number;
    cash_sales: number;
    income: number;
    expense: number;
    expected_amount: number | null;
    counted_amount: number | null;
    difference: number | null;
    status: string;
    notes: string | null;
    opened_at: string | null;
    closed_at: string | null;
    movements?: CashMovement[];
}

const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

export const cashboxApi = {
    async current(): Promise<CashSession | null> {
        return unwrap<CashSession | null>(await http.get('/cashbox/current'));
    },
    async open(opening_amount: number): Promise<CashSession> {
        return unwrap<CashSession>(await http.post('/cashbox/open', { opening_amount }));
    },
    async movement(type: 'income' | 'expense', amount: number, reason: string): Promise<CashSession> {
        return unwrap<CashSession>(await http.post('/cashbox/movement', { type, amount, reason }));
    },
    async close(counted_amount: number, notes?: string): Promise<CashSession> {
        return unwrap<CashSession>(await http.post('/cashbox/close', { counted_amount, notes }));
    },
    async history(params: TableQuery): Promise<Paginated<CashSession>> {
        return unwrap<Paginated<CashSession>>(await http.get('/cashbox/history', { params }));
    },
};
