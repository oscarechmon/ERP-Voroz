/** Tipos compartidos del dominio en el frontend. */

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    avatar_url: string | null;
    phone: string | null;
    is_active: boolean;
    roles: string[];
    permissions: string[];
}

export interface ApiEnvelope<T> {
    success: boolean;
    message: string;
    data: T;
}

export interface Paginated<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
}

export interface TableQuery {
    search?: string;
    page?: number;
    per_page?: number;
    sort_by?: string;
    sort_dir?: 'asc' | 'desc';
    [key: string]: unknown;
}
