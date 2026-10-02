/**
 * Definición del menú lateral del ERP.
 * Cada ítem declara el permiso requerido; el sidebar y el guard del router lo
 * usan para mostrar/ocultar y proteger las rutas (fuente única de verdad).
 */
export interface MenuItem {
    label: string;
    icon: string;
    to?: string;
    permission?: string;
    children?: MenuItem[];
}

export const menu: MenuItem[] = [
    { label: 'Dashboard', icon: 'pi pi-th-large', to: '/', permission: 'dashboard.view' },
    {
        label: 'Ventas',
        icon: 'pi pi-shopping-cart',
        children: [
            { label: 'Punto de venta', icon: 'pi pi-desktop', to: '/pos', permission: 'sales.create' },
            { label: 'Historial', icon: 'pi pi-list', to: '/sales', permission: 'sales.view' },
        ],
    },
    {
        label: 'Compras',
        icon: 'pi pi-truck',
        children: [
            { label: 'Registrar', icon: 'pi pi-plus-circle', to: '/purchases/create', permission: 'purchases.create' },
            { label: 'Historial', icon: 'pi pi-list', to: '/purchases', permission: 'purchases.view' },
        ],
    },
    {
        label: 'Inventario',
        icon: 'pi pi-box',
        children: [
            { label: 'Productos y servicios', icon: 'pi pi-tag', to: '/products', permission: 'products.view' },
            { label: 'Categorías', icon: 'pi pi-sitemap', to: '/categories', permission: 'categories.view' },
            { label: 'Marcas', icon: 'pi pi-bookmark', to: '/brands', permission: 'brands.view' },
            { label: 'Stock', icon: 'pi pi-database', to: '/stock', permission: 'stock.view' },
            { label: 'Kardex', icon: 'pi pi-book', to: '/kardex', permission: 'inventory.view' },
        ],
    },
    {
        label: 'Contactos',
        icon: 'pi pi-users',
        children: [
            { label: 'Clientes', icon: 'pi pi-user', to: '/customers', permission: 'customers.view' },
            { label: 'Proveedores', icon: 'pi pi-building', to: '/suppliers', permission: 'suppliers.view' },
        ],
    },
    {
        label: 'Caja',
        icon: 'pi pi-wallet',
        children: [
            { label: 'Sesión actual', icon: 'pi pi-clock', to: '/cashbox', permission: 'cashbox.view' },
            { label: 'Historial', icon: 'pi pi-history', to: '/cashbox/history', permission: 'cashbox.view' },
        ],
    },
    { label: 'Reportes', icon: 'pi pi-chart-bar', to: '/reports', permission: 'reports.view' },
    {
        label: 'Configuración',
        icon: 'pi pi-cog',
        children: [
            { label: 'Empresa', icon: 'pi pi-building', to: '/settings/company', permission: 'settings.view' },
            { label: 'Sucursales', icon: 'pi pi-map-marker', to: '/settings/branches', permission: 'settings.view' },
            { label: 'Almacenes', icon: 'pi pi-warehouse', to: '/settings/warehouses', permission: 'settings.view' },
            { label: 'Usuarios', icon: 'pi pi-id-card', to: '/users', permission: 'users.view' },
            { label: 'Roles y permisos', icon: 'pi pi-shield', to: '/roles', permission: 'roles.view' },
        ],
    },
    { label: 'Auditoría', icon: 'pi pi-eye', to: '/audit', permission: 'audit.view' },
];
