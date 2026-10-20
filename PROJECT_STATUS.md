# PROJECT STATUS

## Documento maestro
`proyect_status.md` (plan de desarrollo; antes `PLAN_DESARROLLO_VETERINARIA_CURSOR.md`)

Este archivo (`PROJECT_STATUS.md`) es el **tablero de estado**. No sustituye al plan.

## Etapa actual
ETAPA 14 — facturación electrónica

## Tarea actual
ETAPA 14 implementada en local — cierre formal lo autoriza el usuario

## Estado
E14-T01 … E14-T06 **CÓDIGO + PRUEBAS OK** (`php artisan test` 44 passed; `npm run build` OK)

## Última tarea terminada
ETAPA 14 — PSE/OSE (driver fake + HTTP), series/correlativos, IGV 18%, boletas/facturas, notas de crédito

## Próxima tarea
ETAPA 13 — despliegue en cPanel, o ETAPA 15 — portal del cliente (cuando el usuario autorice)

Hay que abrir caja antes de vender. El lote se elige por FEFO y se bloquea en transacción para no dejar stock negativo.

Una compra confirmada no se vuelve a confirmar. El stock solo cambia vía `stock_movements`.

El stock de lote solo cambia vía `stock_movements`. Las ventas (ETAPA 7) usarán la asignación FEFO ya expuesta en `GET /api/v1/variants/{id}/fefo`.

`maatwebsite/excel` no se instaló: PHP CLI 8.4 no tiene `ext-gd`. El importador lee `.xlsx` y `.csv` con ZipArchive/CSV nativo.

Un producto con variantes no se borra: se desactiva.

Usuarios seed locales (solo desarrollo): `admin@alivet.com` / `operador@alivet.com`

Frontend: `http://localhost:4201/login` (4200 está ocupado por otro sitio)
API: `php artisan serve --host=127.0.0.1 --port=8000`

---

# ESTRUCTURA (E0-T04)

Layout actual del workspace (monorepo temporal; el plan pide dos repos en E0-T03):

```text
veterinaria-api/                 Laravel 10 (código existente, no borrado)
  app/
    Http/
      Controllers/Api/           controladores actuales (sin mover)
      Requests/                  vacío — FormRequests
      Resources/                 vacío — API Resources
      Middleware/
    Services/                    vacío — lógica de dominio
    Policies/                    vacío
    Models/
    Providers/
  config/
  database/
  routes/
  tests/
    Feature/Api/
    Unit/Services/
  frontend/                      Angular 21.2 (nuevo)
    src/app/
      core/                      guards, interceptors, services (vacío)
      shared/                    components, pipes, directives (vacío)
      features/
        shell/                   pantalla inicial (lazy)
        auth/                    vacío — login en ETAPA 1
      app.ts / app.routes.ts
```

Qué se mejoró:

- Carpetas de arquitectura Laravel alineadas al plan, **sin extraer aún** la lógica de los controladores.
- Frontend Angular 21 con lazy loading del shell.
- Plantilla default de Angular reemplazada por un shell mínimo.
- `.gitignore` raíz ignora `frontend/node_modules`, `frontend/dist`, `frontend/.angular`.

Qué no se hizo (a propósito):

- No se movieron controladores a Services/Requests.
- No hay AuthGuard, interceptor HTTP ni environments (E1-T05).
- No hay Git ni repos separados (E0-T03).
- Material/PrimeNG no instalados.

Arranque:

- Backend: `php artisan` OK (10.50.2).
- Frontend: `cd frontend && npm start` → `http://localhost:4200/` (verificado).

---

# VERSIONES OFICIALES (E0-T02)

**Fuente de verdad para Cursor.** No instalar otra major sin autorización.

**Baseline:** entorno local XAMPP (PHP 8.1.25 + MariaDB 10.4.32), aceptado en lugar de cPanel hasta tener datos de hosting. El frontend se construye en local y se despliega como estáticos; Node no corre en cPanel.

## Stack fijado

