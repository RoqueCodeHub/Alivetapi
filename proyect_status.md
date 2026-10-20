# PLAN DE DESARROLLO — SISTEMA DE GESTIÓN VETERINARIA

> **Documento maestro para Cursor**
>
> Stack definido: **Laravel API REST + Angular + MySQL**
>
> Despliegue objetivo: **hosting compartido con cPanel, PHP 8**
>
> Contexto: **Perú / Soles (S/)**
>
> **Regla principal:** Cursor debe trabajar UNA ETAPA a la vez. No implementar módulos de etapas posteriores hasta que la etapa actual cumpla todos sus criterios de terminado.

---

# 0. REGLAS PARA CURSOR

## 0.1 Forma de trabajo

Antes de modificar código:

1. Revisar la estructura actual del proyecto.
2. Revisar archivos existentes y reutilizar lo que ya funciona.
3. No eliminar funcionalidades existentes sin justificarlo.
4. No implementar funcionalidades de etapas posteriores.
5. Crear migraciones, modelos, servicios, controladores, requests, resources, policies y pruebas según corresponda.
6. Mantener separación entre Backend Laravel y Frontend Angular.
7. Toda lógica importante debe estar en servicios, no en controladores.
8. Los controladores deben ser delgados.
9. Validar autorización mediante Policies/permisos.
10. No guardar secretos en el código.
11. Ejecutar pruebas antes de marcar una tarea como terminada.
12. Actualizar `PROJECT_STATUS.md` al finalizar cada tarea.

## 0.2 Arquitectura backend

Laravel:

- Controller
- FormRequest
- Service
- API Resource
- Policy
- Model
- Migration
- Seeder
- Tests

API versionada:

```text
/api/v1/...
```

Autenticación:

```text
Laravel Sanctum
```

Roles/permisos:

```text
spatie/laravel-permission
```

Excel:

```text
maatwebsite/excel
```

PDF:

```text
dompdf
```

## 0.3 Arquitectura frontend

Angular:

- módulos por funcionalidad
- lazy loading
- AuthGuard
- RoleGuard
- HttpInterceptor
- servicios para API
- formularios reactivos
- componentes reutilizables
- tablas, filtros y diálogos mediante Angular Material o PrimeNG

## 0.4 Regla crítica de inventario

El stock **NO se modifica directamente**.

Toda entrada o salida debe generar un registro en:

```text
stock_movements
```

El sistema debe poder reconstruir el historial del stock mediante el kardex.

Las ventas deben guardar el costo y precio existentes en el momento de la venta.

## 0.5 Concurrencia

El sistema debe soportar varios usuarios simultáneamente.

Para operaciones críticas de stock:

- transacciones de BD
- `lockForUpdate()`
- validación de stock en servidor
- idempotency key para creación de ventas

---

# 1. ETAPA 0 — DEFINICIÓN Y PREPARACIÓN

## Objetivo

Dejar las decisiones técnicas mínimas confirmadas antes de programar.

## Tareas

### E0-T01 — Confirmar entorno

Verificar:

- versión exacta de PHP del hosting
- versión disponible de MySQL
- extensiones PHP
- posibilidad de crear subdominios
- Document Root personalizado
- phpMyAdmin
- Cron Jobs
- SSL
- límites de memoria
- `max_execution_time`

### E0-T02 — Definir versiones

Según PHP disponible, fijar:

- versión Laravel
- versión Angular
- versión Node/NPM
- versión MySQL

No elegir versiones incompatibles con el hosting.

### E0-T03 — Definir repositorios

Separar:

```text
veterinaria-backend
veterinaria-frontend
```

Definir:

- rama principal
- rama de desarrollo
- convenciones de commits
- `.gitignore`
- `.env.example`

### E0-T04 — Definir estructura inicial

Backend:

```text
app/
config/
database/
routes/
tests/
```

Frontend:

```text
src/app/
core/
shared/
features/
```

## Criterio de terminado

- Versiones confirmadas.
- Repositorios creados.
- Backend y frontend arrancan localmente.
- `.env.example` documentado.
- No existen credenciales reales en Git.

---

# 2. ETAPA 1 — FUNDAMENTOS DEL SISTEMA

