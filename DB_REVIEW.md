# Revisión de la base

Una clínica, MySQL. El dinero ya está en `decimal`. Casi todas las relaciones del esquema vivo ya tienen clave foránea. Este corte agrega índices y, si los datos lo permiten, una clave foránea. No une tablas, no borra historial y no cambia la búsqueda del mostrador.

## Aplicado

Migración `2026_11_12_231000_add_hot_path_indexes`. Si el índice ya existe, no se vuelve a crear.

- `sales (status, payment_status, id)` para el listado y los saldos.
- `sales.customer_id`. La migración cuenta ventas cuyo `customer_id` no existe en `clientes`. En esta base ese conteo fue 0, así que crea la clave foránea hacia `clientes` con `nullOnDelete`. Una venta sin cliente sigue siendo válida. Si en otro entorno el conteo es mayor que 0, solo crea el índice `sales_customer_id_index` y no la clave.
- `cash_sessions (user_id, closed_at)` para la caja abierta.
- `batches (variant_id, expiry_date, id)` para el FEFO.
- `batches (expiry_date)` para las alertas de vencimiento.
- `stock_movements (variant_id, created_at, id)` para el kardex.
- `appointments (status, scheduled_at)` para la agenda.
- `vaccinations (next_due_at)` y `dewormings (next_due_at)`. El recordatorio compara la columna `date` directo, así el índice se usa. El conjunto de filas no cambia.
- `audit_logs (model, id)` porque el listado filtra por modelo y ordena por `id`.

## No se migra

- Siguen dos esquemas. La API montada usa `sales`, `sale_items`, `batches` y `stock_movements`. El código sin montar sigue apuntando a `ventas`, `venta_detalles` y `movimientos_stock`. No se copian filas ni se eliminan tablas.
- `users.role_id` y los roles de Spatie siguen los dos.
- La búsqueda del mostrador usa `LIKE '%texto%'`. Un índice normal no la acelera. El texto completo queda fuera porque cambiaría los resultados.
- Auditoría y logins ordenan por `id`, que ya es la clave primaria. No se agrega un índice de `created_at` que esas listas no usan.
- Compras por estado y vencimiento, y recordatorios por `status`, quedan para un corte posterior.
