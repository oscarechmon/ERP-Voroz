import http, { apiUrl } from '@/lib/http';
import type { Paginated, TableQuery } from '@/types';

/** Tipos del dominio Catálogo. Un `package` lo crea el módulo Paquetes (no se edita aquí). */
export type ProductType = 'product' | 'service' | 'package';

/** Producto con stock o servicio (sin stock); los servicios también llegan de la web. */
export const PRODUCT_TYPES: { label: string; value: ProductType }[] = [
    { label: 'Producto', value: 'product' },
    { label: 'Servicio', value: 'service' },
];

export interface Product {
    id: number;
    type: ProductType;
    code: string;
    barcode: string | null;
    sku: string | null;
    name: string;
    description: string | null;
    image_url: string | null;
    category_id: number | null;
    brand_id: number | null;
    unit_id: number | null;
    category?: { id: number; name: string };
    brand?: { id: number; name: string };
    unit?: { id: number; name: string; abbreviation: string };
    cost: number;
    price: number;
    wholesale_price: number | null;
    offer_price: number | null;
    profit_margin: number;
    current_stock: number;
    stock_min: number;
    stock_max: number | null;
    track_stock: boolean;
    has_expiry: boolean;
    is_active: boolean;
    created_at: string | null;
}

/** Datos de la etiqueta imprimible de un producto (código de barras + QR). */
export interface ProductLabel {
    name: string;
    code: string;
    price: number;
    barcode_png: string;
    qr_png: string;
}

export interface Category {
    id: number;
    parent_id: number | null;
    name: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
}

export interface Brand {
    id: number;
    name: string;
    is_active: boolean;
}

export interface Unit {
    id: number;
    name: string;
    abbreviation: string;
    is_active: boolean;
}

export interface Option {
    id: number;
    name: string;
    abbreviation?: string;
}

/** Extrae `data` de la envoltura estándar { success, message, data }. */
const unwrap = <T>(res: { data: { data: T } }): T => res.data.data;

export const productsApi = {
    async list(params: TableQuery): Promise<Paginated<Product>> {
        return unwrap<Paginated<Product>>(await http.get('/products', { params }));
    },
    async get(id: number): Promise<Product> {
        return unwrap<Product>(await http.get(`/products/${id}`));
    },
    /** Crea/actualiza soportando imagen: usa multipart cuando hay archivo. */
    async save(payload: Partial<Product> & { image?: File | null }, id?: number): Promise<Product> {
        const hasFile = payload.image instanceof File;
        let body: FormData | Record<string, unknown> = { ...payload };
        const config: Record<string, unknown> = {};

        if (hasFile) {
            const fd = new FormData();
            Object.entries(payload).forEach(([k, v]) => {
                if (v === null || v === undefined) return;
                if (k === 'image' && v instanceof File) fd.append('image', v);
                else fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : String(v));
            });
            if (id) fd.append('_method', 'PUT'); // method spoofing para multipart
            body = fd;
        }

        const url = id ? `/products/${id}` : '/products';
        const res = id && !hasFile ? await http.put(url, body, config) : await http.post(url, body, config);
        return unwrap<Product>(res);
    },
    async remove(id: number): Promise<void> {
        await http.delete(`/products/${id}`);
    },
    async bulkRemove(ids: number[]): Promise<void> {
        await http.post('/products/bulk-destroy', { ids });
    },
    async scan(barcode: string): Promise<Product> {
        return unwrap<Product>(await http.get(`/products/scan/${encodeURIComponent(barcode)}`));
    },
    /** Etiqueta imprimible (código de barras + QR) de un producto. */
    async label(id: number): Promise<ProductLabel> {
        return unwrap<ProductLabel>(await http.get(`/products/${id}/label`));
    },
    /** URL de exportación del catálogo (se abre en nueva pestaña; usa la sesión activa). */
    exportUrl(format: 'xlsx' | 'csv', params: Record<string, unknown> = {}): string {
        const qs = new URLSearchParams({ export: format });
        Object.entries(params).forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '') qs.append(k, String(v));
        });
        return apiUrl(`/products/export?${qs.toString()}`);
    },
};

/** Genérico para catálogos simples (categorías, marcas, unidades). */
function crud<T>(resource: string) {
    return {
        async list(params: TableQuery): Promise<Paginated<T>> {
            return unwrap<Paginated<T>>(await http.get(`/${resource}`, { params }));
        },
        async options(): Promise<Option[]> {
            return unwrap<Option[]>(await http.get(`/${resource}`, { params: { all: 1 } }));
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

export const categoriesApi = crud<Category>('categories');
export const brandsApi = crud<Brand>('brands');
export const unitsApi = crud<Unit>('units');
