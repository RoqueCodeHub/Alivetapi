# Veterinaria API — Documentación

**Base URL:** `http://localhost/veterinaria-api/public/api`
**Autenticación:** JWT Bearer Token
**Content-Type:** `application/json`

---

## Autenticación

Todos los endpoints (excepto `register` y `login`) requieren el header:
```
Authorization: Bearer {token}
```

---

## 1. Auth

### POST `/auth/register`
Registra un nuevo usuario en el sistema.

**Acceso:** Público

**Body:**
```json
{
  "name": "Juan Pérez",
  "email": "juan@example.com",
  "password": "secret123",
  "role_id": 3
}
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| name | string | ✅ | Nombre del usuario |
| email | string | ✅ | Correo único |
| password | string | ✅ | Mínimo 6 caracteres |
| role_id | integer | ❌ | ID del rol (default: 3 = recepcionista) |

**Respuesta 201:**
```json
{
  "message": "Usuario registrado exitosamente",
  "user": { "id": 1, "name": "Juan Pérez", "email": "juan@example.com", "role": {...} },
  "token": "eyJ0eXAiOiJKV1Qi..."
}
```

---

### POST `/auth/login`
Inicia sesión y obtiene el token JWT.

**Acceso:** Público

**Body:**
```json
{
  "email": "juan@example.com",
  "password": "secret123"
}
```

**Respuesta 200:**
```json
{
  "message": "Login exitoso",
  "user": { "id": 1, "name": "Juan Pérez", "role": {...} },
  "token": "eyJ0eXAiOiJKV1Qi...",
  "type": "bearer"
}
```

**Respuesta 401:** Credenciales incorrectas.

---

### GET `/auth/me`
Retorna los datos del usuario autenticado.

**Respuesta 200:**
```json
{
  "user": { "id": 1, "name": "Juan Pérez", "email": "juan@example.com", "role": {...} }
}
```

---

### POST `/auth/logout`
Cierra la sesión e invalida el token.

**Respuesta 200:**
```json
{ "message": "Sesión cerrada exitosamente" }
```

---

### POST `/auth/refresh`
Refresca el token JWT actual.

**Respuesta 200:**
```json
{ "token": "eyJ0eXAiOiJKV1Qi..." }
```

---

## 2. Categorías

### GET `/categorias`
Lista todas las categorías ordenadas por nombre.

**Respuesta 200:**
```json
{
  "message": "Categorías obtenidas exitosamente",
  "total": 5,
  "categorias": [
    { "id": 1, "nombre": "Antibióticos", "descripcion": "...", "activo": true }
  ]
}
```

---

### POST `/categorias`
Crea una nueva categoría.

**Body:**
```json
{
  "nombre": "Antibióticos",
  "descripcion": "Medicamentos antibióticos"
}
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| nombre | string | ✅ | Único en la tabla |
| descripcion | string | ❌ | |

**Respuesta 201:**
```json
{
  "message": "Categoría creada exitosamente",
  "categoria": { "id": 1, "nombre": "Antibióticos", "activo": true }
}
```

---

### GET `/categorias/{id}`
Retorna una categoría con el conteo de productos asociados.

**Respuesta 200:**
```json
{
  "categoria": { "id": 1, "nombre": "Antibióticos", "productos_count": 12 }
}
```

**Respuesta 404:** `{ "message": "Categoría no encontrada" }`

---

### PUT `/categorias/{id}`
Actualiza una categoría.

**Body** (todos los campos opcionales):
```json
{
  "nombre": "Antibióticos Veterinarios",
  "descripcion": "Nueva descripción",
  "activo": false
}
```

**Respuesta 200:**
```json
{
  "message": "Categoría actualizada exitosamente",
  "categoria": { ... }
}
```

---

### DELETE `/categorias/{id}`
Elimina una categoría. Falla si tiene productos asociados.

**Respuesta 200:** `{ "message": "Categoría eliminada exitosamente" }`
**Respuesta 404:** `{ "message": "Categoría no encontrada" }`
**Respuesta 409:** `{ "message": "No se puede eliminar la categoría porque tiene productos asociados" }`

---

## 3. Clientes

### GET `/clientes`
Lista todos los clientes con sus datos personales.

**Respuesta 200:**
```json
{
  "message": "Clientes obtenidos exitosamente",
  "total": 3,
  "clientes": [
    {
      "id": 1,
      "nombres": "Ana",
      "apellidos": "Torres",
      "nombre_completo": "Ana Torres",
      "tipo_documento": "DNI",
      "numero_documento": "44556677",
      "telefono": "987654321",
      "email": "ana@gmail.com",
      "direccion": "Urb. Las Flores 234",
      "activo": true,
      "observaciones": null
    }
  ]
}
```

