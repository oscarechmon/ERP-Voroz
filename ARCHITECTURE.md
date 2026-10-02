# SISTEMA ERP — Arquitectura del Sistema

Sistema de ventas / ERP modular, multiempresa-ready, para el mercado peruano
(DNI/RUC, IGV, Yape/Plin, comprobantes internos boleta/factura en PDF).

---

## 1. Stack tecnológico

| Capa        | Tecnología |
|-------------|-----------|
| Runtime     | PHP 8.2+ (objetivo 8.4), Node 22 |
| Backend     | Laravel 12, MySQL/MariaDB |
| Auth        | Laravel Sanctum (SPA cookie) + Breeze (scaffolding) |
| ACL         | spatie/laravel-permission |
| Exportación | maatwebsite/excel, barryvdh/laravel-dompdf |
| Imágenes    | intervention/image |
| Códigos     | picqer/php-barcode-generator, milon/barcode (EAN13/CODE128), endroid/qr-code |
| Auditoría   | owen-it/laravel-auditing |
| Búsqueda    | laravel/scout (preparado, driver database → futuro Meilisearch) |
| Frontend    | Vue 3 + TypeScript + Vite |
| Estado      | Pinia |
| UI          | PrimeVue + TailwindCSS |
| Gráficos    | ApexCharts (vue3-apexcharts) |
| Router      | Vue Router 4 |
| HTTP        | Axios (interceptores CSRF + auth) |

SPA pura. Blade sólo para `resources/views/app.blade.php` (punto de entrada).

---

## 2. Principios de arquitectura

- **Clean Architecture por capas** dentro de cada módulo:
  `Controller → Request(validación) → Service(lógica) → Repository(persistencia) → Model`.
- **SOLID + DI**: cada Repository y Service tiene su **Interface**, registrada en un
  `ModuleServiceProvider`. Los controllers dependen de interfaces, no de implementaciones.
- **DTOs** para transportar datos entre capas (sin exponer requests/arrays sueltos).
- **Resources** (JsonResource) para todas las respuestas de API.
- **Policies + Gates** por modelo; permisos granulares con Spatie.
- **Events → Listeners → Queues/Jobs** para efectos secundarios (recalcular stock,
  registrar kardex, auditar, enviar notificaciones, generar PDFs pesados).
- **Repository Pattern** aísla Eloquent; facilita testeo y futuro cambio de fuente.

---

## 3. Estructura de directorios (backend modular)

```
app/
  Core/                     # Contratos y clases base compartidas
    Repositories/BaseRepository.php + Contracts/RepositoryInterface.php
    Services/BaseService.php
    DTO/BaseDTO.php
    Traits/{ApiResponse, HasUuidRoute, Filterable}.php
    Exceptions/BusinessException.php

Modules/
  <Modulo>/
    Config/                 # config del módulo
    Providers/              # <Modulo>ServiceProvider (bindings)
    Http/
      Controllers/Api/
      Requests/
      Resources/
    Services/               # + Contracts/
    Repositories/           # + Contracts/
    DTO/
    Models/
    Policies/
    Events/  Listeners/  Jobs/
    Database/Migrations|Factories|Seeders/
    routes/api.php
    Tests/{Unit,Feature}/
```

Módulos: `Auth`, `Users`, `Roles`, `Catalog`(productos/categorías/marcas/unidades),
`Contacts`(clientes/proveedores), `Inventory`(almacenes/stock/kardex),
`Sales`(POS/ventas/comprobantes), `Purchases`, `Cashbox`(caja), `Reports`,
`Dashboard`, `Settings`, `Audit`.

Carga: cada `ModuleServiceProvider` registra rutas, migraciones, policies y bindings.
Un `Modules/ModuleServiceProvider` raíz los auto-descubre.

---

## 4. Modelo de base de datos (núcleo)

Todas las tablas: `id` BIGINT, `timestamps`, `SoftDeletes` donde aplica, FKs con
índices, `company_id`/`branch_id` en tablas transaccionales (multiempresa-ready).

**Identidad y acceso**
- `companies` (empresa: razón social, RUC, logo, moneda, IGV%, dirección)
- `branches` (sucursales) · `warehouses` (almacenes, FK branch)
- `users` (+ avatar, phone, is_active, last_login_at, company_id, branch_id)
- `roles`, `permissions`, `model_has_roles`, `role_has_permissions` (Spatie)
- `audits` (laravel-auditing: user, event, auditable, old_values, new_values, ip, ua, url)

**Catálogo**
- `categories` (self-parent → subcategorías, tree) · `brands` · `units`
- `products` (code, barcode, sku, name, description, brand_id, category_id,
  cost, price, wholesale_price, offer_price, stock_min, stock_max, unit_id,
  image, qr_path, status, has_expiry)
- `product_prices` (histórico de precios) · `product_barcodes` (multi-barcode)

**Contactos**
- `customers` (doc_type[DNI/RUC/CE], doc_number, name, address, email, phone, notes)
- `suppliers` (doc_type, doc_number, name, address, email, phone, notes)

**Inventario**
- `stocks` (product_id, warehouse_id, quantity, avg_cost) — único por (product,warehouse)
- `inventory_movements` (type[in/out/adjust/transfer], product_id, warehouse_id,
  quantity, cost, balance, reference_type, reference_id, user_id) → **Kardex**
- `stock_transfers` (from_wh, to_wh, status) + `stock_transfer_items`

