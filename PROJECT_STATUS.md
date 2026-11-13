# PROJECT STATUS

## Etapa actual

Corte de mostrador y seguridad. No cierra las etapas 0 a 15 de `proyect_status.md`.

## Tarea actual

Venta cobrada, venta a crédito y devolución de lo ya pagado, con los fallos Críticos y Altos corregidos.

## Estado

HECHO en local. El despliegue en cPanel no entra en este corte.

## Última tarea terminada

Mostrador con caja, FEFO, fiado de administrador, abonos y ticket con saldo. La API sin versión dejó de montarse.

## Próxima tarea

Consulta clínica de punta a punta, cuando se autorice el siguiente corte. SUNAT sigue fuera.

## Backend

- [x] Laravel y `/api/v1` con Sanctum
- [x] Permisos Spatie, incluido `sales.credit` solo en Administrador
- [x] Catálogo, inventario, kardex, compras, caja, ventas, clientes y mascotas
- [x] Venta cobrada con caja abierta, FEFO e idempotencia
- [x] Venta a crédito: cliente obligatorio, stock al confirmar, abonos en la caja de quien cobra
- [x] Anulación de deuda solo si no hay abonos. Devolución solo de venta pagada
- [x] Clínica, recordatorios, reportes, auditoría, facturación y portal existen en API
- [ ] Factura electrónica SUNAT en producción
- [ ] La API legacy (`routes/api.php`) sigue en el repo y ya no se carga

## Frontend

- [x] Angular contra `/api/v1`
- [x] Ventas: cobrar de contado con caja; fiar solo con `sales.credit` y cliente
- [x] Caja: lista de saldos y abono para quien tiene `sales.charge`
- [x] El ticket muestra pagado y saldo
- [ ] Paridad con Digitail, ezyVet o Shepherd

## Comparación corta

Sirve para juzgar este corte, no para copiar el producto.

- Digitail y ezyVet unen historia, vacunas, inventario y cobro. Aquí la historia y el cobro clínico ya tienen API; este corte solo deja sólido el mostrador.
- Shepherd prioriza agenda e historia. La agenda existe y queda para el siguiente corte.
- El mostrador peruano ya cobra en efectivo, Yape, Plin, tarjeta y transferencia, y emite un ticket interno. La boleta y la factura SUNAT no se cierran aquí.

## Tests

- [x] Feature de mostrador, clínica y ticket
- [ ] Suite de frontend

## Deploy

- [x] Local
- [ ] cPanel

## Decisiones de este corte

- Una clínica en Perú. No es un SaaS.
- `/api/v1` es la única API que se extiende.
- Crítico, Alto, Medio y Bajo de `OWASP_REVIEW.md` quedan corregidos.
- El fiado descuenta stock al confirmar. Las deudas viejas, sin lote, siguen descontando al cobrarse.
- Sin intereses, vencimiento, límite de crédito ni condonación.

## Problemas conocidos

- Las deudas anteriores a este corte no se reescriben. Si el ítem no tiene lote, el cobro descuenta el stock.
- Medio y Bajo de `OWASP_REVIEW.md` quedaron cerrados: fotos privadas, costo oculto sin `costs.view`, importación por extensión, idempotencia del mismo cajero, CSP y debug apagado en producción.
- Clínica, portal y SUNAT no se ampliaron, salvo el acceso a los adjuntos clínicos.
- Los índices del esquema vivo y lo que no se migra están en `DB_REVIEW.md`.