| Pieza | Versión oficial | Estado | Notas |
|---|---|---|---|
| PHP runtime (web) | **8.1.25** | Instalado (XAMPP Apache) | Compatible con “hosting PHP 8” y con Laravel 10 |
| PHP CLI | 8.1.x **recomendado** | Máquina tiene 8.4.18 en PATH | Artisan/Composer hoy usan 8.4; no es el runtime web. No subir Laravel a 11+ |
| Laravel | **10.50.x** | Instalado 10.50.2 | `composer.json`: `^10.10` / `php: ^8.1`. No recrear el proyecto |
| Composer | **2.8.x** | Instalado 2.8.3 | |
| Base de datos | **MariaDB 10.4.32** | Instalada (XAMPP) | Driver Laravel `mysql`. Equivalente aceptado: MySQL 8.0.x |
| Angular | **21.2.x** | Instalado 21.2.0 (`frontend/`) | No usar 19/20/22 |
| Node.js | **24.13.x** | Instalado v24.13.1 | Compatible con Angular 21 (`^20.19 \|\| ^22.12 \|\| ^24.0`) |
| NPM | **11.8.x** | Instalado 11.8.0 | Gestor oficial del frontend |
| UI (cuando exista Angular) | Angular Material **o** PrimeNG | No elegido aún | El plan permite ambos; se decide en E0-T04 / E1-T05 |

## Paquetes backend (no instalar ahora; E1)

| Paquete | Versión objetivo | Hoy |
|---|---|---|
| laravel/sanctum | **^3.3** (3.3.3 en lock) | Configurado (stateful + tokens). JWT legado en `/api/*` |
| tymon/jwt-auth | 2.3.x | Sigue en rutas `/api/*` existentes |
| spatie/laravel-permission | **^6.0** (composer.json) | Falta `composer update` + migrate |
| maatwebsite/excel | última compatible Laravel 10 | No instalado (falta `ext-gd` en PHP CLI); importación E4 usa lector nativo xlsx/csv |
| barryvdh/laravel-dompdf | última compatible Laravel 10 | No instalado |

## Por qué Angular 21 y no 19

La tabla de arranque proponía Angular 19 + Node 20. Eso **no se congela**:

- Hoy es **2026-09-19**. Angular 19 llegó a EOL el **2026-05-19**.
- Node 20 LTS llegó a EOL el **2026-04-30**.
- Angular **22** pide Node `^24.15.0`; esta máquina tiene **24.13.1** (insuficiente).
- Angular **21** está soportado y declara Node `^20.19.0 \|\| ^22.12.0 \|\| ^24.0.0` → encaja con Node 24.13.1.
- cPanel no ejecuta `ng build`; no hay conflicto con PHP 8.1 del hosting.

## Reglas de compatibilidad (no romper)

1. Apache/XAMPP debe servir PHP **8.1.x**, no 8.4.
2. No migrar a Laravel 11/12: exige PHP 8.2+ y reescribe estructura (`bootstrap/app.php`); el código actual es Laravel 10.
3. No crear Angular 19/20/22 en E0-T04 / E1-T05; crear **Angular 21**.
4. Extensiones PHP requeridas (presentes en XAMPP 8.1): `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`.
5. Límites locales XAMPP 8.1: `memory_limit=512M`, `max_execution_time=0`, `upload_max_filesize=40M`, `post_max_size=40M`. Hosting puede ser más bajo; se revisará cuando exista cPanel.

## Fuera de alcance de E0-T02

No se inicializó Git (E0-T03).

## Evaluación de arranque (2026-09-19)

**¿Se puede empezar?** Sí, por la **ETAPA 0**. No por la ETAPA 1.

**¿El código actual sirve de base?** Sí. Hay API Laravel con dominio veterinario. Se reutiliza; no se borra.

**¿Se puede dar por terminada la ETAPA 0?** No.

| Pregunta | Respuesta |
|---|---|
| ¿Hay plan maestro? | Sí: `proyect_status.md` |
| ¿Hay backend Laravel que arranca? | Sí: 10.50.2 + MySQL/MariaDB |
| ¿Hay frontend Angular? | No |
| ¿Hay Git? | No |
| ¿Versiones alineadas a cPanel PHP 8? | Solo hipótesis local (XAMPP 8.1.25) |
| ¿Auth del plan (Sanctum)? | No: el API usa JWT |
| ¿API `/api/v1`? | No: usa `/api` |
| ¿Bloquea empezar E0? | No |
| ¿Bloquea empezar E1? | Sí: E0 incompleta |

### Baseline local propuesto (no es cPanel)

Usar esto como runtime oficial **hasta tener datos de hosting**:

| Pieza | Valor propuesto | Motivo |
|---|---|---|
| PHP runtime | **8.1.25 (XAMPP/Apache)** | Encaja con “hosting PHP 8”; Laravel 10 lo soporta |
| PHP CLI a evitar como runtime web | 8.4.18 (PATH) | Laravel 10 no declara PHP 8.4 |
| Laravel | **10.50.x** (ya instalado) | No recrear el proyecto |
| Base de datos | **MariaDB 10.4.32** / MySQL 8 | Ya conectada |
| Node (para Angular) | **20 LTS** recomendado; máquina actual 24.13.1 | cPanel no compila Node; se sube `dist` |
| Angular | **19** (a crear en E0-T04 / E1-T05) | Aún no existe |
| Auth (más adelante, E1) | Sanctum según plan; JWT se migra con justificación | No tocar ahora |

### Veredicto

1. **Iniciar E0:** autorizado por evaluación.
2. **No programar módulos** ni Sanctum/`/api/v1` hasta cumplir E0.
3. **Falta del usuario para cerrar E0-T01:** PHP/MySQL de cPanel, subdominio, Document Root, Cron, SSL, `memory_limit`, `max_execution_time`.
4. Si no hay cPanel todavía, se puede **autorizar el baseline local de la tabla** y seguir con E0-T02 → E0-T03 → E0-T04.

## Backend
- [x] Laravel configurado (10.50.2)
- [x] API `/api/v1` + JSON uniforme + errores globales
- [x] MySQL conectado (`GET /api/v1/health`)
- [x] CORS por orígenes (Angular 4200) + credentials
- [x] Sanctum (HasApiTokens, stateful, tokens; JWT legado se mantiene)
- [x] Spatie Permission 6.25.0 (`spatie_roles`, HasRoles)
- [~] maatwebsite/excel (sustituido en E4 por lector nativo por falta de `ext-gd` en CLI)
- [ ] PDF (dompdf)

## Frontend
- [x] Angular 21.2 configurado (`frontend/`)
- [x] Estructura `core/shared/features`
- [x] Environments + interceptor HTTP + errores API
- [x] Consulta `GET /api/v1/health` en el shell
- [ ] AuthGuard / RoleGuard

## Tests
- [ ] Unitarios
- [x] Feature escritos (`tests/Feature/Api/HealthTest.php`) — ejecutar `php artisan test`
- [ ] Frontend (`ng test`)

## Deploy
- [~] Local (backend Laravel + XAMPP; frontend no existe; hay dos PHP distintos)
- [ ] Pruebas
- [ ] Producción (cPanel no confirmado)

## Git
- [ ] Repositorio Git inicializado
- [ ] Remoto configurado
- [ ] Ramas `main` / `develop`
- [ ] Repos separados `veterinaria-backend` / `veterinaria-frontend`
- [x] `.gitignore` ignora `.env` y `vendor`
- [~] `.env.example` existe (plantilla Laravel genérica; incompleta para JWT)

---

# Diagnóstico (ETAPA 0)

Fecha: 2026-09-19  
Documento maestro: `proyect_status.md`  
Workspace inspeccionado: `c:\xampp\htdocs\veterinaria-api`

## 1. Estructura de carpetas

Proyecto **monolito Laravel** en la raíz (no hay carpeta `backend/` ni `frontend/`).

Presente:

```text
app/  bootstrap/  config/  database/  public/  resources/  routes/  storage/  tests/  vendor/
```

`app/` actual: `Console`, `Exceptions`, `Http/Controllers/Api`, `Http/Middleware`, `Models`, `Providers`.

Ausente respecto al plan:

- `app/Services`
- `app/Http/Requests`
- `app/Http/Resources`
- `app/Policies`
- aplicación Angular (`src/app/core`, `shared`, `features`)
- `PROJECT_STATUS.md` (se crea con este diagnóstico)

También en raíz: `API_DOCS.md`, `PLAN_DESARROLLO_VETERINARIA_CURSOR.md`, `artisan`, `composer.json`, `vite.config.js`.

## 2. Backend Laravel existente

**Sí existe** y ya tiene dominio de veterinaria (no es un skeleton vacío).

Paquetes relevantes:

| Paquete | composer.json | Lock |
|---|---|---|
| laravel/framework | ^10.10 | 10.50.2 |
| laravel/sanctum | ^3.3 | v3.3.3 |
| tymon/jwt-auth | ^2.3 | 2.3.0 |
| spatie/laravel-permission | no | — |
| maatwebsite/excel | no | — |
| barryvdh/laravel-dompdf / dompdf | no | — |