**Ventas**
- `sales` (series, number, doc_type[ticket/boleta/factura/cotizacion], customer_id,
  user_id, warehouse_id, subtotal, tax(IGV), discount, total, status, notes, sold_at)
- `sale_items` (sale_id, product_id, qty, price, discount, tax, subtotal)
- `sale_payments` (sale_id, method[efectivo/yape/plin/transfer/tarjeta], amount) → pago mixto
- `document_series` (doc_type, series, current_number) → correlativos por sucursal

**Compras**
- `purchases` (supplier_id, warehouse_id, number, subtotal, tax, total, status)
- `purchase_items` (updates avg_cost + stock vía evento)

**Caja**
- `cash_registers` (branch, name) · `cash_sessions` (open/close, opening_amount,
  closing_amount, user_id) · `cash_movements` (income/expense, amount, reason)

**Configuración**
- `settings` (key/value por company) · `notifications` (Laravel default)

Relaciones clave: Product↔Stock(1:N por almacén), Sale→SaleItems(1:N)→Product,
Sale→SalePayments(1:N), Product↔InventoryMovements(1:N), Category self-referencing.

---

## 5. Mapa de módulos

```
                         ┌───────────────┐
                         │   Dashboard   │  (agrega KPIs de todos)
                         └──────┬────────┘
   Auth/Users/Roles ── Settings │ ── Audit (transversal, escucha eventos)
        │                       │
        ▼                       ▼
   ┌─────────┐   ┌──────────┐   ┌───────────┐   ┌──────────┐
   │ Catalog │──▶│ Inventory│◀──│ Purchases │   │ Contacts │
   └────┬────┘   └────┬─────┘   └───────────┘   └────┬─────┘
        │             │  ▲                            │
        └─────────────┼──┴──────── Sales (POS) ◀──────┘
                      │              │
                   Kardex         Cashbox
                      └────► Reports ◀────┘
```

---

## 6. Flujo de navegación (SPA)

```
/login  →  /  (Dashboard)
Sidebar:
  Dashboard
  Ventas ▸ POS · Historial · Cotizaciones · Comprobantes
  Compras ▸ Registrar · Historial
  Inventario ▸ Productos · Categorías · Marcas · Stock · Kardex · Transferencias · Ajustes
  Contactos ▸ Clientes · Proveedores
  Caja ▸ Sesión actual · Movimientos · Historial
  Reportes ▸ Ventas · Compras · Utilidad · Stock · Kardex · Vendedores
  Configuración ▸ Empresa · Sucursales · Almacenes · Series · Usuarios · Roles · Permisos
  Auditoría
```
Cada ruta protegida por `meta.permission`; guard global en Vue Router valida contra
los permisos del usuario cargados en el store Pinia.

---

## 7. Matriz de permisos

Patrón `modulo.accion`: `view, create, edit, delete, export, import, print`.
Módulos con permisos: products, categories, brands, units, customers, suppliers,
stock, inventory, sales, purchases, cashbox, reports, settings, users, roles, dashboard, audit.

Roles semilla: **Super Administrador** (todos), **Administrador**, **Supervisor**,
**Ventas**, **Logística**, **Caja**, **Invitado**. El sistema permite crear roles nuevos.

---

## 8. API REST

- Prefijo `/api/v1`, auth vía Sanctum (cookie SPA) + rate limiting.
- Recursos RESTful por módulo (`apiResource`), respuestas con JsonResource + meta paginación.
- Endpoints especiales: `POST /sales` (POS checkout transaccional),
  `GET /products/scan/{barcode}`, `GET /dashboard/metrics`, `GET /reports/{tipo}?export=xlsx|pdf`.
- Validación con Form Requests + mensajes personalizados (es).

---

## 9. Seguridad y rendimiento

Seguridad: CSRF (Sanctum), Policies/Gates, sanitización de inputs, Form Requests,
rate limiting por ruta, logging de acciones (Audit), SoftDeletes, hashing.

Rendimiento: eager loading, paginación server-side, índices, cache de settings y
métricas de dashboard (TTL corto), colas para jobs pesados (PDF, kardex, notificaciones),
lazy-loading de rutas y componentes en el frontend.

---

## 10. Plan de implementación por fases

| Fase | Entregable |
|------|-----------|
| 0 | Bootstrap: Laravel 12, paquetes, estructura `Modules/`, `Core/` base clases |
| 1 | Frontend SPA shell: Vite+TS+Pinia+PrimeVue+Tailwind, layout, dark mode, axios |
| 2 | Migraciones + Modelos + Relaciones + Factories + Seeders base |
| 3 | Auth (Sanctum/Breeze) + Users + Roles + Permissions + Audit |
| 4 | Catálogo: categorías, marcas, unidades, productos, código de barras/QR |
| 5 | Contactos: clientes, proveedores + historial |
| 6 | Inventario: almacenes, stock, movimientos, kardex, transferencias, alertas |
| 7 | Ventas POS + comprobantes (ticket/boleta/factura/cotización) + pagos mixtos |
| 8 | Compras + actualización de stock y costo promedio |
| 9 | Caja: sesiones, movimientos, arqueo |
| 10 | Reportes + exportación Excel/PDF/CSV |
| 11 | Dashboard: KPIs + gráficos ApexCharts |
| 12 | Configuración + Notificaciones + pulido UX (skeletons, toasts, confirm) |
| 13 | Tests (Unit/Feature), seeders demo completos, README de despliegue |

Cada fase entrega backend + frontend + tests del módulo, ejecutable y verificable.
```
