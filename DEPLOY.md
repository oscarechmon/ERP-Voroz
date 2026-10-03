# Despliegue en Hostinger (sistema.sinexcusas.org.pe)

El sistema se sirve en el **subdominio `sistema.sinexcusas.org.pe`**, cuya
carpeta es `public_html/sistema` dentro del hosting de sin_excusas. Son dos
proyectos separados: cada uno con su repositorio, su base de datos, sus
usuarios y su despliegue.

Cada `git push origin main` dispara `.github/workflows/deploy.yml`, que compila
el proyecto, lo empaqueta en **un solo `release.zip`**, lo sube por FTP a
`public_html/sistema` y le pide al servidor que lo descomprima, migre y regenere
las cachés. Al final comprueba que `/login` responde y que Laravel se reconoce
en la raíz del subdominio.

**Por qué un zip.** Subir el proyecto archivo por archivo son miles de
transferencias FTP (`vendor/` es casi todo eso) y Hostinger cierra la sesión a
los 3600 segundos con `421 Session Timeout`. Un único archivo tarda un par de
minutos.

**Lo que el zip no toca:** el `.env` del servidor, las imágenes subidas
(`storage/app/public`), los logs y las sesiones. La lista está en
[`.deployignore`](.deployignore).

**Ojo:** publicar sobrescribe y agrega, pero no borra. Si eliminas un archivo
del repositorio, sigue existiendo en el servidor hasta que lo borres a mano.

## Estructura en el servidor

```
/home/u367943235/domains/sinexcusas.org.pe/public_html/
├── .htaccess  app/  public/ …   ← sin_excusas (sinexcusas.org.pe)
└── sistema/                     ← raíz del subdominio; aquí se descomprime
    ├── .htaccess                ← manda todo a sistema/public/
    ├── .env                     ← se crea a mano, nunca se pisa
    ├── app/  Modules/  vendor/  storage/ …
    └── public/                  ← lo único que se sirve
```

- El [`.htaccess`](.htaccess) de la raíz manda cada petición a `public/`, así
  el `.env`, el código y el `release.zip` quedan fuera de alcance. Viaja en
  cada despliegue; si se pierde, el `.env` queda a la vista.
- Como la carpeta cuelga de `public_html`, también se llega a ella por
  `sinexcusas.org.pe/sistema`. El mismo `.htaccess` redirige esas visitas al
  subdominio.
- **Base de datos propia, siempre.** Los dos proyectos tienen tablas `users`,
  `roles`, `permissions`, `sales`… con los mismos nombres y distinto contenido.
- Al ser otro host, las cookies y el `localStorage` no se mezclan con los de la
  web.

## Secretos en GitHub (Settings → Secrets and variables → Actions)

En el repositorio **de este proyecto**, no en el de sin_excusas. Los cinco son
obligatorios; sin alguno, el workflow falla en el primer paso con el nombre del
que falta.

| Secreto        | Valor                                                    |
|----------------|----------------------------------------------------------|
| `FTP_SERVER`   | `br-asc-web1445.hstgr.io` (el mismo que sin_excusas)     |
| `FTP_USERNAME` | `u367943235.sistema`                                     |
| `FTP_PASSWORD` | la contraseña de esa cuenta FTP                          |
| `DEPLOY_URL`   | `https://sistema.sinexcusas.org.pe`                      |
| `DEPLOY_TOKEN` | una cadena larga al azar, **distinta** de la de sin_excusas e igual a la del `.env` del sistema |

**El nombre del servidor, no `ftp.sinexcusas.org.pe` ni la IP.** El dominio
resuelve al CDN de Hostinger, que no habla FTP, y con la IP el certificado no
valida (está emitido para `*.hstgr.io`). Sale del banner de SSH
(`u367943235@br-asc-web1445`) más `.hstgr.io`.

La cuenta `u367943235.sistema` apunta a `public_html/sistema`, por eso el
workflow sube a la raíz de la cuenta (`FTP_DIR: ''`). Además, con ella este
repositorio no puede tocar los archivos de sin_excusas.

## Puesta en marcha (una sola vez)

La ruta que descomprime vive dentro de la propia aplicación, así que el primer
despliegue hay que sembrarlo a mano:

1. hPanel → **Bases de datos → Administración**: crea una base de datos y un
   usuario nuevos para el sistema (por ejemplo `u367943235_sistema`). No uses
   la de sin_excusas.
2. Carga los cinco secretos y lanza el workflow (**Actions → Deploy → Run
   workflow**). Subirá `public_html/sistema/release.zip` y fallará al publicar:
   todavía no hay código que lo atienda.
3. hPanel → **Administrador de archivos** → entra a `public_html/sistema`,
   borra la página por defecto de Hostinger (`default.php` o `index.php`),
   selecciona `release.zip` y usa **Extraer** (en esa misma carpeta).
