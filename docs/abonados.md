# Revisión de abonados y mensualidades

Revisión del 13 de noviembre de 2026 sobre `Subscription`, `Vehicle`, `Client`, `ParkingBilling`, `ParkingService`, `OperationsController`, sus controladores de catálogo y migraciones.

## Modelo y comportamiento comprobado

| Elemento | Implementación |
| --- | --- |
| Relación | Cada abono tiene `client_id`, `vehicle_id`, `rate_id`, fechas, importe y estado lógico; `space_id` es opcional. |
| Validación | El vehículo debe pertenecer al cliente, tener tipo compatible con la tarifa mensual y estar activo. Se rechazan solapamientos y reservas incompatibles. |
| Reserva | La entrada impide ocupar un espacio reservado para otro vehículo por un abono vigente. |
| Tickets | Existe `subscription_id`, pero el flujo actual no lo asigna como cobertura. La salida se cobra con la tarifa seleccionada al entrar. |
| Pagos | Existe `payments.subscription_id`, pero no hay un flujo de interfaz para cobrar, conciliar o anular mensualidades. Guardar `subscriptions.amount` no registra un pago. |
| Cancelación | Los abonos tienen `is_active`; no tienen un estado separado de cancelación. La desactivación impide reconocer vigencia. |

## Mejora segura incluida

Las búsquedas de entrada y las cotizaciones reconocen **informativamente** un abono vigente del vehículo, únicamente para usuarios con `abonados.view`, además del permiso operativo de cada endpoint. Se verifica en servidor:

- Coincidencia exacta de vehículo y de su propietario actual con el cliente del abono.
- Activación de abono, vehículo, cliente, tipo y tarifa mensual compatible.
- Fecha actual dentro del intervalo inclusivo de inicio y vencimiento, en la zona horaria de la aplicación.
- Un único registro válido; si hay varios abonos heredados superpuestos, se informa la ambigüedad sin elegir arbitrariamente.

El aviso indica expresamente que se mantiene el cobro por estancia. La vigencia actual **no acredita pago, cobertura de toda la estancia ni gratuidad**. No se persiste esa consulta como una decisión financiera, no se cambian tickets históricos y no se modifica `ParkingBilling` ni la transacción de `ParkingService`. Los operadores de entrada, cierre y pago siguen registrados como antes.

El contrato AJAX se amplía con `subscription` (`null` sin permiso o sin vehículo; objeto con `status`, `coverage_applied: false` y fechas/ID solo si es válido). Se actualizan ambos consumidores: `entradas.js` y `salidas.js`.

## Cobertura económica pendiente

La exención automática se difiere por la excepción de estabilidad solicitada: completar ese flujo exige cambios coordinados en caja, comprobantes, cierre de tickets y auditoría. No se añade un interruptor que prometa una cobertura que aún no puede aplicar. **En esta entrega no hay una política de cobertura habilitable ni descuento automático.**

Antes de implementar la cobertura, el administrador debe definir explícitamente:

1. Si exige pago confirmado de la mensualidad y cómo se registra o revoca ese pago.
2. Si cubre cualquier espacio compatible o solo el reservado.
3. Si debe cubrir toda la estancia o cómo se liquidan las horas anteriores/posteriores a la vigencia.
4. El efecto de desactivar o modificar el abono durante una estancia.

La futura configuración debe iniciar desactivada, aplicarse de forma explícita a nuevas estancias y conservar una decisión auditable (política, abono, intervalo, motivo y operador). Una salida cubierta debe cerrar el ticket sin inventar un ingreso de efectivo o QR, ni duplicar el pago mensual. Debe revalidarse bajo transacción y bloqueos, con pruebas de concurrencia en MySQL antes de activarla.

## Casos de regresión

`SubscriptionRecognitionTest` comprueba vigencia y límites de fecha, vencimiento, desactivación, propietario incorrecto, otro vehículo del mismo cliente, relaciones inactivas, tarifa incompatible, superposición heredada y permisos. También ejecuta entrada, cotización, cobro normal y rechazo de doble pago con un abono vigente, y verifica que el reconocimiento posterior no cambie el cobro histórico.