---

### POST `/clientes`
Crea un nuevo cliente junto con su persona.

**Body:**
```json
{
  "nombres": "Ana",
  "apellidos": "Torres",
  "tipo_documento": "DNI",
  "numero_documento": "44556677",
  "telefono": "987654321",
  "email": "ana@gmail.com",
  "direccion": "Urb. Las Flores 234",
  "observaciones": "Cliente frecuente"
}
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| nombres | string | ✅ | |
| apellidos | string | ✅ | |
| tipo_documento | string | ✅ | `DNI`, `CE` o `PASAPORTE` |
| numero_documento | string | ❌ | Único en personas |
| telefono | string | ❌ | Máx 50 caracteres |
| email | string | ❌ | Único en personas |
| direccion | string | ❌ | |
| observaciones | string | ❌ | |

**Respuesta 201:**
```json
{
  "message": "Cliente registrado exitosamente",
  "cliente": { ... }
}
```

---

### GET `/clientes/{id}`
Retorna un cliente con el total de ventas realizadas.

**Respuesta 200:**
```json
{
  "cliente": { "id": 1, "nombre_completo": "Ana Torres", ... },
  "total_ventas": 5
}
```

---

### PUT `/clientes/{id}`
Actualiza datos del cliente y/o su persona.

**Body** (todos los campos opcionales):
```json
{
  "nombres": "Ana María",
  "telefono": "999888777",
  "activo": false,
  "observaciones": "Actualizado"
}
```

**Respuesta 200:**
```json
{
  "message": "Cliente actualizado exitosamente",
  "cliente": { ... }
}
```

---

### DELETE `/clientes/{id}`
Elimina el cliente y su persona. Falla si tiene ventas.

**Respuesta 200:** `{ "message": "Cliente eliminado exitosamente" }`
**Respuesta 409:** `{ "message": "No se puede eliminar el cliente porque tiene ventas registradas." }`

---

## 4. Trabajadores

### GET `/trabajadores`
Lista todos los trabajadores con sus datos personales y usuario del sistema.

**Respuesta 200:**
```json
{
  "message": "Trabajadores obtenidos exitosamente",
  "total": 4,
  "trabajadores": [
    {
      "id": 1,
      "nombre_completo": "Carlos Ramos",
      "tipo_documento": "DNI",
      "numero_documento": "12345678",
      "telefono": "999000111",
      "email": "carlos@vet.com",
      "activo": true,
      "cargo": "Veterinario",
      "especialidad": "Cirugía",
      "salario": 3500.00,
      "fecha_contratacion": "2024-01-15",
      "usuario": { "id": 2, "email": "carlos@vet.com", "rol": "veterinario" }
    }
  ]
}
```

---

### POST `/trabajadores`
Registra un trabajador, su persona y crea automáticamente un usuario del sistema. La contraseña inicial es el número de documento.

**Body:**
```json
{
  "nombres": "Carlos",
  "apellidos": "Ramos",
  "tipo_documento": "DNI",
  "numero_documento": "12345678",
  "telefono": "999000111",
  "email": "carlos@vet.com",
  "direccion": "Av. Principal 123",
  "cargo": "Veterinario",
  "especialidad": "Cirugía",
  "salario": 3500.00,
  "fecha_contratacion": "2024-01-15"
}
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| nombres | string | ✅ | |
| apellidos | string | ✅ | |
| tipo_documento | string | ✅ | `DNI`, `CE` o `PASAPORTE` |
| numero_documento | string | ✅ | Único. Se usa como contraseña inicial |
| email | string | ✅ | Único en personas y users |
| telefono | string | ❌ | |
| direccion | string | ❌ | |
| cargo | string | ✅ | Determina el rol: `Veterinario/Veterinaria` → veterinario, `Recepcionista` → recepcionista |
| especialidad | string | ❌ | |
| salario | numeric | ❌ | |
| fecha_contratacion | date | ✅ | Formato `YYYY-MM-DD` |

**Respuesta 201:**
```json
{
  "message": "Trabajador registrado exitosamente",
  "trabajador": { ... }
}
```

---

### GET `/trabajadores/{id}`

**Respuesta 200:**
```json
{
  "trabajador": { "id": 1, "nombre_completo": "Carlos Ramos", ... }
}
```