## Objetivo

Construir la base técnica sobre la que funcionarán todos los módulos.

## Tareas

### E1-T01 — Crear Laravel API

- instalar Laravel
- configurar conexión MySQL
- configurar API
- configurar `/api/v1`
- respuesta JSON uniforme
- manejo global de errores

### E1-T02 — Configuración de seguridad

- CORS
- `.env`
- APP_KEY
- HTTPS preparado
- APP_DEBUG=false para producción

### E1-T03 — Instalar autenticación

Instalar y configurar:

```text
Laravel Sanctum
```

### E1-T04 — Instalar permisos

Instalar:

```text
spatie/laravel-permission
```

### E1-T05 — Preparar Angular

- crear aplicación Angular
- routing
- estructura `core/shared/features`
- interceptor HTTP
- manejo uniforme de errores
- configuración de ambientes

### E1-T06 — Primera conexión

Angular debe poder consultar Laravel.

Crear endpoint:

```text
GET /api/v1/health
```

## Criterio de terminado

- Laravel responde.
- Angular consume Laravel.
- MySQL conectado.
- API versionada.
- Manejo de errores funcionando.
- Pruebas básicas funcionando.

---

# 3. ETAPA 2 — AUTENTICACIÓN, USUARIOS Y ROLES

## Objetivo

Permitir acceso seguro y diferenciar Administrador y Operador.

## Roles que deben existir desde el inicio

```text
Administrador
Operador de tienda
Cajero
Almacenero
Veterinario
Groomer / peluquero
Recepcionista
Contador
Auditor
Cliente
Soporte técnico
```

Inicialmente activos:

```text
Administrador
Operador de tienda
```

Los demás quedan preparados para el futuro.

## Tareas

### E2-T01 — Modelo de usuarios

Implementar usuarios y estados:

- activo
- inactivo

### E2-T02 — Roles

Crear roles mediante seeder.

### E2-T03 — Permisos

Crear permisos granulares.

Ejemplos:

```text
users.view
users.create
users.update
users.delete

products.view
products.create
products.update

inventory.view
inventory.adjust
inventory.count

purchases.view
purchases.create

sales.view
sales.create
sales.void

cash.open
cash.close

costs.view
reports.view
reports.view_all
```

### E2-T04 — Login

Implementar:

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
```

### E2-T05 — Seguridad de login

- throttle
- validación
- expiración/revocación de tokens
- mensajes de error seguros

### E2-T06 — CRUD de usuarios

- listar
- crear
- editar
- activar/desactivar
- asignar roles

### E2-T07 — Perfil

- ver perfil
- cambiar contraseña

### E2-T08 — Angular

Crear:

- Login
- AuthService
- AuthGuard
- RoleGuard
- interceptor
- menú dinámico por permisos
- logout

## Criterio de terminado

Administrador y Operador pueden iniciar sesión simultáneamente.

Cada uno ve únicamente las opciones autorizadas.

Crear un usuario con otro rol no requiere modificar código.

---

# 4. ETAPA 3 — CATÁLOGO DE PRODUCTOS

## Objetivo

Crear la estructura de productos, categorías y variantes.

## Decisión de modelo

```text
Producto
   └── Variantes / SKU
```

Ejemplo:

```text
Producto: Bozal

Variantes:
- Talla S
- Talla M
- Talla L
```

## Categorías iniciales

```text
Medicamentos
  - antibióticos
  - antiinflamatorios
  - antiparasitarios internos
  - antiparasitarios externos
  - vitaminas y suplementos
  - otros medicamentos

