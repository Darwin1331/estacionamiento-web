# Fase 3: entradas, salidas, boletos y caja

## Preparación

No se necesitan nuevas tablas ni migraciones para esta fase: se reutilizan `tickets`, `payments`, `payment_methods` y los catálogos existentes. En la verificación final, `php artisan migrate:status` mostró las siete migraciones aplicadas y ninguna pendiente. Se ejecutó `php artisan migrate` normal; Laravel respondió `Nothing to migrate`. No se ejecutaron seeders sobre MySQL ni se modificaron contraseñas o permisos permanentes.

El Administrador tiene acceso completo. Para habilitar al Operador/Cajero o al Supervisor, abre **Administración → Roles y permisos**, selecciona el rol y configura:

| Función | Permisos |
| --- | --- |
| Abrir Entradas y registrar | `entradas.view`, `entradas.create` |
| Registrar un vehículo desde la entrada | `vehiculos.create` |
| Consultar estancias y cotizar | `salidas.view` |
| Confirmar pago y salida | **Ambos:** `salidas.execute`, `caja.charge` |
| Historial de boletos | `tickets.view` |
| Reimprimir boleto | `tickets.reprint` |
| Historial de pagos y comprobantes | `caja.view` |

No se asignan permisos adicionales automáticamente a los roles existentes. Las rutas comprueban autorización en el servidor; una solicitud sin permisos devuelve HTTP 403. El boleto inicial se entrega a quien registra la entrada, y el comprobante inicial a quien confirma el cobro.

## Una operación completa desde el navegador

1. Inicia sesión como Administrador o con un rol autorizado.
2. En Gestión, verifica que haya un tipo de vehículo, piso, sector, espacio y tarifa activos. El espacio debe estar disponible. Los espacios antiguos sin piso o sector siguen siendo compatibles.
3. Abre **Operaciones → Entradas → Registrar entrada**. Busca una placa. Si no existe, usa **Registrar vehículo nuevo**, guarda en el modal y vuelve a la entrada.
4. Elige el espacio y la tarifa correspondiente al tipo de vehículo. Confirma. El boleto muestra su código único, la hora del servidor y el precio de entrada; puedes imprimirlo desde el modal. El vehículo aparece estacionado y el espacio queda ocupado.
5. Abre **Operaciones → Salidas → Buscar boleto / placa**. Revisa entrada, duración, espacio, tarifa, unidades e importe.
6. Selecciona **Efectivo** o **QR**. Para QR, verifica manualmente la recepción y marca la casilla de confirmación. La referencia es opcional.
7. Pulsa **Confirmar pago y salida** y acepta la confirmación. El sistema guarda el pago, cierra el boleto y libera el espacio en una sola transacción. Se abre el comprobante imprimible.
8. En **Operaciones → Caja y pagos**, consulta la transacción, filtra por fechas/método/boleto/placa/referencia y vuelve a imprimir el comprobante.
9. En **Operaciones → Tickets** puedes consultar también las estancias cerradas.

Todos los registros, búsquedas, confirmaciones e impresiones de este flujo usan modales/AJAX. Las tablas y los indicadores de la página se actualizan después de guardar. El botón Actualizar permite recoger cambios de otros operadores.

## Reglas de cobro y consistencia

- Cálculo en centavos enteros, persistencia `DECIMAL(12,2)` y presentación con dos decimales.
- Se cobra por unidad iniciada, con un mínimo de una unidad: minuto, hora, fracción configurada, día de 24 horas o mes calendario desde la entrada. Exactamente una hora equivale a una unidad; una hora y un segundo equivale a dos.
- Las tarifas mensuales usan aniversarios de la entrada, ajustando al último día cuando el mes es más corto.
- El precio se copia a `tickets.rate_amount` al ingresar. Cambiar el precio del catálogo afecta a entradas futuras.
- Para conservar el cálculo de boletos existentes, el catálogo rechaza cambios de unidad, minutos de fracción o tipo de vehículo en una tarifa ya utilizada. Para esos cambios se crea otra tarifa; no se elimina la anterior.
- Al confirmar el cobro se recalcula en el servidor. Si el importe difiere del que vio el operador, se responde 422: usa **Actualizar cálculo** y confirma nuevamente. El cliente nunca decide el importe cobrado.
- Los abonos continúan en su catálogo. Esta fase cobra la tarifa seleccionada por estancia, sin descontar automáticamente contratos de abono. Un espacio reservado por un abono vigente no se ofrece a otro vehículo.
- Se comprueban vehículo, tipo, cliente, espacio, piso, sector y tarifa. No se admite un vehículo con boleto abierto ni un espacio ocupado, en mantenimiento o desactivado.
- Las entradas bloquean filas del vehículo y espacio con `FOR UPDATE`. Los cobros bloquean el boleto y el espacio. Los conflictos por interbloqueo se reintentan dentro de la transacción.
- Un boleto cerrado/anulado o con pago confirmado no puede cobrarse de nuevo. Una excepción revierte pago, cierre y liberación juntos.
- Los métodos admitidos son los registros activos `Efectivo` y `Transferencia / QR` (también se reconoce `QR`). No hay integración bancaria ni confirmación automática de QR.
- Se conservan los registros históricos. Anulación de boletos y cierre contable de caja continúan pendientes; sus permisos siguen identificados como Próximo. Los reportes PDF/Excel están disponibles desde [la Fase 4](fase-4-reportes.md).

## Verificaciones

```powershell
php artisan test
npm run build
php tests/mysql-phase-three.php
```

La suite usa SQLite en memoria y cubre entrada, duplicados, espacios desactivados/ocupados/reservados, precisión y límites temporales de tarifas, precio histórico, efectivo, QR manual, liberación, doble cobro, rollback y permisos de Spatie.

La comprobación MySQL usa los datos de conexión locales sin mostrarlos. Crea datos temporales dentro de una transacción que siempre se revierte. También abre una segunda conexión para comprobar los bloqueos de espacios y boletos, y verifica que los usuarios y cantidades de registros permanezcan intactos. Ejecútala sin otros cambios simultáneos de datos para que la comparación de cantidades sea significativa.

Para verificar **el navegador contra Laravel y MySQL real** con Edge y `playwright-core` disponible fuera del proyecto:

```powershell
$env:PARKING_PLAYWRIGHT_PATH = Join-Path $env:TEMP 'parking-ui-tools\node_modules\playwright-core'
node tests/browser-mysql-phase-three.cjs
```

`tests/mysql-browser-bridge.php` mantiene una única transacción MySQL durante toda la sesión del navegador. La aplicación atiende cada solicitud AJAX real dentro de esa transacción y la revierte al terminar. El script comprueba registros de vehículo y entrada, cotización, error 422, QR manual, liberación, filtros de caja, comprobante y denegación Spatie 403. También confirma que el número de registros y todos los usuarios permanecen intactos después del rollback de la transacción de prueba.

La prueba de interfaz está en `tests/browser-phase-three.cjs`. Utiliza Edge y `playwright-core` instalado fuera del proyecto, indicado mediante `PARKING_PLAYWRIGHT_PATH`. Primero ejecuta `php tests/render-phase-three.php` para generar vistas de lectura; después `node tests/browser-phase-three.cjs`. Las operaciones de esa prueba de navegador usan respuestas simuladas y no escriben en MySQL. Las vistas temporales se generan en `storage/framework/testing/phase-three-ui`.

Resultados de implementación: **45 pruebas / 611 aserciones**, compilación Vite correcta, **42 comprobaciones MySQL**, prueba de interfaz simulada y flujo completo Edge con MySQL real correctos. **No se aplicó ninguna migración nueva.**