4. Crea el `.env` en `public_html/sistema` a partir de `.env.example` (ver abajo).
   El `.env.example` no viaja en el zip (`.deployignore` excluye `/.env.*`):
   cópialo del repositorio o escribe el `.env` directamente.
5. hPanel → **Avanzado → Terminal SSH**:

   ```bash
   cd ~/domains/sinexcusas.org.pe/public_html/sistema
   php artisan key:generate --force    # solo si el .env no trae APP_KEY
   php artisan migrate --force
   php artisan db:seed --class="Modules\Users\Database\Seeders\RolePermissionSeeder" --force
   php artisan db:seed --class="Modules\Settings\Database\Seeders\CompanySeeder" --force
   php artisan db:seed --class="Modules\Users\Database\Seeders\UserSeeder" --force
   php artisan storage:link
   php artisan optimize
   ```

   No uses `db:seed` a secas: el `DatabaseSeeder` carga productos, clientes,
   stock y ventas de demostración. `UserSeeder` crea seis usuarios con la
   contraseña `password`: entra con `admin@sistema.test`, cámbiale correo y
   contraseña de inmediato y elimina los demás.

6. Borra el `release.zip` que quedó y vuelve a lanzar el workflow: a partir de
   aquí todo es automático.

### Clientes y ventas del ERP anterior (voroz)

Con el sistema ya en marcha (catálogo vinculado con la web), se traen desde el
volcado SQL de la base vieja, exportado con su phpMyAdmin:

1. hPanel → Administrador de archivos: sube el `.sql` a
   `public_html/sistema/storage/app/private/` (fuera de lo que se publica).
2. Por SSH, en `public_html/sistema`, primero simula y luego importa:

   ```bash
   php artisan voroz:importar storage/app/private/u257283941_voroz.sql --simular
   php artisan voroz:importar storage/app/private/u257283941_voroz.sql
   ```

3. Borra el `.sql` del servidor: tiene datos personales. **Nunca lo subas al
   repositorio** (es público).

Los clientes se enlazan con los que ya existen (por DNI o correo) y solo se
completan sus datos vacíos; los demás se crean. Las ventas entran como fueron
(fecha, comprobante, anulaciones, pagos) **sin mover stock**, y sus productos se
enlazan con los de aquí (la lista está en `VorozImporter::PRODUCTS`). La serie
T001/B001 sigue desde la última venta importada. Se puede repetir sin duplicar.

### El `.env` del servidor

Parte de `.env.example` y cambia esto:

```env
APP_NAME=Sistema
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sistema.sinexcusas.org.pe

DB_DATABASE=u367943235_sistema
DB_USERNAME=u367943235_sistema
DB_PASSWORD=<la de la base nueva>

SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=sistema.sinexcusas.org.pe

DEPLOY_TOKEN=<la misma cadena que el secreto de GitHub>
```

- `APP_URL`: con él se arman las URLs de las imágenes.
- `SESSION_DOMAIN=null`: la cookie de sesión queda solo para el subdominio y
  no viaja a la web.
- `SANCTUM_STATEFUL_DOMAINS` es lo que hace que la API acepte la sesión del
  navegador; sin el subdominio, el login "funciona" y vuelve a pedir
  credenciales.

Con config en caché Laravel deja de leer el `.env`, así que **cada cambio del
`.env` en el servidor exige volver a cachear**: `php artisan optimize` por SSH,
o un push nuevo.

## Conexión con la web (sin_excusas)

El sistema es **el único lugar donde se opera el centro**:

- **Centro:** Agenda, Atenciones (consumen la sesión del paquete, sacan los
  insumos del stock y generan la comisión), Paquetes, Personal y Comisiones.
- **Ventas:** POS (también servicios y paquetes; un paquete exige cliente y le
  crea su saldo de sesiones), ventas con **saldo pendiente** que se cobran
  después (Historial → Cobrar saldo), y **Pedidos online** con su seguimiento.
- **Clientes** con su ficha completa (datos personales y antecedentes).
- Catálogo y stock: productos, insumos, servicios (con los insumos que usa cada
  uno) y paquetes; compras, ajustes, kardex.
- **Lo que muestra la web:** en cada producto, servicio o paquete, la foto, la
  descripción, **Publicar en la web** y, en un servicio, su duración. La web es
  un cascarón: lee todo eso de aquí por la API y muestra la foto desde aquí
  (por eso este sistema necesita `storage:link` y un `APP_URL` correcto).

La web conserva solo su contenido (textos y fotos de sus páginas), los datos
del sitio y la tienda (carrito, cobro con Izipay, "Mis pedidos").