Higiene y cuidado
Accesorios
Ropa
Camas
Alimentos
Herramientas e instrumental
```

Las categorías deben ser editables y permitir `parent_id`.

## Tareas

### E3-T01 — Categorías

CRUD y árbol.

### E3-T02 — Marcas

CRUD.

### E3-T03 — Productos

Campos principales:

```text
name
description
category_id
brand_id
species
type
requires_batch
active
```

### E3-T04 — Variantes

Campos principales:

```text
product_id
sku
barcode
presentation
unit
sale_unit
units_per_box
cost_price
sale_price
min_stock
needs_review
review_note
```

### E3-T05 — Búsqueda

Filtros:

- nombre
- SKU
- código de barras
- categoría
- especie
- activo/inactivo
- sin precio
- pendientes de revisión

### E3-T06 — Angular

Crear pantallas:

- categorías
- marcas
- productos
- variantes
- detalle de producto
- búsqueda rápida

## Criterio de terminado

Se pueden crear y editar productos y variantes.

El producto no se elimina físicamente cuando tiene historial.

---

# 5. ETAPA 4 — MIGRACIÓN DEL INVENTARIO EXISTENTE

## Objetivo

Cargar el inventario real desde Excel sin perder información dudosa.

## Datos conocidos del inventario

El documento original reporta:

- 247 filas en 10 categorías.
- Hoja separada de Antiparasitarios: 25 productos.
- Hoja de Vitaminas.
- 127 filas con fecha de vencimiento.
- 73 filas con observaciones/dudas.
- Existen productos vencidos o próximos a vencer.

## Tareas

### E4-T01 — Plantilla de importación

Normalizar columnas:

```text
categoría
producto
presentación
cantidad
unidad
F.V.
costo
precio
observaciones
```

### E4-T02 — Normalización

Separar:

```text
producto
variante
```

Ejemplo:

```text
Biomec 1% L.A. — 100 mL
```

debe convertirse en:

```text
Producto: Biomec 1%
Variante: L.A. — 100 mL
```

### E4-T03 — Fechas

Convertir:

```text
agosto-2028
07-2028
```

al último día del mes:

```text
2028-08-31
2028-07-31
```

### E4-T04 — `needs_review`

Marcar como revisión cuando:

- observación existente
- cantidad vacía
- talla no escrita
- precio dudoso
- información incompleta

### E4-T05 — Importador

Crear:

```text
POST /api/v1/import/inventory
```

Debe:

- crear categorías
- crear productos
- crear variantes
- crear lote
- crear movimiento inicial
- registrar vencimiento
- marcar dudas
- devolver resumen

### E4-T06 — Reporte de importación

Mostrar:

```text
filas procesadas
filas importadas
filas rechazadas
filas pendientes de revisión
errores
```

### E4-T07 — Pantalla de pendientes

Crear:

```text
Pendientes de revisión
```

Permitir corregir los registros antes de la puesta en marcha.

## Criterio de terminado

El inventario se puede importar sin perder las observaciones.

El sistema identifica claramente qué registros requieren revisión.

---

# 6. ETAPA 5 — INVENTARIO, LOTES Y KARDEX

## Objetivo

Controlar stock real, lotes y vencimientos.

## Modelo principal

```text
Variant
   ├── Batch
   └── StockMovement
