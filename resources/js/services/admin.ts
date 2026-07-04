import http from '@/lib/http';
import type { Paginated, TableQuery } from '@/types';

export interface ManagedUser {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    avatar_url: string | null;
    is_active: boolean;
    roles: string[];
    last_login_at: string | null;
    created_at: string | null;
}

export interface Role {
    id: number;
    name: string;
    permissions: string[];
    permissions_count: number;
    users_count?: number;
}

export interface PermissionGroup {
    module: string;
    permissions: { name: string; action: string }[];
}

export interface RoleOption {
    id: number;
    name: string;
}

const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

export const usersApi = {
    async list(params: TableQuery): Promise<Paginated<ManagedUser>> {
        return unwrap<Paginated<ManagedUser>>(await http.get('/users', { params }));
    },
    async save(payload: Record<string, unknown>, id?: number): Promise<ManagedUser> {
        const res = id ? await http.put(`/users/${id}`, payload) : await http.post('/users', payload);
        return unwrap<ManagedUser>(res);
    },
    async toggle(id: number): Promise<ManagedUser> {
        return unwrap<ManagedUser>(await http.patch(`/users/${id}/toggle`));
    },
    async changePassword(id: number, password: string, password_confirmation: string): Promise<void> {
        await http.patch(`/users/${id}/password`, { password, password_confirmation });
    },
    async remove(id: number): Promise<void> {
        await http.delete(`/users/${id}`);
    },
};

export const rolesApi = {
    async list(): Promise<Role[]> {
        return unwrap<Role[]>(await http.get('/roles'));
    },
    async options(): Promise<RoleOption[]> {
        return unwrap<RoleOption[]>(await http.get('/roles', { params: { all: 1 } }));
    },
    async permissions(): Promise<PermissionGroup[]> {
        return unwrap<PermissionGroup[]>(await http.get('/roles/permissions'));
    },
    async save(payload: { name: string; permissions: string[] }, id?: number): Promise<Role> {
        const res = id ? await http.put(`/roles/${id}`, payload) : await http.post('/roles', payload);
        return unwrap<Role>(res);
    },
    async remove(id: number): Promise<void> {
        await http.delete(`/roles/${id}`);
    },
};
