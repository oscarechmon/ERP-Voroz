import http from '@/lib/http';
import type { Paginated, TableQuery } from '@/types';

export interface AuditEntry {
    id: number;
    event: string;
    event_label: string;
    model: string;
    auditable_id: number;
    user: string;
    old_values: Record<string, unknown>;
    new_values: Record<string, unknown>;
    ip_address: string | null;
    user_agent: string | null;
    url: string | null;
    created_at: string | null;
}

export interface AuditFilters {
    events: { value: string; label: string }[];
    models: { value: string; label: string }[];
}

const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

export const auditApi = {
    async list(params: TableQuery): Promise<Paginated<AuditEntry>> {
        return unwrap<Paginated<AuditEntry>>(await http.get('/audit', { params }));
    },
    async filters(): Promise<AuditFilters> {
        return unwrap<AuditFilters>(await http.get('/audit/filters'));
    },
};