```

## Tareas

### E5-T01 — Lotes

Campos:

```text
variant_id
batch_code
expiry_date
quantity
cost
```

### E5-T02 — Movimientos

Tipos:

```text
compra
venta
ajuste
devolución
merma
vencido
inicial
```

### E5-T03 — Regla de stock

Nunca modificar stock manualmente.

Toda modificación genera `stock_movements`.

### E5-T04 — Kardex

Mostrar:

- fecha
- usuario
- movimiento
- cantidad
- saldo
- motivo
- referencia

### E5-T05 — Ajustes

Permitir:

```text
entrada
salida
```

con motivo obligatorio.

Todo ajuste queda auditado.

### E5-T06 — Inventario físico

Flujo:

```text
crear conteo
→ contar
→ comparar
→ mostrar diferencias
→ aprobar
→ generar ajustes
```

### E5-T07 — Alertas

Crear:

- stock mínimo
- vence en 30 días
- vence en 60 días
- vence en 90 días
- vencido

### E5-T08 — FEFO

Las ventas deben seleccionar primero el lote que vence antes.

### E5-T09 — Conversión de unidades

Ejemplo:

```text
1 caja = 42 sobres
```

Permitir abrir una caja y convertir a unidades de venta.

### E5-T10 — Exportación

Exportar inventario y kardex a Excel.

## API

```text
GET  /api/v1/stock
GET  /api/v1/variants/{id}/kardex
POST /api/v1/stock/adjustments
POST /api/v1/stock/counts
GET  /api/v1/alerts/expiring
GET  /api/v1/alerts/low-stock
```

## Criterio de terminado

Se puede registrar un ajuste, verlo en el kardex y recibir alertas de vencimiento.

---

# 7. ETAPA 6 — PROVEEDORES Y COMPRAS

## Objetivo

Registrar compras y alimentar el inventario.

## Tareas

### E6-T01 — Proveedores

Campos:

```text
name
ruc
phone
email
```

### E6-T02 — Compras

Registrar:

- proveedor
- comprobante
- fecha
- total
- productos
- lote
- vencimiento
- costo

### E6-T03 — Confirmación

Al confirmar compra:

```text
compra
→ lote
→ stock
→ movimiento kardex
→ actualización de costo
```

### E6-T04 — Historial de costos

Guardar historial de precios de compra.

### E6-T05 — Cuentas por pagar

Opcional. No bloquear el resto del sistema.

## API

```text
GET/POST/PUT /api/v1/suppliers
GET/POST/PUT /api/v1/purchases
POST /api/v1/purchases/{id}/confirm
```

## Criterio de terminado

Una compra confirmada aumenta correctamente el stock del lote correspondiente.

---

# 8. ETAPA 7 — POS Y CAJA

## Objetivo

Llegar al primer MVP operativo del negocio.

> **IMPORTANTE:** al terminar esta etapa debe ser posible reemplazar el cuaderno por el sistema para inventario + ventas.

## Tareas

### E7-T01 — Sesión de caja

Apertura:

```text
monto inicial
usuario
fecha/hora
```

Cierre:

```text
monto esperado
monto contado
diferencia
```

### E7-T02 — POS

Debe permitir:

- búsqueda por nombre
- búsqueda por SKU
- código de barras
- carrito
- cantidad
- descuentos
- cliente opcional

### E7-T03 — Pagos

Métodos:

```text
efectivo
Yape
Plin
tarjeta
transferencia
```

Permitir pagos mixtos.

Calcular vuelto automáticamente.

### E7-T04 — Venta

Crear venta dentro de transacción.

Debe:

- validar stock
- aplicar FEFO
- descontar stock
- guardar costo histórico
- guardar precio histórico
- generar movimientos

### E7-T05 — Idempotencia

Toda creación de venta debe aceptar:

```text
idempotency_key
```

para evitar ventas duplicadas.

### E7-T06 — Ticket

Generar comprobante/ticket imprimible.

### E7-T07 — Anulación

Solo administrador.

Debe exigir:

```text
motivo
```

y devolver stock mediante movimiento.

### E7-T08 — Devoluciones

Registrar devolución y reingreso correspondiente al stock.

### E7-T09 — Servicios

Preparar el carrito para vender servicios además de productos.

### E7-T10 — Permisos

Operador/cajero:

```text
ver sus ventas
```

Administrador:

```text
ver todas
```

## API

```text
POST /api/v1/cash-sessions/open
POST /api/v1/cash-sessions/{id}/close

POST /api/v1/sales
POST /api/v1/sales/{id}/void

