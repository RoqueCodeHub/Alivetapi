# Guía frontend — Veterinaria (Alivet)

Documento para **seguir el Angular** sin redescubrir el API. El backend Laravel 10 ya cubre las etapas 1–14 y el portal de cliente (etapa 15, código listo). Moneda: **Soles (PEN)**. Auth nueva: **Sanctum**. Prefijo: **`/api/v1`**.

El Angular actual es **funcional y sin diseño** (sin Material ni PrimeNG). Las pantallas existen; falta UI/UX.

---

## 1. Cómo arrancar

```text
# API (raíz del repo)
php artisan serve --host=127.0.0.1 --port=8000

# Frontend
cd frontend
npm start
```

- App staff: `http://localhost:4201/login` (el `ng serve` usa puerto **4201**)
- Portal cliente: `http://localhost:4201/portal/login`
- Proxy: `frontend/proxy.conf.json` reenvía `/api` → `http://127.0.0.1:8000`
- `environment.apiBaseUrl` = `/api/v1` (dev y prod)

Usuarios locales (solo desarrollo):

| Rol | Email | Contraseña típica |
|---|---|---|
| Administrador | `admin@alivet.com` | la del seeder / tu `.env` |
| Operador de tienda | `operador@alivet.com` | `operador123` |

El portal **no** usa esas cuentas. El staff habilita acceso en ficha de cliente (`POST /customers/{id}/portal`).

---

## 2. Contrato JSON (todas las rutas v1)

Éxito:

```json
{ "success": true, "message": "…", "data": { } }
```

Error:

```json
{ "success": false, "message": "…", "data": null, "errors": { } }
```

`errors` solo viene en validación (422).

Listados paginados suelen ser:

```json
{
  "success": true,
  "message": "…",
  "data": {
    "items": [],
    "meta": { "current_page": 1, "last_page": 1, "total": 0 }
  }
}
```

Interceptor: `frontend/src/app/core/interceptors/api.interceptor.ts`

- Manda `Authorization: Bearer {token}` y `Accept: application/json`
- En 401 (salvo login) limpia sesión
- Expone `ApiClientError` con `message` y `status`

Usuario logueado (`data.user`):

```ts
{
  id: number
  name: string
  email: string
  is_active: boolean
  cliente_id: number | null   // si tiene valor → es portal, no staff
  roles: string[]
  permissions: string[]
}
```

Guards:

- Staff: `staffGuard` → `/app/*` (si `cliente_id` redirige a `/portal`)
- Portal: `portalGuard` → `/portal/*`
- Permiso: `permissionGuard` + `data.permission` en la ruta
- Menú: `layout.ts` filtra por `auth.hasPermission(...)`

---

## 3. Mapa de pantallas (ya existen)

Rutas en `frontend/src/app/app.routes.ts`. Componentes en `frontend/src/app/features/`.

### Staff (`/app`)

