import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { basePath } from '@/lib/basePath';

/**
 * Rutas de la SPA. Cada ruta protegida declara `meta.requiresAuth` y opcionalmente
 * `meta.permission`. Las vistas se cargan de forma perezosa (code-splitting) para
 * que la app se sienta rápida y sólo descargue lo necesario.
 */
const routes: RouteRecordRaw[] = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/pages/auth/Login.vue'),
        meta: { layout: 'auth', guestOnly: true },
    },
    {
        path: '/',
        component: () => import('@/layouts/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                name: 'dashboard',
                component: () => import('@/pages/Dashboard.vue'),
                meta: { title: 'Dashboard', permission: 'dashboard.view' },
            },
            {
                path: 'pos',
                name: 'pos',
                component: () => import('@/pages/sales/Pos.vue'),
                meta: { title: 'Punto de venta', permission: 'sales.create' },
            },
            {
                path: 'sales',
                name: 'sales',
                component: () => import('@/pages/sales/Sales.vue'),
                meta: { title: 'Ventas', permission: 'sales.view' },
            },
            {
                path: 'agenda',
                name: 'agenda',
                component: () => import('@/pages/agenda/Agenda.vue'),
                meta: { title: 'Agenda', permission: 'appointments.view' },
            },
            {
                path: 'attendances',
                name: 'attendances',
                component: () => import('@/pages/attendances/Attendances.vue'),
                meta: { title: 'Atenciones', permission: 'attendances.view' },
            },
            {
                path: 'packages',
                name: 'packages',
                component: () => import('@/pages/packages/Packages.vue'),
                meta: { title: 'Paquetes', permission: 'packages.view' },
            },
            {
                path: 'employees',
                name: 'employees',
                component: () => import('@/pages/staff/Employees.vue'),
                meta: { title: 'Personal', permission: 'employees.view' },
            },
            {
                path: 'commissions',
                name: 'commissions',
                component: () => import('@/pages/commissions/Commissions.vue'),
                meta: { title: 'Comisiones', permission: 'commissions.view' },
            },
            {
                path: 'online-orders',
                name: 'online-orders',
                component: () => import('@/pages/online-orders/OnlineOrders.vue'),
                meta: { title: 'Pedidos online', permission: 'online_orders.view' },
            },
            {
                path: 'products',
                name: 'products',
                component: () => import('@/pages/catalog/Products.vue'),
                meta: { title: 'Productos y servicios', permission: 'products.view' },
            },
            {
                path: 'categories',
                name: 'categories',
                component: () => import('@/pages/catalog/Categories.vue'),
                meta: { title: 'Categorías', permission: 'categories.view' },
            },
            {
                path: 'brands',
                name: 'brands',
                component: () => import('@/pages/catalog/Brands.vue'),
                meta: { title: 'Marcas', permission: 'brands.view' },
            },
            {
                path: 'customers',
                name: 'customers',
                component: () => import('@/pages/contacts/Customers.vue'),
                meta: { title: 'Clientes', permission: 'customers.view' },
            },
            {
                path: 'suppliers',
                name: 'suppliers',
                component: () => import('@/pages/contacts/Suppliers.vue'),
                meta: { title: 'Proveedores', permission: 'suppliers.view' },
            },
            {
                path: 'purchases',
                name: 'purchases',
                component: () => import('@/pages/purchases/Purchases.vue'),
                meta: { title: 'Compras', permission: 'purchases.view' },
            },
            {
                path: 'purchases/create',
                name: 'purchases.create',
                component: () => import('@/pages/purchases/PurchaseCreate.vue'),
                meta: { title: 'Registrar compra', permission: 'purchases.create' },
            },
            {
                path: 'purchases/:id/edit',
                name: 'purchases.edit',
                component: () => import('@/pages/purchases/PurchaseCreate.vue'),
                meta: { title: 'Editar compra', permission: 'purchases.edit' },
            },
            {
                path: 'cashbox',
                name: 'cashbox',
                component: () => import('@/pages/cashbox/Cashbox.vue'),
                meta: { title: 'Caja', permission: 'cashbox.view' },
            },
            {
                path: 'cashbox/history',
                name: 'cashbox.history',
                component: () => import('@/pages/cashbox/CashboxHistory.vue'),
                meta: { title: 'Historial de caja', permission: 'cashbox.view' },
            },
            {
                path: 'stock',
                name: 'stock',
                component: () => import('@/pages/inventory/Stock.vue'),
                meta: { title: 'Stock', permission: 'stock.view' },
            },
            {
                path: 'kardex',
                name: 'kardex',
                component: () => import('@/pages/inventory/Kardex.vue'),
                meta: { title: 'Kardex', permission: 'inventory.view' },
            },
            {
                path: 'reports',
                name: 'reports',
                component: () => import('@/pages/Reports.vue'),
                meta: { title: 'Reportes', permission: 'reports.view' },
            },
            {
                path: 'users',
                name: 'users',
                component: () => import('@/pages/admin/Users.vue'),
                meta: { title: 'Usuarios', permission: 'users.view' },
            },
            {
                path: 'roles',
                name: 'roles',
                component: () => import('@/pages/admin/Roles.vue'),
                meta: { title: 'Roles y permisos', permission: 'roles.view' },
            },
            {
                path: 'audit',
                name: 'audit',
                component: () => import('@/pages/admin/Audit.vue'),
                meta: { title: 'Auditoría', permission: 'audit.view' },
            },
            {
                path: 'settings/company',
                name: 'settings.company',
                component: () => import('@/pages/settings/Company.vue'),
                meta: { title: 'Empresa', permission: 'settings.view' },
            },
            {
                path: 'settings/branches',
                name: 'settings.branches',
                component: () => import('@/pages/settings/Branches.vue'),
                meta: { title: 'Sucursales', permission: 'settings.view' },
            },
            {
                path: 'settings/warehouses',
                name: 'settings.warehouses',
                component: () => import('@/pages/settings/Warehouses.vue'),
                meta: { title: 'Almacenes', permission: 'settings.view' },
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/pages/NotFound.vue'),
        meta: { layout: 'blank' },
    },
];

const router = createRouter({
    history: createWebHistory(basePath),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

// Guard global: autenticación + autorización por permiso.
router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (!auth.ready) {
        await auth.bootstrap();
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    const permission = to.meta.permission as string | undefined;
    if (permission && !auth.can(permission)) {
        return { name: 'dashboard' };
    }

    return true;
});

export default router;