GET /api/v1/sales
GET /api/v1/sales/{id}/receipt
```

## Criterio de terminado

Dos usuarios pueden vender simultáneamente sin generar stock negativo.

Pueden abrir/cerrar caja y verificar diferencias.

---

# 9. ETAPA 8 — CLIENTES Y MASCOTAS

## Objetivo

Relacionar ventas y futuros servicios con clientes y mascotas.

## Tareas

### E8-T01 — Clientes

Campos:

```text
DNI/RUC
nombre
teléfono
WhatsApp
```

### E8-T02 — Mascotas

Campos:

```text
nombre
especie
raza
sexo
fecha de nacimiento
peso
alergias
foto
```

### E8-T03 — Historial

Mostrar compras del cliente.

### E8-T04 — POS

Buscar cliente rápidamente desde el POS.

## API

```text
GET/POST/PUT /api/v1/customers
GET/POST/PUT /api/v1/pets
GET /api/v1/customers/{id}/purchases
```

## Criterio de terminado

Una venta puede asociarse a un cliente y consultarse posteriormente.

---

# 10. ETAPA 9 — REPORTES Y DASHBOARD

## Objetivo

Dar al administrador información para controlar ventas e inventario.

## Dashboard administrador

Mostrar:

- ventas del día
- ventas de semana
- ventas de mes
- comparación con período anterior
- productos vendidos por día
- top productos
- ventas por categoría
- ventas por cajero
- ventas por método de pago
- ventas por hora
- utilidad
- productos de baja rotación
- valorización del inventario
- productos próximos a vencer

## Dashboard operador/cajero

Mostrar:

- ventas del día
- caja
- buscador de stock

## Tareas

### E9-T01 — Reportes API

```text
GET /api/v1/reports/sales-by-day
GET /api/v1/reports/top-products
GET /api/v1/reports/by-category
GET /api/v1/reports/by-cashier
GET /api/v1/reports/profit
GET /api/v1/reports/slow-movers
GET /api/v1/reports/inventory-value
```

### E9-T02 — Filtros

Permitir:

```text
fecha inicio
fecha fin
categoría
cajero
```

### E9-T03 — Gráficos

Implementar gráficos en Angular.

### E9-T04 — Resumen diario

Crear:

```text
daily_sales_summary
```

para reducir carga del hosting.

### E9-T05 — Exportación

Excel y PDF.

## Criterio de terminado

El administrador puede consultar qué productos se vendieron más en un período y filtrar por categoría/fecha.

---

# 11. ETAPA 10 — CLÍNICA VETERINARIA

## Objetivo

Convertir el sistema comercial en sistema de gestión veterinaria.

## Tareas

### E10-T01 — Servicios

Catálogo:

```text
consulta
vacunación
desparasitación
baño
peluquería
cirugía
hospitalización
laboratorio
```

### E10-T02 — Agenda

Citas por:

- veterinario
- groomer

Estados:

```text
pendiente
atendida
cancelada
```

### E10-T03 — Historia clínica

Registrar:

- consulta
- diagnóstico
- tratamiento
- peso
- adjuntos
- fotos
- exámenes

### E10-T04 — Vacunación

Carnet de vacunas.

### E10-T05 — Desparasitación

Control de próximas fechas.

### E10-T06 — Consumo de insumos

Una atención puede consumir:

```text
jeringa
vacuna
medicamento
otros insumos
```

El consumo debe descontar stock.

### E10-T07 — Cobro

El servicio y sus insumos deben poder pasar al POS.

## API

```text
GET/POST/PUT /api/v1/services
GET/POST/PUT /api/v1/appointments
GET/POST/PUT /api/v1/medical-records
GET/POST/PUT /api/v1/vaccinations
```

## Criterio de terminado

Una consulta con vacuna:

```text
consulta
→ historia clínica
→ vacunación
→ descuento de vacuna
→ cobro en caja
```

---

# 12. ETAPA 11 — RECORDATORIOS Y NOTIFICACIONES

## Objetivo

Automatizar recordatorios.

## Tareas

### E11-T01 — Scheduler

Configurar Laravel Scheduler.

### E11-T02 — Recordatorios

Generar recordatorios para:

- vacunas
- desparasitación
- citas

### E11-T03 — Notificaciones

Correo.

WhatsApp mediante enlace o proveedor externo, si posteriormente se decide integrarlo.

### E11-T04 — Alertas internas

Para administrador:

- vencimientos
- stock mínimo
- diferencias de caja

## Criterio de terminado

El scheduler ejecuta correctamente las tareas programadas.

---

# 13. ETAPA 12 — AUDITORÍA, SEGURIDAD Y RESPALDOS

## Objetivo

Asegurar trazabilidad y recuperación del sistema.

## Tareas

### E12-T01 — Auditoría

Registrar:

```text
usuario
acción
modelo
id
valor anterior
valor nuevo
IP
fecha
```

Tabla:

```text
audit_logs
```

### E12-T02 — Inicio de sesión

Registrar accesos.

### E12-T03 — Respaldo BD

Backup automático.

### E12-T04 — Restauración

Realizar prueba real de restauración.

### E12-T05 — Seguridad

Revisar:

- validaciones
- autorización
- SQL injection
- XSS
- CSRF según arquitectura
- rate limiting
- exposición de archivos
- CORS
- HTTPS
- `.env`

## Criterio de terminado

Existe respaldo verificable y se ha probado una restauración.

---

# 14. ETAPA 13 — DESPLIEGUE EN CPANEL

## Objetivo

Llevar el sistema a producción sin SSH.

## Tareas

### E13-T01 — API

En cPanel:

- configurar PHP
- extensiones
- MySQL
- subdominio API
- Document Root
- SSL

### E13-T02 — Backend

Subir Laravel sin:

```text
.env
node_modules
logs
```

Configurar `.env` de producción.

### E13-T03 — Base de datos

Inicialmente:

```text
migraciones/seeders local
→ exportar SQL
→ importar mediante phpMyAdmin
```

### E13-T04 — Deploy posterior

Preparar mecanismo protegido para migraciones futuras.

### E13-T05 — Cron

Configurar:

```text
schedule:run
```

cada minuto.

### E13-T06 — Queue

Usar driver database si es necesario.

### E13-T07 — Angular

Construir:

```text
ng build --configuration production
```

Subir `dist/`.

Configurar `.htaccess` para rutas Angular.

### E13-T08 — Backups

Configurar:

- Backup Wizard si está disponible
- backup diario de MySQL
- conservación mínima de 7 días
- descarga periódica

## Criterio de terminado

Backend y frontend funcionan en el dominio real.

Se puede iniciar sesión, consultar inventario y realizar los flujos desarrollados hasta la etapa actual.

---

# 15. ETAPA 14 — FACTURACIÓN ELECTRÓNICA

## Objetivo

Integrar SUNAT cuando el sistema comercial ya esté estable.

## Tareas

### E14-T01

Preparar integración con PSE/OSE.

### E14-T02

Series y correlativos.

### E14-T03

IGV.

### E14-T04

Boletas y facturas.

### E14-T05

Notas de crédito.

### E14-T06

Validar requisitos vigentes con contador.

## Criterio de terminado

La emisión electrónica funciona mediante proveedor autorizado y las respuestas quedan registradas.

---

# 16. ETAPA 15 — PORTAL DEL CLIENTE

## Objetivo

Crear portal opcional para clientes.

## Tareas

- login cliente
- mascotas
- carnet de vacunas
- próximas citas
- historial de compras
- solicitud de citas

## Criterio de terminado

Un cliente puede consultar su información sin acceder a información de otros clientes.

---

# 17. MODELO DE DATOS PRINCIPAL

Relaciones:

```text
categories
    ↓