---

### PUT `/trabajadores/{id}`
Actualiza trabajador, persona y usuario sincronizadamente.

**Body** (todos los campos opcionales):
```json
{
  "cargo": "Recepcionista",
  "salario": 2800,
  "activo": false
}
```

---

### DELETE `/trabajadores/{id}`
Elimina el trabajador, su usuario y su persona. Falla si tiene ventas registradas.

**Respuesta 409:** `{ "message": "No se puede eliminar el trabajador porque tiene ventas registradas. Puede desactivarlo cambiando activo a false." }`

---

## 5. Proveedores

### GET `/proveedores`
Lista todos los proveedores.

**Respuesta 200:**
```json
{
  "message": "Proveedores obtenidos exitosamente",
  "total": 3,
  "proveedores": [
    {
      "id": 1,
      "nombre_completo": "Distribuidora Farma SAC",
      "empresa": "Distribuidora Farma SAC",
      "telefono": "01-234-5678",
      "email": "ventas@farma.com",
      "activo": true
    }
  ]
}
```

---

### POST `/proveedores`
Crea un proveedor junto con su persona.

**Body:**
```json
{
  "nombres": "Distribuidora",
  "apellidos": "Farma SAC",
  "tipo_documento": "DNI",
  "numero_documento": "20123456789",
  "telefono": "01-234-5678",
  "email": "ventas@farma.com",
  "direccion": "Av. Industrial 456",
  "empresa": "Distribuidora Farma SAC"
}
```

| Campo adicional | Tipo | Requerido | Descripción |
|-----------------|------|-----------|-------------|
| empresa | string | ❌ | Nombre comercial |

---

### GET `/proveedores/{id}`
Retorna proveedor con su lista de productos asociados.

**Respuesta 200:**
```json
{
  "proveedor": {
    "id": 1,
    "nombre_completo": "Distribuidora Farma",
    "empresa": "Distribuidora Farma SAC",
    "productos": [ { "id": 1, "nombre": "Amoxicilina", ... } ]
  }
}
```

---

### PUT `/proveedores/{id}`
Actualiza datos del proveedor.

---

### DELETE `/proveedores/{id}`
Falla si el proveedor tiene productos asociados.

**Respuesta 409:** `{ "message": "No se puede eliminar el proveedor porque tiene productos asociados" }`

---

## 6. Productos

### GET `/productos`
Lista todos los productos con categoría y proveedor.

**Query params opcionales:** ninguno.

**Respuesta 200:**
```json
{
  "message": "Productos obtenidos exitosamente",
  "total": 20,
  "productos": [
    {
      "id": 1,
      "nombre": "Amoxicilina 500mg",
      "descripcion": "Antibiótico de amplio espectro",
      "codigo": "AMX500",
      "categoria": "Antibióticos",
      "proveedor": "Distribuidora Farma SAC",
      "precio_compra": 5.50,
      "precio_venta": 12.00,
      "stock_actual": 150,
      "stock_minimo": 20,
      "stock_bajo": false,
      "unidad_medida": "tableta",
      "fecha_vencimiento": "2026-12-31",
      "requiere_receta": true,
      "activo": true
    }
  ]
}
```

---

### POST `/productos`
Crea un nuevo producto.

