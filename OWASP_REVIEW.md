# Revisión OWASP

Lista OWASP Top 10 (2021) sobre esta clínica: personal autenticado, una sede. No es un SaaS expuesto a clientes anónimos de varias empresas.

Severidad usada:

- Crítico: sin sesión, o cualquier usuario, crea privilegios, mueve dinero o corrompe stock.
- Alto: un autenticado se salta su rol sobre dinero, stock, historia clínica o datos de otro cliente.
- Medio y Bajo: corregidos en el mismo sistema. El detalle queda abajo.

## Corregido en este corte

### Crítico — registro público con rol

`POST /api/auth/register` no pedía sesión, aceptaba `role_id` y devolvía un token. La misma API JWT (`routes/api.php`) exponía ventas, stock y clientes sin las policies de `/api/v1`.

Acción: `RouteServiceProvider` ya no monta `routes/api.php`. El código queda en el repo. `POST /api/auth/register` responde 404.

### Alto — fiado sin movimiento de stock

Cualquier usuario con `sales.create` podía guardar `pending: true`. El stock no bajaba hasta el cobro total, así que el inventario no reflejaba la mercadería entregada.

Acción: fiar exige `sales.credit` (solo Administrador en el seeder) y un cliente. El stock sale por FEFO en ese momento. El cajero cobra abonos con `sales.charge` y su caja abierta. Un abono no puede pasar el saldo. La anulación de una deuda con abonos se rechaza. La devolución solo aplica a una venta pagada, y el efectivo de la devolución entra en la caja abierta.

Las deudas ya guardadas, sin lote en el ítem, siguen descontando stock al cobrarse.

### Alto — el efectivo del abono no entraba en la caja

`sale_payments` no guardaba la sesión. El esperado de caja ignoraba las ventas pendientes, así que un abono en efectivo no cambiaba el monto esperado.

Acción: cada pago y cada devolución en efectivo guardan `cash_session_id`. El esperado suma ese efectivo, resta el vuelto de las ventas de esa sesión y resta las devoluciones en efectivo de esa misma sesión.

### Alto — adjuntos clínicos en disco público

`ClinicService` guardaba exámenes y fotos de la historia en el disco `public` y devolvía una URL sin sesión.

Acción: los archivos nuevos van al disco `local`. La descarga es `GET /api/v1/medical-records/{id}/attachments/{id}` con `clinic.view`. Se aceptan jpg, jpeg, png, webp y pdf. Un archivo viejo que siga en `public` también se sirve solo por esa ruta autenticada.

## Medio — corregido

### Fotos de mascota en disco público

`PetService::storePhoto` guardaba en `public` y `PetResource` devolvía esa URL.

Acción: las fotos nuevas van al disco `local`. `foto` ya no sale en el JSON. `foto_url` apunta a `GET /api/v1/pets/{id}/photo`. Esa ruta pide `customers.view`, `customers.update`, o `portal.access` de la mascota del cliente. Una foto vieja que siga en `public` se sirve solo por esa ruta.

### Costo de compra en el portal

`SaleResource` incluía `unit_cost` para cualquier sesión, también la del cliente.

Acción: `unit_cost` solo se incluye si la sesión tiene `costs.view`. El portal no tiene ese permiso. `idempotency_key` solo vuelve al usuario que creó la venta y tiene `sales.create`.

### Importación sin tipo de archivo

El FormRequest limitaba el tamaño y no la extensión.

Acción: se aceptan `csv`, `txt`, `xlsx` y `xlsm`. El lector ya rechazaba el resto; ahora la validación corta antes de leer.

### Idempotencia como consulta de ventas ajenas

Quien podía crear ventas y conocía la clave recibía la venta de otro.

Acción: la repetición devuelve la venta solo si `user_id` es el de la sesión. Otro cajero recibe 422 y no el cuerpo de la venta.

### Cabeceras, debug y CORS

CSP y HSTS no salían del código. `APP_DEBUG` y los orígenes dependían solo del `.env`.

Acción: cada respuesta lleva `Content-Security-Policy` (`default-src 'none'; frame-ancestors 'none'`), `nosniff`, `X-Frame-Options: DENY` y `Permissions-Policy`. HSTS se envía si la petición es HTTPS, si `FORCE_HTTPS` está activo, o si el entorno es `production`. Con `APP_ENV=production` el arranque deja `app.debug` en false y `force_https` en true. Un origen `*` en `CORS_ALLOWED_ORIGINS` se descarta.

## Bajo — corregido

### Mensajes de validación en rutas públicas

Una petición sin sesión recibía el mapa de campos que fallaron. El login de credenciales incorrectas ya respondía un solo mensaje.

Acción: sin sesión, la validación responde «Los datos enviados no son válidos» y no incluye `errors`. Con sesión, el personal sigue recibiendo el campo, porque el formulario ya es suyo.

### Límite de intentos

El login de `/api/v1` y el del portal ya limitaban 5 intentos por minuto. La API legacy, sin ese límite, no está montada.

Acción: el cambio de contraseña usa el mismo límite de 5 por minuto.

## Controles que se revisaron y se mantienen

- Policies o `can()` en las rutas de `/api/v1` para usuarios, catálogo, stock, compras, ventas, clínica, reportes, facturas, auditoría y portal.
- El portal exige `portal.access` y la mascota tiene que ser del cliente de la cuenta.
- Consultas con `whereRaw` usan bindings. No hay concatenación de la entrada del usuario en SQL.
- Contraseñas con `Hash`. El modelo `User` oculta `password`.
- Login de personal rechaza cuentas de portal y cuentas inactivas.
- Ajustes de stock, caja, compras y emisión de comprobantes pasan por FormRequest con permiso.