products
    ↓
variants
    ↓
batches
    ↓
stock_movements

suppliers
    ↓
purchases
    ↓
purchase_items

users
    ↓
cash_sessions
    ↓
sales
    ↓
sale_items
    ↓
variants

customers
    ↓
pets
```

## Tablas principales

### categories

```text
id
parent_id
name
slug
active
```

### products

```text
id
category_id
brand_id
name
description
species
type
requires_batch
active
```

### variants

```text
id
product_id
sku
barcode
presentation
unit
sale_unit
units_per_box
cost_price
sale_price
min_stock
needs_review
review_note
```

### batches

```text
id
variant_id
batch_code
expiry_date
quantity
cost
```

### stock_movements

```text
id
variant_id
batch_id
type
quantity
reason
user_id
reference
created_at
```

### suppliers

```text
id
name
ruc
phone
email
```

### purchases

```text
id
supplier_id
comprobante
fecha
total
```

### purchase_items

```text
purchase_id
variant_id
batch_id
quantity
cost
```

### cash_sessions

```text
id
user_id
opened_at
closed_at
opening_amount
expected_amount
counted_amount
difference
```

### sales

```text
id
cash_session_id
user_id
customer_id
series
number
subtotal
discount
total
payment_status
status
idempotency_key
```

### sale_items

```text
sale_id
variant_id
batch_id
quantity
unit_price
unit_cost
discount
```

### payments

```text
sale_id
method
amount
```

### customers

Datos del cliente.

### pets

```text
customer_id
species
breed
birth_date
weight
allergies
```

### audit_logs

```text
user_id
action
model
model_id
before
after
ip
created_at
```

---

# 18. FLUJOS CRÍTICOS QUE CURSOR DEBE PROBAR

## Flujo A — Login

```text
usuario
→ login
→ token
→ Angular guarda sesión
→ menú según permisos
```

## Flujo B — Compra

```text
proveedor
→ compra
→ confirmar
→ lote
→ stock
→ kardex
```

## Flujo C — Venta

```text
abrir caja
→ buscar producto
→ agregar carrito
→ seleccionar lote FEFO
→ validar stock
→ pagar
→ registrar venta
→ descontar stock
→ kardex
→ ticket
```

## Flujo D — Anulación

```text
venta
→ administrador
→ motivo
→ anular
→ reingreso stock
→ kardex
→ auditoría
```

## Flujo E — Inventario físico

```text
crear conteo
→ contar
→ comparar
→ aprobar diferencia
→ ajuste
→ kardex
```

## Flujo F — Clínica

```text
cliente
→ mascota
→ cita
→ consulta
→ diagnóstico
→ vacuna
→ descuento stock
→ cobro
→ historia clínica
```

---

# 19. PRIORIDAD DE DESARROLLO

Cursor debe seguir estrictamente este orden:

```text
ETAPA 0  → Preparación
ETAPA 1  → Fundamentos
ETAPA 2  → Usuarios/Roles
ETAPA 3  → Catálogo
ETAPA 4  → Migración
ETAPA 5  → Inventario
ETAPA 6  → Compras
ETAPA 7  → POS/Caja
ETAPA 8  → Clientes/Mascotas
ETAPA 9  → Reportes
ETAPA 10 → Clínica
ETAPA 11 → Notificaciones
ETAPA 12 → Auditoría/Seguridad
ETAPA 13 → Producción cPanel
ETAPA 14 → Facturación
ETAPA 15 → Portal cliente
```

---

# 20. MVP

El primer objetivo real del negocio es:

```text
ETAPA 1
+
ETAPA 2
+
ETAPA 3
+
ETAPA 4
+
ETAPA 5
+
ETAPA 6
+
ETAPA 7
```

Al completar la ETAPA 7 debe existir:

```text
Usuarios
+
Productos
+
Inventario
+
Lotes
+
Vencimientos
+
Kardex
+
Compras
+
POS
+
Caja
+
Ventas
```

Esto constituye el **MVP operativo**.

Las etapas posteriores agregan capacidades de análisis, clínica, automatización, seguridad avanzada y facturación.

---

# 21. FORMATO OBLIGATORIO PARA CADA TAREA DE CURSOR

Cada vez que se solicite una tarea a Cursor, utilizar:

```text
ETAPA: E[X]
TAREA: E[X]-T[XX]