**Body:**
```json
{
  "nombre": "Amoxicilina 500mg",
  "descripcion": "Antibiótico de amplio espectro",
  "codigo": "AMX500",
  "categoria_id": 1,
  "proveedor_id": 2,
  "precio_compra": 5.50,
  "precio_venta": 12.00,
  "stock_actual": 150,
  "stock_minimo": 20,
  "unidad_medida": "tableta",
  "fecha_vencimiento": "2026-12-31",
  "requiere_receta": true
}
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| nombre | string | ✅ | |
| descripcion | string | ❌ | |
| codigo | string | ❌ | Código de barras, único |
| categoria_id | integer | ✅ | Debe existir en categorías |
| proveedor_id | integer | ✅ | Debe existir en proveedores |
| precio_compra | numeric | ✅ | ≥ 0 |
| precio_venta | numeric | ✅ | ≥ 0 |
| stock_actual | integer | ✅ | ≥ 0 |
| stock_minimo | integer | ✅ | Umbral de alerta de stock bajo |
| unidad_medida | string | ✅ | Ej: `tableta`, `ml`, `mg`, `kg`, `unidad` |
| fecha_vencimiento | date | ❌ | Debe ser posterior a hoy |
| requiere_receta | boolean | ❌ | Default: false |

---

### GET `/productos/stock-bajo`
Lista productos activos cuyo `stock_actual` ≤ `stock_minimo`.

**Respuesta 200:**
```json
{
  "message": "Productos con stock bajo",
  "total": 3,
  "productos": [ ... ]
}
```

---

### GET `/productos/por-vencer`
Lista productos activos con `fecha_vencimiento` en los próximos **30 días**.

**Respuesta 200:**
```json
{
  "message": "Productos próximos a vencer",
  "total": 2,
  "productos": [ ... ]
}
```

---

### GET `/productos/{id}`
Retorna un producto con información de stock.

**Respuesta 200:**
```json
{
  "producto": { ... },
  "stock_bajo": false
}
```

---

### PUT `/productos/{id}`
Actualiza un producto. Todos los campos son opcionales.

**Body:**
```json
{
  "precio_venta": 15.00,
  "stock_minimo": 30,
  "activo": false
}
```

---

### DELETE `/productos/{id}`
Elimina un producto.

**Respuesta 200:** `{ "message": "Producto eliminado exitosamente" }`

---

## 7. Movimientos de Stock

### GET `/movimientos-stock`
Lista todos los movimientos. Soporta filtros por query params.

**Query params (todos opcionales):**

| Param | Tipo | Descripción |
|-------|------|-------------|
| producto_id | integer | Filtrar por producto |
| tipo | string | `entrada`, `salida` o `ajuste` |
| desde | date | Fecha de inicio `YYYY-MM-DD` |
| hasta | date | Fecha de fin `YYYY-MM-DD` |

**Respuesta 200:**
```json
{
  "message": "Movimientos obtenidos exitosamente",
  "total": 50,
  "movimientos": [
    {
      "id": 1,
      "producto": { "id": 1, "nombre": "Amoxicilina 500mg", "codigo": "AMX500" },
      "user": { "id": 1, "name": "Admin" },
      "tipo": "entrada",
      "cantidad": 100,
      "stock_anterior": 50,
      "stock_nuevo": 150,
      "precio_unitario": 5.50,
      "motivo": "compra",
      "referencia": "OC-001",
      "created_at": "2026-03-08T10:00:00"
    }
  ]
}
```

---

### POST `/movimientos-stock`
Registra un movimiento manual de stock y actualiza el stock del producto.

**Body:**
```json
{
  "producto_id": 1,
  "tipo": "entrada",
  "cantidad": 50,
  "precio_unitario": 5.50,
  "motivo": "Compra a proveedor",
  "referencia": "OC-2026-001"
}
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| producto_id | integer | ✅ | |
| tipo | string | ✅ | `entrada` suma stock, `salida` resta, `ajuste` fija el valor directo |
| cantidad | integer | ✅ | ≥ 1 |
| precio_unitario | numeric | ❌ | |
| motivo | string | ❌ | |
| referencia | string | ❌ | Ej: número de orden de compra |

**Respuesta 201:**
```json
{
  "message": "Movimiento registrado exitosamente",
  "movimiento": { ... },
  "stock_anterior": 100,
  "stock_nuevo": 150
}
```

**Respuesta 422:** Stock insuficiente para salidas.

---

### GET `/movimientos-stock/producto/{id}`
Historial completo de movimientos de un producto específico.

**Respuesta 200:**
```json
{
  "producto": { "id": 1, "nombre": "Amoxicilina 500mg", "stock_actual": 150 },
  "total": 10,
  "movimientos": [ ... ]
}
```

---

### GET `/movimientos-stock/{id}`
Retorna un movimiento por ID.

---

## 8. Ventas

### GET `/ventas`
Lista todas las ventas. Soporta filtros por query params.

**Query params (todos opcionales):**

| Param | Tipo | Descripción |
|-------|------|-------------|
| estado | string | `pendiente`, `completada` o `anulada` |
| cliente_id | integer | Filtrar por cliente |
| trabajador_id | integer | Filtrar por trabajador |
| desde | date | Fecha de inicio `YYYY-MM-DD` |
| hasta | date | Fecha de fin `YYYY-MM-DD` |