Rutas API (40): prefijo `api/` **sin** `v1`. Autenticación `auth:api` con driver **JWT**.

Módulos ya implementados (CRUD / flujos):

- Auth (register, login, logout, me, refresh)
- Categorías, clientes, trabajadores, proveedores, productos
- Movimientos de stock (`movimientos_stock`)
- Ventas (crear, listar, ver, anular)

Migraciones aplicadas en BD local (batch 1), incluyendo Sanctum `personal_access_tokens` aunque el API no usa Sanctum.

Arquitectura actual vs plan:

- Lógica en controladores (ej. `VentaController`), no en servicios
- Sin FormRequests, API Resources ni Policies
- Roles propios (`roles` + `users.role_id`), no Spatie
- Producto con `stock_actual` y un `fecha_vencimiento` (no variantes / lotes / FEFO)
- Tabla `movimientos_stock` (plan: `stock_movements`)
- Sanctum instalado pero middleware stateful comentado en `app/Http/Kernel.php`

## 3. Frontend Angular existente

**No existe** en este workspace ni en `C:\xampp\htdocs` (no hay `angular.json`).

`package.json` de la raíz es el de **Laravel Vite** (axios, vite, laravel-vite-plugin), no Angular.

`node_modules` **no está instalado**.

La carpeta `C:\xampp\htdocs\dashboard` es el dashboard de XAMPP, no un frontend del sistema.

## 4. Versiones detectadas (local)

| Componente | Versión | Notas |
|---|---|---|
| PHP CLI (`php` en PATH) | 8.4.18 | `C:\Program Files\php-8.4.18\` — usado por Composer/Artisan |
| PHP Apache/XAMPP | 8.1.25 | `C:\xampp\php\` — el que servirá `http://localhost/veterinaria-api/public` |
| Laravel | 10.50.2 | Requiere `php: ^8.1` |
| Composer | 2.8.3 | |
| Node | v24.13.1 | |
| NPM | 11.8.0 | |
| Motor SQL XAMPP | MariaDB 10.4.32 | Compatible con driver MySQL de Laravel |
| Angular | — | No instalado |

Límites PHP:

| | CLI 8.4 | XAMPP 8.1 |
|---|---|---|
| memory_limit | 128M | 512M |
| max_execution_time | 0 | 0 |
| upload_max_filesize | 20M | 40M |
| post_max_size | 25M | 40M |

Extensiones CLI 8.4 relevantes: `pdo_mysql`, `openssl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`.  
XAMPP 8.1 además incluye `mysqli`, `pdo_sqlite`, `bz2`, `exif`, `ftp`, `gettext`.

**Hosting cPanel (E0-T01):** no se pudo confirmar desde esta máquina. Faltan versión PHP del hosting, MySQL remoto, subdominios, Document Root, Cron, SSL y límites de producción.

## 5. Configuración existente

- `.env` presente (ignorado por `.gitignore`)
- `.env.example` plantilla Laravel 10 estándar; **no documenta `JWT_SECRET`**
- `APP_DEBUG` habilitado en local
- CORS: `paths` `api/*` y `sanctum/csrf-cookie`; `allowed_origins = *`; `supports_credentials = false`
- Guard default `web`; guard `api` = `jwt`
- Prefijo de rutas: `api` (RouteServiceProvider)
- Driver BD: mysql; cache/session/queue: file / file / sync
- Documentación de API en `API_DOCS.md` (base URL `http://localhost/veterinaria-api/public/api`, JWT)

## 6. Dependencias instaladas

- `vendor/` sí (Composer)
- `node_modules/` no
- Sanctum presente y no usado en rutas
- JWT sí usado
- Faltan paquetes del documento maestro: Spatie Permission, Excel, PDF

## 7. Estado de Git

**No hay repositorio Git** (no existe carpeta `.git`).

No hay remoto, ramas, convención de commits aplicada, ni historial.

`.gitignore` y `.gitattributes` sí existen (plantilla Laravel). `.env` y `vendor` están ignorados. Al no haber Git, el criterio “no hay credenciales reales en Git” aún no está cubierto por un repo real.

## 8. Variables de entorno (sin secretos)

Claves presentes en `.env`:

`APP_NAME`, `APP_ENV`, `APP_KEY` (definida), `APP_DEBUG`, `APP_URL`,  
`LOG_*`,  
`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (definida),  
`BROADCAST_DRIVER`, `CACHE_DRIVER`, `FILESYSTEM_DISK`, `QUEUE_CONNECTION`, `SESSION_*`,  
`MEMCACHED_HOST`, `REDIS_*`, `MAIL_*`, `AWS_*`, `PUSHER_*`, `VITE_*`,  
`JWT_SECRET` (definida; **no está en `.env.example`**).

Valores y secretos no se documentan aquí.

---

## Qué existe

- Backend Laravel 10 operativo localmente
- API REST con JWT, módulos de catálogo/personas/stock/ventas
- Migraciones corridas contra MariaDB/MySQL local
- Seeders (roles, users, categorías, proveedores, clientes, trabajadores)
- `.env` / `.env.example` / `.gitignore`
- `vendor/` y Artisan funcionando
- `API_DOCS.md`
- Documento maestro en `proyect_status.md`

## Qué falta (ETAPA 0)

- Confirmación del entorno **hosting/cPanel** (E0-T01 sigue parcial; se trabaja con baseline local)
- Git + repos separados backend/frontend (E0-T03)
- Convenciones de rama/commits
- Frontend Angular y su arranque local (criterio de terminado E0)
- Estructura Angular `core/shared/features` (E0-T04)
- `JWT_SECRET` (y más adelante Sanctum) en `.env.example`
- Instalación de `node_modules` si se usa Vite del skeleton Laravel

## Qué está incorrecto respecto al documento maestro

Estos puntos **no se corrigen en esta etapa**; se registran para no romper código existente:

1. Auth: **JWT (`tymon/jwt-auth`)** vs **Sanctum** obligatorio en el plan (sin autorización no se cambia).
2. API en `/api/...` vs `/api/v1/...`.
3. Roles caseros vs **spatie/laravel-permission**.
4. Stock en el producto (`stock_actual`) y tabla `movimientos_stock` vs modelo Producto → Variante, lotes, FEFO y `stock_movements`.
5. Lógica en controladores vs servicios delgados + policies.
6. Un solo proyecto vs `veterinaria-backend` + `veterinaria-frontend`.
7. PHP dual local: Artisan/Composer en **8.4.18** y Apache XAMPP en **8.1.25**. Laravel 10 no declara soporte oficial de PHP 8.4.
8. Sanctum instalado y sin uso (duplicidad con JWT).
9. CORS abierto (`*`) y `APP_DEBUG=true` (aceptable en local; no para producción).

## Qué debe modificarse (después de autorización, no ahora)

Orden propuesto por el plan, **sin ETAPA 1 todavía**:

1. E0-T01: cPanel sigue pendiente; baseline local ya aceptado.
2. E0-T02: **hecho** (versiones oficiales arriba).
3. E0-T03: inicializar Git; definir `main`/`develop`; completar `.env.example`; **no commitear `.env`**.
4. E0-T04: **hecho** (estructura Laravel + Angular 21). No borrar el Laravel actual.
5. Recién en ETAPA 1: alinear `/api/v1`, Sanctum, Spatie, JSON uniforme. El código JWT y los módulos actuales deben **reutilizarse o migrarse con justificación**, no eliminarse de golpe.

## Tareas ETAPA 0 — estado

| ID | Tarea | Estado | Notas |
|---|---|---|---|
| E0-T01 | Confirmar entorno | **PARCIAL** | Baseline local aceptado (XAMPP). cPanel no confirmado. |
| E0-T02 | Definir versiones | **TERMINADA** | PHP 8.1.25, Laravel 10.50.x, MariaDB 10.4.32, Angular 21.x, Node 24.13.x, NPM 11.8.x |
| E0-T03 | Definir repositorios | **NO LISTA** | Sin Git, sin split backend/frontend, `.env.example` incompleto. |
| E0-T04 | Definir estructura inicial | **TERMINADA** | Laravel: Services/Requests/Resources/Policies. Angular 21 en `frontend/` con core/shared/features. Controladores existentes no se movieron. |

### Criterio de terminado ETAPA 0

| Criterio | ¿Cumple? |
|---|---|
| Versiones confirmadas | Sí (baseline local; Angular 21 no 19) |
| Repositorios creados | No |
| Backend arranca localmente | Sí (`artisan`; migraciones OK) |
| Frontend arranca localmente | Sí (`frontend/` → `http://localhost:4200/`) |
| `.env.example` documentado | Sí (JWT, CORS, Sanctum, notas de producción) |
| No hay credenciales reales en Git | N/A (no hay Git); `.env` sí está en `.gitignore` |