| Qué pasa | Cómo viaja |
|---|---|
| Cambias un producto, servicio, paquete, su stock, su foto o si se publica | El sistema avisa a la web al instante (`POST /erp/catalogo`) y la web lo vuelve a leer |
| Pasas lo que la web tenía (una vez, `php artisan erp:fichas` en la web) | La web manda aquí fotos, descripciones y lo publicado (`POST /integration/products/{id}/web`) |
| Alguien crea una cuenta en la tienda | La web lo enlaza con su ficha de aquí (la crea si no existe) |
| Se crea o se cobra un pedido en la tienda | La web lo manda aquí (`POST /integration/orders`); cobrado, se registra como **venta del canal web** y descuenta stock |
| Mueves un pedido online (preparar, enviar, entregar, anular) | Se avisa a la web (`POST /erp/pedidos/{código}/estado`) y el cliente lo ve; anular un pedido cobrado anula su venta y devuelve el stock |

Todo usa el **almacén por defecto** (Configuración → Almacenes): su stock es el
que ve la tienda online y de él salen los insumos de las atenciones.

Se activa con dos líneas en el `.env` del sistema:

```env
INTEGRATION_TOKEN=<cadena larga al azar, la misma que ERP_TOKEN en la web>
INTEGRATION_WEB_URL=https://sinexcusas.org.pe
```

y `php artisan optimize`. Los pasos para conectarlas por primera vez y traer el
historial de la web (`erp:vincular` y `erp:migrar`, que se ejecutan allá) están
en el `DEPLOY.md` de sin_excusas.

Los permisos de los módulos del centro y los roles **Recepción** y
**Especialista** los agrega solos la migración de esa versión al desplegar. Si
algún día hiciera falta rehacerlos, por SSH en `public_html/sistema`:

```bash
php artisan db:seed --class="Modules\Users\Database\Seeders\RolePermissionSeeder" --force
php artisan optimize
```

Si la web no contesta un aviso, el cambio igual se guarda aquí y queda en el
log; el botón **Sincronizar ahora** de la web (o su cron) lo recupera.

## Cómo funciona la publicación

Terminada la subida, GitHub Actions llama a `POST /deploy/release` con la
cabecera `X-Deploy-Token`. El servidor descomprime **por tandas** (1200
entradas por llamada, configurable con `DEPLOY_CHUNK`) en una carpeta de paso,
y solo cuando el paquete está entero mueve los archivos a su sitio y ejecuta:

```
php artisan optimize:clear   → tira las cachés del código viejo
php artisan migrate --force  → aplica las migraciones nuevas
php artisan optimize         → cachea config, rutas y vistas
```

**PHP 8.2.** El workflow instala `vendor/` con la misma versión que corre el
subdominio. Si subes la versión en hPanel, súbela también en el workflow.

## Si algo falla

| Síntoma | Causa y arreglo |
|---|---|
| `Faltan estos secretos …` | Crea los que nombra el mensaje en Settings → Secrets del repositorio del sistema. |
| `curl: (67)` al subir | Usuario o contraseña FTP mal: el usuario es `u367943235.sistema` y la contraseña distingue mayúsculas. |
| `curl: (6)` / `curl: (60)` al subir | `FTP_SERVER` debe ser el nombre del servidor, `algo.hstgr.io`, ni `ftp.dominio` ni la IP. |
| `No hay release.zip que publicar` | El zip no llegó a `public_html/sistema`: revisa a qué carpeta apunta la cuenta FTP y `FTP_DIR`. |
| `404` al publicar | `DEPLOY_TOKEN` vacío en el `.env`, config vieja en caché, o falta el `.htaccess` de `public_html/sistema`. |
| `403` al publicar | El token del `.env` y el secreto `DEPLOY_TOKEN` de **este** repositorio (no el de sin_excusas) no coinciden. Tras cambiar el del `.env`, `php artisan optimize`. |
| `Laravel no se reconoce bajo '/'` en el último paso | `public/index.php` del servidor no es el de este repositorio, o `ASSET_URL` del workflow no está vacío. |
| El subdominio muestra la página por defecto de Hostinger | Falta el `.htaccess` en `public_html/sistema` (o no se extrajo el zip). |
| Error 419 o la sesión se cierra sola | Se cambió el `.env` sin `php artisan optimize`. |
| El login acepta y vuelve a pedir credenciales | `SANCTUM_STATEFUL_DOMAINS` no es `sistema.sinexcusas.org.pe`. |
| Imágenes rotas | `APP_URL` mal, o falta `php artisan storage:link`. |
| Un menú nuevo (Centro, Pedidos online) no aparece | Faltan los permisos nuevos: ejecuta el `RolePermissionSeeder` (ver arriba) y vuelve a entrar. |
| La web no se entera de los cambios del catálogo | Revisa `INTEGRATION_WEB_URL` y que `INTEGRATION_TOKEN` sea igual a `ERP_TOKEN` de la web; después `php artisan optimize`. El log del sistema dice "No se pudo avisar a la web…". |
| `Este PHP no tiene la extensión zip` | hPanel → Configuración PHP → activar `zip`. |
| Se queda extrayendo y no termina | Baja `DEPLOY_CHUNK` en el `.env` (por ejemplo 400) y vuelve a lanzar. |