| Ruta | Permiso | Archivo | Qué hace hoy |
|---|---|---|---|
| `/login` | — | `auth/login` | Login staff |
| `/app` | — | `shell/home` | Inicio |
| `/app/usuarios` | `users.view` | `users/users` | CRUD usuarios/roles |
| `/app/categorias` | `products.view` | `catalog/categories` | Árbol categorías |
| `/app/marcas` | `products.view` | `catalog/brands` | Marcas |
| `/app/productos` | `products.view` | `catalog/products` | Productos |
| `/app/productos/:id` | `products.view` | `catalog/product-detail` | Variantes |
| `/app/importar-inventario` | `inventory.adjust` | `inventory/import-inventory` | Excel/CSV |
| `/app/pendientes` | `products.view` | `inventory/pending-review` | `needs_review` |
| `/app/stock` | `inventory.view` | `inventory/stock-list` | Stock |
| `/app/stock/:id` | `inventory.view` | `inventory/stock-detail` | Lotes / ajuste |
| `/app/kardex/:id` | `inventory.view` | `inventory/kardex` | Kardex |
| `/app/alertas` | `inventory.view` | `inventory/stock-alerts` | Vencimiento / min |
| `/app/conteos` | `inventory.count` | `inventory/stock-counts` | Conteos |
| `/app/conteos/:id` | `inventory.count` | `inventory/stock-count-detail` | Líneas + aprobar |
| `/app/proveedores` | `purchases.view` | `purchases/suppliers` | Proveedores |
| `/app/compras` | `purchases.view` | `purchases/purchases` | Compras |
| `/app/compras/nueva` | `purchases.create` | `purchases/purchase-detail` | Alta |
| `/app/compras/:id` | `purchases.view` | `purchases/purchase-detail` | Ver/confirmar/pagar |
| `/app/cuentas-por-pagar` | `purchases.view` | `purchases/payables` | CxP |
| `/app/pos` | `sales.create` | `pos/pos` | Cobro |
| `/app/caja` | `cash.open` | `pos/cash-session` | Abrir/cerrar |
| `/app/ventas` | `sales.view` | `pos/sales` | Listado |
| `/app/ventas/:id` | `sales.view` | `pos/sale-detail` | Ticket, anular, devolver, emitir CPE |
| `/app/facturacion` | `invoicing.view` | `invoices/invoices` | Series + CPE |
| `/app/clientes` | `customers.view` | `customers/customers` | Clientes |
| `/app/clientes/:id` | `customers.view` | `customers/customer-detail` | Mascotas, compras, **habilitar portal** |
| `/app/servicios-clinicos` | `clinic.view` | `clinic/clinic-services` | Catálogo clínica |
| `/app/agenda` | `clinic.view` | `clinic/agenda` | Citas |
| `/app/mascotas/:id` | `clinic.view` | `clinic/pet-clinic` | Historia, vacunas, desparasitación |
| `/app/recordatorios` | `reminders.view` | `reminders/reminders` | Recordatorios + wa.me |
| `/app/reportes` | `reports.view_all` | `reports/reports` | Dashboard / export |
| `/app/auditoria` | `audit.view` | `audit/audit` | Logs, logins, backups |
| `/app/perfil` | — | `profile/profile` | Contraseña |

### Portal cliente (`/portal`)

| Ruta | Archivo | Qué hace |
|---|---|---|
| `/portal/login` | `portal/portal-login` | Login `POST /portal/login` |
| `/portal` | `portal/portal-home` | Mascotas, citas, solicitud, compras |
| `/portal/mascotas/:id/carnet` | `portal/portal-carnet` | Vacunas + desparasitaciones |

---

## 4. Auth

### Staff

```http
POST /api/v1/auth/login
{ "email": "…", "password": "…" }
```

Respuesta `data`: `{ token, token_type: "Bearer", user }`

```http
POST /api/v1/auth/logout
GET  /api/v1/auth/me
PUT  /api/v1/auth/password
{ "current_password", "password", "password_confirmation" }
```

Login limitado a **5/min** por IP+email. Un usuario de **portal** (`cliente_id` seteado) **no** entra por `/auth/login` (403: usar portal).

### Portal

```http
POST /api/v1/portal/login
{ "email": "…", "password": "…" }
```

Staff habilita:

```http
POST /api/v1/customers/{id}/portal
{ "email": "cliente@correo.com", "password": "mínimo 8" }
```

Permiso: `customers.update`. Rol asignado: `Cliente` (`portal.access`).

---

## 5. API staff — endpoints y payloads útiles

Base: `http://127.0.0.1:8000/api/v1` o, con proxy, `/api/v1`.

Todas las de abajo (salvo `GET /health` y logins) llevan Bearer.

### Health

`GET /health` → `data.status = ok`

### Usuarios

- `GET /roles`
- `GET /users`
- `POST /users` `{ name, email, password, roles: ["Cajero"] }`
- `GET /users/{id}` `PUT /users/{id}`
- `PATCH /users/{id}/activate` `PATCH /users/{id}/deactivate`

### Catálogo

Producto → **variante** (SKU, precio, stock mínimo). El producto no se borra: se desactiva.