OBJETIVO:
[qué se quiere conseguir]

ALCANCE:
[qué debe modificar]

NO HACER:
[qué queda fuera]

REQUISITOS:
[reglas técnicas]

ARCHIVOS ESPERADOS:
[archivos que probablemente deberán crearse/modificarse]

PRUEBAS:
[qué debe probar]

CRITERIO DE TERMINADO:
[condición objetiva]

AL FINAL:
1. Ejecuta las pruebas.
2. Corrige errores encontrados.
3. Resume archivos modificados.
4. Resume endpoints creados/modificados.
5. Indica cómo probar manualmente.
6. Actualiza PROJECT_STATUS.md.
7. No continúes con la siguiente tarea.
```

---

# 22. ARCHIVO DE ESTADO DEL PROYECTO

Crear en la raíz:

```text
PROJECT_STATUS.md
```

Formato:

```markdown
# PROJECT STATUS

## Etapa actual
E1

## Tarea actual
E1-T01

## Estado
EN PROGRESO

## Última tarea terminada
-

## Próxima tarea
-

## Backend
- [ ] Laravel configurado
- [ ] API funcionando
- [ ] MySQL conectado

## Frontend
- [ ] Angular configurado
- [ ] API conectada

## Tests
- [ ] Unitarios
- [ ] Feature
- [ ] Frontend

## Deploy
- [ ] Local
- [ ] Pruebas
- [ ] Producción

## Decisiones pendientes
-