**Respuesta 200:**
```json
{
  "message": "Ventas obtenidas exitosamente",
  "total": 10,
  "ventas": [
    {
      "id": 1,
      "cliente": { "id": 1, "nombre_completo": "Ana Torres" },
      "trabajador": { "id": 1, "nombre_completo": "Carlos Ramos" },
      "items": [
        {
          "producto": "Amoxicilina 500mg",
          "codigo": "AMX500",
          "cantidad": 2,
          "precio_unitario": 12.00,
          "subtotal": 24.00
        }
      ],
      "descuento": 0,
      "total": 24.00,
      "estado": "completada",
      "observaciones": null,
      "fecha": "2026-03-08 10:30:00"
    }
  ]
}
```

---

### POST `/ventas`
Registra una venta completa. Valida stock antes de procesar y descuenta automáticamente.

**Body:**
```json
{
  "cliente_id": 1,
  "trabajador_id": 1,
  "descuento": 5.00,
  "observaciones": "Cliente frecuente",
  "items": [
    {
      "producto_id": 1,
      "cantidad": 2,
      "precio_unitario": 12.00
    },
    {
      "producto_id": 3,
      "cantidad": 1
    }
  ]
}
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| cliente_id | integer | ✅ | |
| trabajador_id | integer | ✅ | |
| descuento | numeric | ❌ | Monto fijo a descontar del total |
| observaciones | string | ❌ | |
| items | array | ✅ | Mínimo 1 ítem |
| items[].producto_id | integer | ✅ | |
| items[].cantidad | integer | ✅ | ≥ 1 |
| items[].precio_unitario | numeric | ❌ | Si no se envía, usa el precio de venta del producto |

**Respuesta 201:** Venta con todos los detalles.
**Respuesta 422:** Stock insuficiente para algún producto.

---

### GET `/ventas/{id}`
Retorna el detalle completo de una venta.

---

### PATCH `/ventas/{id}/anular`
Anula una venta y revierte automáticamente el stock de todos los productos.

**Respuesta 200:** `{ "message": "Venta anulada y stock revertido exitosamente" }`
**Respuesta 422:** `{ "message": "La venta ya está anulada" }`

---

## Códigos de Respuesta HTTP

| Código | Significado |
|--------|-------------|
| 200 | OK — solicitud exitosa |
| 201 | Created — recurso creado exitosamente |
| 401 | Unauthorized — token inválido o ausente |
| 404 | Not Found — recurso no encontrado |
| 409 | Conflict — acción no permitida (ej: eliminar con dependencias) |
| 422 | Unprocessable — validación fallida o regla de negocio violada |
| 500 | Server Error — error interno |

---

## Resumen de Endpoints

| Módulo | Método | Endpoint |
|--------|--------|----------|
| Auth | POST | `/auth/register` |
| Auth | POST | `/auth/login` |
| Auth | GET | `/auth/me` |
| Auth | POST | `/auth/logout` |
| Auth | POST | `/auth/refresh` |
| Categorías | GET | `/categorias` |
| Categorías | POST | `/categorias` |
| Categorías | GET | `/categorias/{id}` |
| Categorías | PUT | `/categorias/{id}` |
| Categorías | DELETE | `/categorias/{id}` |
| Clientes | GET | `/clientes` |
| Clientes | POST | `/clientes` |
| Clientes | GET | `/clientes/{id}` |
| Clientes | PUT | `/clientes/{id}` |
| Clientes | DELETE | `/clientes/{id}` |
| Trabajadores | GET | `/trabajadores` |
| Trabajadores | POST | `/trabajadores` |
| Trabajadores | GET | `/trabajadores/{id}` |
| Trabajadores | PUT | `/trabajadores/{id}` |
| Trabajadores | DELETE | `/trabajadores/{id}` |
| Proveedores | GET | `/proveedores` |
| Proveedores | POST | `/proveedores` |
| Proveedores | GET | `/proveedores/{id}` |
| Proveedores | PUT | `/proveedores/{id}` |
| Proveedores | DELETE | `/proveedores/{id}` |
| Productos | GET | `/productos` |
| Productos | POST | `/productos` |
| Productos | GET | `/productos/stock-bajo` |
| Productos | GET | `/productos/por-vencer` |
| Productos | GET | `/productos/{id}` |
| Productos | PUT | `/productos/{id}` |
| Productos | DELETE | `/productos/{id}` |
| Movimientos | GET | `/movimientos-stock` |
| Movimientos | POST | `/movimientos-stock` |
| Movimientos | GET | `/movimientos-stock/producto/{id}` |
| Movimientos | GET | `/movimientos-stock/{id}` |
| Ventas | GET | `/ventas` |
| Ventas | POST | `/ventas` |
| Ventas | GET | `/ventas/{id}` |
| Ventas | PATCH | `/ventas/{id}/anular` |