- Categorías: `GET/POST /categories` `PUT /categories/{id}` (árbol `parent_id`)
- Marcas: `GET/POST /brands` `PUT /brands/{id}`
- Productos: `GET/POST /products` `GET/PUT /products/{id}` `DELETE /products/{id}` (desactiva)
- `POST /products/{id}/variants` `PUT /variants/{id}`

### Inventario

Stock **solo** por movimientos. Venta usa **FEFO** (lote que vence primero).

- Import: `GET /import/inventory/template` `POST /import/inventory` (multipart `.xlsx`/`.csv`)
- Pendientes: `GET /inventory/pending-review` `PUT /inventory/pending-review/{variante}`
- `GET /stock` `GET /stock/export` `GET /stock/{varianteId}`
- `POST /stock/adjustments` `{ batch_id, direction: "entrada"|"salida", quantity, reason }`
- `POST /stock/open-box`
- `GET /variants/{id}/kardex` `GET /variants/{id}/kardex/export`
- `GET /variants/{id}/fefo`
- Alertas: `GET /alerts/expiring` `GET /alerts/low-stock`
- Conteos: `GET/POST /stock/counts` `GET /stock/counts/{id}`  
  `PUT /stock/counts/{id}/lines` `{ items: [{ id, counted_qty }] }`  
  `POST /stock/counts/{id}/approve`

### Compras

Una compra **confirmada no se vuelve a confirmar**.

- Proveedores: `GET/POST /suppliers` `PUT /suppliers/{id}`
- `GET/POST /purchases` `GET/PUT /purchases/{id}`
- `POST /purchases/{id}/confirm`
- `POST /purchases/{id}/payments` `{ amount, method }`
- `GET /purchases/payables` (paginado)
- `GET /variants/{id}/cost-history`

### POS / caja / ventas

**Hay que abrir caja antes de vender.**

Pagos: `efectivo | yape | plin | tarjeta | transferencia`. El vuelto solo sale del efectivo.

```http
GET  /cash-sessions/current
POST /cash-sessions/open          { "opening_amount": 100 }
POST /cash-sessions/{id}/close    { "counted_amount": 150, "notes": "…" }

GET  /pos/search?q=amoxicilina

POST /sales
```

Cuerpo venta:

```json
{
  "idempotency_key": "uuid-obligatorio",
  "customer_id": null,
  "pet_id": null,
  "discount": 0,
  "invoice_type": null,
  "items": [
    { "item_type": "product", "variant_id": 1, "quantity": 2, "unit_price": 15, "discount": 0 },
    { "item_type": "service", "description": "Consulta", "quantity": 1, "unit_price": 40 }
  ],
  "payments": [
    { "method": "efectivo", "amount": 50 },
    { "method": "yape", "amount": 20 }
  ]
}
```

- `invoice_type`: omitir / `null` = solo ticket interno; `"boleta"` o `"factura"` = emite CPE al cobrar
- Misma `idempotency_key` = misma venta (no duplica)
- `GET /sales` `GET /sales/{id}` `GET /sales/{id}/receipt`
- `POST /sales/{id}/void` `{ "reason": "…" }` (permiso `sales.void`)
- `POST /sales/{id}/returns` `{ "reason": "…", "refund_method": "efectivo", "items": [{ "sale_item_id": 1, "quantity": 1 }] }`

Ticket interno: `series-number` (ej. `B001-000001`). **No es el CPE SUNAT.**

Venta `status`: `completed` | `voided`.

### Facturación electrónica (CPE)

Precios de venta se tratan **con IGV incluido (18%)**. Driver local: **`fake`** (no SUNAT real).

```http
GET  /invoices
GET  /invoices/series
GET  /invoices/requirements
GET  /invoices/{id}
POST /sales/{saleId}/invoices     { "type": "boleta" | "factura" }
POST /invoices/{id}/retry
```

Reglas UI:

- **Factura** exige cliente con **RUC 11 dígitos**
- **Boleta** sin documento solo si total ≤ S/ 700
- Series CPE: `B001` boleta, `F001` factura, `BC01`/`FC01` notas de crédito (independientes del ticket POS)
- Anular/devolver una venta **ya emitida y aceptada** genera nota de crédito

### Clientes y mascotas

```http
GET  /customers?q=
POST /customers
{ "name": "…", "document_type": "DNI"|"RUC"|"CE"|"PASAPORTE", "document_number": "…", "phone": "…", "whatsapp": "…" }

GET/PUT /customers/{id}
POST    /customers/{id}/portal
GET     /customers/{id}/purchases
POST    /customers/{id}/pets
{ "nombre": "Luna", "especie": "canino", "raza": "…", "sexo": "macho"|"hembra"|"desconocido", "fecha_nacimiento": "2024-01-01", "peso": 8.2, "alergias": "…" }

GET/PUT /pets/{id}
POST    /pets/{id}/photo   (multipart)
```

Cliente `data`: `id, name, document_type, document_number, phone, whatsapp, notes, activo, portal_enabled, pets[]`.

### Clínica

Staff_user es obligatorio al crear cita **desde agenda staff**. Desde portal puede ir vacío (solicitud).

Estados cita: `pendiente` | `atendida` | `cancelada`.

```http
GET/POST /services  PUT /services/{id}
GET/POST /appointments
GET/PUT  /appointments/{id}
POST     /appointments/{id}/cancel
POST     /appointments/{id}/attend
POST     /appointments/{id}/consumptions   { variant_id, quantity }  → descuenta stock FEFO
POST     /appointments/{id}/charge         { payments, idempotency_key? }  → POS sin volver a descontar stock

GET  /pets/{id}/clinical-history
POST /pets/{id}/medical-records
GET/PUT /medical-records/{id}
POST    /medical-records/{id}/attachments
POST /pets/{id}/vaccinations
PUT  /vaccinations/{id}
POST /pets/{id}/dewormings
PUT  /dewormings/{id}
```

Servicios seed: consulta, vacunación, desparasitación, baño, peluquería, cirugía, hospitalización, laboratorio.

### Recordatorios

```http
GET  /reminders?audience=&type=
POST /reminders/{id}/dismiss
```

Tipos: `vacuna`, `desparasitacion`, `cita`, `vencimiento`, `stock_minimo`, `diferencia_caja`.  
`audience`: `customer` | `internal`. Si hay WhatsApp, `whatsapp_url` es un `wa.me` (no hay proveedor WhatsApp).

### Reportes

Permiso de pantalla admin: `reports.view_all`. El operador ve dashboard propio con `reports.view`.

```http
GET /reports/dashboard
GET /reports/sales-by-day
GET /reports/top-products
GET /reports/by-category
GET /reports/by-cashier
GET /reports/profit
GET /reports/slow-movers
GET /reports/inventory-value
GET /reports/export
```

### Auditoría / seguridad

```http
GET /audit-logs
GET /login-logs
GET /backups
GET /security-status
```

---

## 6. API portal cliente

Todas con Bearer del `POST /portal/login`. Un cliente **no** ve datos de otro (422 si pide mascota ajena).

```http
GET  /portal/me
GET  /portal/pets
GET  /portal/pets/{petId}/carnet
GET  /portal/appointments          → { upcoming: [], all: [] }
POST /portal/appointments
{ "pet_id": 1, "clinic_service_id": 1, "scheduled_at": "2026-09-25 10:00:00", "notes": "opcional" }
     scheduled_at debe ser fecha futura
GET  /portal/purchases
GET  /portal/services
```

Logout: mismo `POST /auth/logout`.

---

## 7. Permisos (Spatie)

El menú y `permissionGuard` usan **estos nombres exactos**:

| Permiso | Uso UI |
|---|---|
| `users.view` `users.create` `users.update` `users.delete` | Usuarios |
| `products.view` `products.create` `products.update` | Catálogo |
| `inventory.view` `inventory.adjust` `inventory.count` | Stock / import / conteo |
| `purchases.view` `purchases.create` | Compras / proveedores |
| `sales.view` `sales.create` `sales.void` | POS / ventas |
| `cash.open` `cash.close` | Caja |
| `customers.view` `customers.create` `customers.update` | Clientes + portal |
| `costs.view` | Historial de costo |
| `reports.view` `reports.view_all` | Dashboard vs reportes admin |
| `clinic.view` `clinic.manage` `clinic.attend` `clinic.charge` | Clínica |
| `reminders.view` | Recordatorios |
| `audit.view` | Auditoría |
| `invoicing.view` `invoicing.emit` | CPE |
| `portal.access` | Solo rol Cliente |

Roles: Administrador, Operador de tienda, Cajero, Almacenero, Veterinario, Groomer / peluquero, Recepcionista, Contador, Auditor, Cliente, Soporte técnico.

---

## 8. Reglas de negocio que el front debe respetar

1. No vender sin caja abierta (`422` *Debes abrir caja antes de vender*).
2. No editar stock a mano en el producto; ajustes van a `/stock/adjustments`.
3. Carrito de producto usa `variant_id`, no `producto_id`.
4. FEFO lo resuelve el backend al cobrar; el POS no elige lote (salvo clínica con consumo ya hecho).
5. Cobro de cita: `charge` con `skip_stock` interno; no volver a descontar lo ya consumido.
6. `idempotency_key` único por intento de cobro (UUID).
7. Producto con variantes: desactivar, no borrar.
8. Compra confirmada: no reconfirmar.
9. Factura ≠ ticket interno.
10. Portal y staff son sesiones distintas; no mezclar menús.

---

## 9. Qué mejorar en el frontend (trabajo pendiente de UI)

El código de pantallas **ya consume el API**. Para “avanzar el front”:

1. **Diseño**: elegir Angular Material o PrimeNG (aún no hay librería UI).
2. Layout staff: sidebar, topbar, estados vacíos, loading, toasts (hoy es HTML mínimo).
3. POS: grilla, cantidades editables, mix de pagos, ticket imprimible, atajos teclado.
4. Tablas con paginación usando `data.meta`.
5. Formularios: mostrar `errors` de 422 por campo.
6. Portal: look & feel de cliente (carnet imprimible, calendario de citas).
7. `ng test` no está armado como suite de producto.
8. No hay PDF (dompdf no instalado); tickets/reportes son JSON/CSV.

No hace falta rehacer servicios HTTP: el interceptor + `environment.apiBaseUrl` ya unifican las llamadas.

---

## 10. Etapas hechas vs no hechas

| Etapa | Backend | Front mínimo |
|---|---|---|
| 0–2 Auth / usuarios | Sí | Sí |
| 3 Catálogo | Sí | Sí |
| 4 Import | Sí (xlsx/csv nativo) | Sí |
| 5 Stock / kardex / conteos | Sí | Sí |
| 6 Compras | Sí | Sí |
| 7 POS / caja | Sí | Sí |
| 8 Clientes / mascotas | Sí | Sí |
| 9 Reportes | Sí | Sí |
| 10 Clínica | Sí | Sí |
| 11 Recordatorios | Sí | Sí |
| 12 Auditoría / backups | Sí | Sí |
| 13 cPanel | No | — |
| 14 Facturación SUNAT/PSE | Sí (driver fake) | Sí |
| 15 Portal cliente | Sí (código) | Sí (mínimo) |

JWT legado `/api/*` (sin `v1`) **sigue** para el API viejo; el Angular nuevo **no** lo usa.

---

## 11. Archivos Angular clave

```text
frontend/src/app/
  core/
    guards/          auth.guard, guest.guard, permission.guard, portal.guard
    interceptors/    api.interceptor.ts
    services/        auth.service.ts
    models/          api-response.ts, auth-user.ts
    auth/            session.ts (token en storage)
  features/          una carpeta por módulo (arriba)
  app.routes.ts
```

Al agregar una pantalla nueva: ruta + `permission` + ítem en `layout.ts` + llamada a `/api/v1/...`.