**ETAPA 0: no terminada.**  
E0-T02 y E0-T04 terminadas. Faltan E0-T03 y cierre de E0-T01 (cPanel).

## Tareas ETAPA 1 — estado

| ID | Tarea | Estado | Notas |
|---|---|---|---|
| E1-T01 | Laravel API `/api/v1` + JSON + errores | **CÓDIGO LISTO** | `routes/api_v1.php`, `ApiResponse`, `Handler`. `/api/*` JWT se conserva |
| E1-T02 | Seguridad CORS/.env/HTTPS | **CÓDIGO LISTO** | CORS orígenes 4200, TrustProxies `*`, `.env.example` |
| E1-T03 | Sanctum | **CÓDIGO LISTO** | `HasApiTokens`, stateful, expiración de tokens. Login v1 es ETAPA 2 |
| E1-T04 | Spatie Permission | **TERMINADA** | 6.25.0, tablas `spatie_roles`/`permissions`, HasRoles, seed Administrador y Operador de tienda |
| E1-T05 | Angular interceptor/environments | **CÓDIGO LISTO** | `apiInterceptor`, `environment*.ts` |
| E1-T06 | Health + conexión | **CÓDIGO LISTO** | `GET /api/v1/health`; shell Angular lo consume. Verificar con `artisan serve` |

Criterio ETAPA 1 (Laravel responde, Angular consume, MySQL, API versionada, errores, pruebas): **cumplido en local**. Cierre formal lo autoriza el usuario. Login sigue en ETAPA 2.

## Decisiones pendientes (requieren al usuario)

- Datos de cPanel: PHP, MySQL, subdominios, Document Root, Cron, SSL, límites.
- Alinear PHP CLI a 8.1 (hoy Artisan usa 8.4.18): recomendado, no bloquea E0-T03.
- ¿Un monorepo o dos repos como exige el plan?
- ¿Dónde vivirá Angular?
- Sanctum vs JWT: el plan **prohíbe cambiar Sanctum sin autorización**; el código actual usa JWT. Hace falta decisión explícita al llegar a E1-T03 (no ahora).
- Destino del código de dominio ya escrito (reutilizar vs reescribir por etapas).

## Problemas conocidos

- Dos intérpretes PHP distintos en la misma máquina.
- Laravel 10 + PHP 8.4 CLI: riesgo de avisos/incompatibilidades.
- Frontend Angular 21 arranca; aún sin interceptor ni conexión al API (E1).
- Sin Git: no hay historial ni protección de secretos en remoto.
- Divergencia fuerte del modelo de inventario respecto al plan (se tocará en etapas posteriores, no en E0).

## Autorización

ETAPA 1 iniciada por el usuario. Login `/api/v1/auth/*` y CRUD de usuarios **no** se implementaron (ETAPA 2).

Para cerrar E1-T04 y las pruebas, ejecutar los comandos de “Próxima acción inmediata” (el terminal de esta sesión no pudo correr Composer).

Siguiente tarea pendiente del plan: **E0-T03 — Definir repositorios**.

---

# BRECHA DE BASE DE DATOS vs `proyect_status.md`

Revisión: 2026-09-20. **No se aplicaron migraciones nuevas.** El esquema actual (migraciones corridas) no coincide con el modelo de la sección 17 del plan.

## Tablas que ya existen (nombres actuales)