## Problemas conocidos
-
```

Cursor debe mantener este archivo actualizado.

---

# 23. PRIMER PROMPT PARA CURSOR

Copiar este bloque en Cursor:

```text
Lee el archivo PLAN_DESARROLLO_VETERINARIA.md y úsalo como documento maestro del proyecto.

NO programes todavía todo el sistema.

Primero:

1. Inspecciona el proyecto actual.
2. Identifica si ya existe Backend Laravel y Frontend Angular.
3. Identifica versiones instaladas.
4. Revisa estructura de carpetas.
5. Revisa configuración existente.
6. No borres ni reemplaces código sin justificarlo.
7. Compara el estado actual contra la ETAPA 0.
8. Crea o actualiza PROJECT_STATUS.md.
9. Genera un diagnóstico de:
   - qué ya existe
   - qué falta
   - qué está incorrecto
   - qué debe modificarse
10. NO avances a la ETAPA 1 hasta terminar este diagnóstico.

Al terminar, espera mi autorización para ejecutar la primera tarea.
```

---

# 24. REGLA PARA LOS SIGUIENTES PROMPTS

Después del diagnóstico, no pedir a Cursor:

```text
"haz todo el sistema"
```

Pedir tareas pequeñas:

```text
Ejecuta E1-T01.

Respeta PLAN_DESARROLLO_VETERINARIA.md.

No avances a E1-T02.

Cuando termines:
- ejecuta pruebas
- corrige errores
- muestra archivos modificados
- muestra endpoints afectados
- actualiza PROJECT_STATUS.md
- espera instrucciones.
```

Luego:

```text
Ejecuta E1-T02.
```

Y así sucesivamente.

---

# 25. REGLA DE CONTROL DEL PROYECTO

El usuario decide cuándo pasar de una etapa a otra.

Cursor NO debe decidir automáticamente que una etapa está terminada.

Para pasar de etapa:

```text
1. Revisar tareas.
2. Ejecutar pruebas.
3. Probar manualmente.
4. Verificar criterios de terminado.
5. Revisar PROJECT_STATUS.md.
6. El usuario autoriza la siguiente etapa.
```

---

# 26. DECISIONES QUE NO DEBEN CAMBIARSE SIN AUTORIZACIÓN

No cambiar sin consultar:

- Laravel + Angular + MySQL.
- API REST.
- `/api/v1`.
- Sanctum.
- Spatie Permission.
- Modelo Producto → Variante.
- Lotes y vencimientos.
- FEFO.
- Kardex mediante `stock_movements`.
- Transacciones para ventas.
- `lockForUpdate()` para concurrencia.
- `idempotency_key`.
- Roles y permisos granulares.
- Soporte para múltiples usuarios.
- Compatibilidad con hosting cPanel.
- Stock sin edición manual.
- costo/precio histórico en `sale_items`.

---

# 27. DECISIONES QUE PUEDEN QUEDAR PARA DESPUÉS

No bloquear el desarrollo inicial por:

- facturación electrónica
- WhatsApp API
- portal del cliente
- cuentas por pagar
- WebSockets
- Redis
- clínica completa
- aplicación móvil
- funcionalidades no incluidas en el MVP

Primero debe funcionar correctamente el núcleo:

```text
usuarios
→ catálogo
→ inventario
→ compras
→ ventas
→ caja
```

---

# 28. DEFINICIÓN FINAL DE "TERMINADO"

Una tarea solo está TERMINADA cuando:

- el código compila;
- las migraciones funcionan;
- las validaciones funcionan;
- los permisos funcionan;
- las pruebas pasan;
- no existen errores críticos conocidos;
- el flujo manual funciona;
- no se rompieron funcionalidades anteriores;
- `PROJECT_STATUS.md` está actualizado.

Una etapa solo está TERMINADA cuando todas sus tareas están terminadas y su criterio de terminado se cumple.

---

# 29. SIGUIENTE ACCIÓN

**No desarrollar todo de una vez.**

La primera acción debe ser:

```text
ETAPA 0
→ E0-T01
→ Confirmar entorno del hosting/cPanel
```

Después:

```text
E0-T02
→ versiones

E0-T03
→ repositorios

E0-T04
→ estructura
```

Luego comenzar:

```text
ETAPA 1
```

y avanzar tarea por tarea.