| Actual | Equivalente en el plan | ¿Sirve? |
|---|---|---|
| `users` | `users` | Parcial: falta `activo`/`inactivo` (E2-T01) |
| `roles` + `users.role_id` | Spatie `roles` / `model_has_roles` / `permissions` | Incorrecto: un solo rol por usuario, 3 roles (admin/veterinario/recepcionista) vs 11 del plan |
| `personal_access_tokens` | Sanctum | Existe; el API usa JWT |
| `password_reset_tokens`, `failed_jobs` | Laravel | OK |
| `categorias` | `categories` | Parcial: falta `parent_id`, `slug` |
| `productos` | `products` + `variants` | Incorrecto: stock, precio, barcode y vencimiento están en el producto |
| `movimientos_stock` | `stock_movements` | Incorrecto: liga a `producto_id`, tipos distintos |
| `proveedores` + `personas` | `suppliers` | Parcial: el plan pide name/ruc/phone/email en una tabla |
| `clientes` + `personas` | `customers` | E8: `nombre`, `whatsapp`, `activo`; DNI/RUC en `personas` (`tipo_documento` incluye RUC) |
| `trabajadores` | (no está en el modelo principal) | Extra; el plan usa `users` + roles |
| `ventas` | `sales` | Incorrecto: faltan caja, serie/número, idempotency, pagos |
| `venta_detalles` | `sale_items` | Incorrecto: falta `variant_id`, `batch_id`, `unit_cost` |

## Tablas del plan que **no existen**

| Tabla | Etapa | Campos clave que faltan |
|---|---|---|
| `brands` | E3-T02 | CRUD marcas |
| `variants` | E3-T04 | sku, barcode, presentation, unit, sale_unit, units_per_box, cost_price, sale_price, min_stock, needs_review |
| `batches` | E5-T01 | variant_id, batch_code, expiry_date, quantity, cost |
| `stock_movements` | E5-T02 | variant_id, batch_id, type (compra/venta/ajuste/devolución/merma/vencido/inicial) |
| `purchases` | E6-T02 | supplier_id, comprobante, fecha, total |
| `purchase_items` | E6-T02 | variant_id, batch_id, quantity, cost |
| `cash_sessions` | E7 | user_id, opened_at, closed_at, opening/expected/counted/difference |
| `sale_items` (modelo plan) | E7 | variant_id, batch_id, unit_price, unit_cost, discount |
| `payments` | E7-T03 | sale_id, method (efectivo/Yape/Plin/tarjeta/transferencia), amount |
| `pets` | E8-T02 | **Creada** (`cliente_id`, nombre, especie, raza, sexo, fecha_nacimiento, peso, alergias, foto); `sales.pet_id` nullable |
| Spatie permission | E1-T04 / E2 | `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` |
| `audit_logs` | E12 | **Creada** (`user_id`, action, model, model_id, before, after, ip) + `login_logs` + `backup_probes` |
| Conteos físicos | E5-T06 | no definidos como tabla en sección 17, pero el flujo los exige |
| Importación | E4 | no hay tablas de filas importadas / needs_review a nivel variante |

## Qué está **incorrecto** y debe modificarse (cuando toque la etapa)

No borrar datos actuales a ciegas. Hay que **migrar** el modelo, no solo añadir columnas.

1. **Inventario en el producto** (`productos.stock_actual`, `fecha_vencimiento`, precios). El plan prohíbe editar stock a mano y exige Producto → Variante → Lote → `stock_movements`.
2. **Kardex** `movimientos_stock` vs `stock_movements` (nombres, FKs y tipos de movimiento).
3. **Ventas** sin `idempotency_key`, sin `cash_session_id`, sin costo histórico en ítems, cliente obligatorio (el POS pide cliente opcional).
4. **Roles** caseros vs Spatie + lista de 11 roles (solo Administrador y Operador activos al inicio).
5. **Categorías** planas; el plan pide árbol (`parent_id`) y categorías iniciales (Medicamentos con hijos, etc.).
6. **Proveedores** sin RUC en su tabla; van por `personas`.
7. Nombres en español (`categorias`, `productos`, `ventas`) vs inglés del plan (`categories`, `products`, `sales`). Decisión pendiente: ¿renombrar o mapear?

## Qué **no** hay que tocar todavía

Según el plan, estas tablas se crean en sus etapas (no en E0):

- E1: Sanctum (tabla ya existe) + Spatie
- E2: usuarios activo/inactivo, permisos
- E3: brands, variants, categories árbol
- E5: batches, stock_movements
- E6: purchases
- E7: cash_sessions, payments, idempotency
- E8: pets (**hecho**)
- E9: reportes / dashboard (**hecho**)
- E10: clínica (**hecho**)
- E11: recordatorios (**hecho**)
- E14: facturación (**hecho**: `invoices`, `invoice_series`, PSE fake/http)

**No se modificó la BD en esta revisión.** Hace falta autorización explícita para crear migraciones (y conviene no hacerlo todo de una vez).
