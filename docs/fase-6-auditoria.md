# Fase 6: auditoría y demostración

Auditoría ejecutada el 12 de noviembre de 2026 sobre la aplicación existente.

## Correcciones realizadas

- **Abonados:** creación dentro de una transacción; bloqueo del vehículo y espacio antes de comprobar reservas superpuestas. Las consultas de solapamiento utilizan lecturas con bloqueo para obtener los datos actuales en MySQL.
- **Disponibilidad de reservas:** validación de cliente, vehículo, tipo, tarifa mensual, espacio, piso y sector. Un espacio con un boleto abierto tampoco puede reservarse aunque su estado estuviera inconsistente.
- **Reactivación:** se comprueban nuevamente pertenencia del vehículo, compatibilidad de tarifa, ubicación y fechas. Las fechas se normalizan a `Y-m-d` al revalidar registros almacenados.
- **Sectores:** se impide cambiar el piso de un sector que ya tiene espacios, evitando referencias geográficas contradictorias.
- **Disponibilidad general:** los espacios cuyo sector pertenece a otro piso quedan excluidos de las consultas operativas, el dashboard y los reportes actuales.

No se modificaron migraciones, credenciales, permisos existentes, rutas ni el diseño Tabler.

## Evidencia ejecutada

| Comprobación | Resultado |
| --- | --- |
| Suite completa, `php artisan test` / `php artisan test --compact` | 64 pruebas aprobadas, 844 aserciones después de corregir la regresión de fechas |
| `php artisan route:list` | 77 rutas; sin registro público de empleados |
| `php artisan migrate:status` | Siete migraciones aplicadas; ninguna pendiente |
| `npm run build` | Compilación correcta; módulos separados y carga dinámica mediante Vite |
| `php tests/mysql-audit-integrity.php` | 70 comprobaciones: InnoDB, claves foráneas, hashes, decimales, coherencia de pagos/ocupación y regresiones de abonados/ubicación |
| `php tests/mysql-phase-three.php` | 42 comprobaciones: bloqueos entre conexiones, entradas, cálculo, efectivo, QR manual, liberación, duplicados y Spatie |
| `php tests/mysql-phase-four.php` | 57 comprobaciones: ingresos, ranking, ocupación nocturna, filtros, permisos y contenido PDF/Excel |
| `node tests/browser-mysql-audit.cjs` | Recorrido completo en Edge con MySQL, sesiones y CSRF activos; sin errores JavaScript |
| `node tests/browser-mysql-phase-five.cjs` | Ocho catálogos, carga por módulo, crear/editar/desactivar, errores 422, escape de HTML e inicialización única |
| `node tests/browser-mysql-phase-three.cjs` | Modales de vehículo/entrada/salida, QR manual, comprobante, filtros y HTTP 403 sin recargas |
| `node tests/browser-mysql-phase-four.cjs` | Pestañas, gráficos, filtros, detalles, descargas, diseño móvil y periodos vacíos |
| `node tests/browser-mysql-administration.cjs` | Crear/renombrar roles, revocar permisos, crear empleados, cambiar roles, activar/desactivar y modales móviles |

La suite PHPUnit utiliza SQLite en memoria. Las tres verificaciones PHP de MySQL y las pruebas de navegador proporcionan evidencia adicional sobre la base MySQL real; no se presenta la suite SQLite como una ejecución íntegra de PHPUnit en MySQL.

La nueva prueba de navegador verifica:

1. Login de administrador temporal mediante el formulario y cookies reales; petición sin token y de otro origen rechazada con HTTP 419.
2. Creación de rol y empleado mediante modales y checkboxes.
3. Acciones permitidas y HTTP 403 para acciones no asignadas, usando una segunda sesión de empleado.
4. Revocación inmediata, permanencia tras logout/login, cierre de acceso para sesiones desactivadas y rechazo del login inactivo.
5. Creación de cliente, vehículo, tipo, piso, sector, espacio y tarifa mediante modales.
6. Entrada, visualización del boleto, cambio de ocupación y rechazo de entrada duplicada.
7. Cotización, pago en efectivo, comprobante, liberación del espacio y rechazo del doble cobro.
8. Filtros y gráficos de reportes, descarga PDF/XLSX y lectura de los archivos descargados para comprobar placa, importe y tipo numérico del total en Excel.

## Cobertura funcional y límites

| Área | Verificación |
| --- | --- |
| Autenticación | Hash, login/logout, límite de intentos y rechazo de cuentas inactivas; pruebas HTTP y navegador |
| Administración | Roles personalizados, checkboxes, sincronización de desmarcados, empleados, asignaciones, protección del Administrador y límites de delegación |
| Gestión | Los ocho catálogos, validaciones, relaciones, desactivación sin eliminación física y reservas mensuales |
| Operaciones | Tarifas por minuto, hora, fracción, día y mes; precisión en centavos; transacciones; bloqueo de espacio/vehículo/boleto y pago único |
| Caja | Efectivo, QR confirmado manualmente, filtros, historial y comprobantes imprimibles |
| Reportes | Pagos confirmados, ingresos diarios/mensuales, comparación, ranking, ocupación actual/histórica y periodos vacíos |
| Exportaciones | PDF carta, paginación, XLSX real, importes numéricos y fechas; misma consulta conservada durante 15 minutos para exportar |
| JavaScript | jQuery 4, `$.ajax()`, CSRF centralizado en `common.js`, módulos independientes, inicialización única, tablas y escape del contenido |

Los abonos registran vigencia, importe y reserva opcional. Según la regla ya mostrada en el modal de entrada, no se descuentan automáticamente del cobro por estancia. QR requiere confirmación manual; no existe una pasarela bancaria. La impresión se verificó mediante documentos digitales y su visualización, sin una impresora física.

El historial de ocupación mide horas-espacio y máximo simultáneo, incluidos cruces de medianoche. No inventa un porcentaje histórico de capacidad porque no existe un historial de cambios de habilitación de espacios. Los periodos se interpretan en `America/La_Paz`, respetando la zona de almacenamiento configurada.

## Aislamiento de los datos de prueba

Los scripts MySQL no ejecutan migraciones ni seeders. Los registros temporales permanecen dentro de una transacción que se revierte al finalizar, incluso ante errores. La auditoría nueva comprueba que todas las tablas implicadas sean InnoDB antes de escribir y compara huellas de los registros existentes antes/después sin imprimir secretos ni hashes.

El navegador nuevo utiliza un servidor HTTP limitado a `127.0.0.1`, un administrador temporal y un directorio de sesiones temporal. No cambia contraseñas reales ni desactiva CSRF. Los otros scripts de navegador emplean el puente HTTP de pruebas anterior con autenticación simulada; complementan la prueba nueva para módulos y tamaños de pantalla.

La reversión es de transacciones de prueba, nunca de migraciones. MySQL puede consumir identificadores autoincrementales durante las pruebas; esos saltos no implican pérdida de registros.

## Guion para presentar al docente

1. Iniciar MySQL y ejecutar `php artisan serve`. Abrir la dirección indicada por Artisan e ingresar con una cuenta Administrador existente.
2. En **Administración → Roles y permisos**, crear un rol con consulta de clientes y vehículos y registro de clientes. Mostrar la selección por grupos.
3. En **Administración → Usuarios**, crear un empleado con ese rol. Abrir una ventana privada e ingresar con el empleado; mostrar una operación permitida y una ruta administrativa denegada.
4. Desmarcar un permiso desde la sesión administrativa y comprobar su revocación en la sesión del empleado.
5. En **Gestión**, registrar cliente y vehículo. Utilizar un espacio disponible compatible y una tarifa por hora, por ejemplo Bs 5,25.
6. En **Operaciones → Entradas**, buscar la placa, seleccionar espacio y tarifa, registrar y visualizar el boleto. Mostrar que disminuyó la disponibilidad.
7. En **Salidas**, buscar el boleto, revisar el cálculo y confirmar efectivo o QR manual. Mostrar el comprobante y el espacio nuevamente disponible. Se cobra como mínimo una unidad iniciada.
8. En **Caja**, filtrar por placa y método. En **Reportes**, seleccionar el día y tipo del vehículo y descargar PDF y Excel; comparar el importe con pantalla.

Los registros creados manualmente durante esta presentación son datos reales y se conservan. El aislamiento y la reversión automáticos corresponden únicamente a los scripts de pruebas.

## Archivos de esta fase

- `app/Models/Space.php`: disponibilidad con ubicación coherente.
- `app/Http/Controllers/Catalog/SectorController.php`: protección al cambiar el piso.
- `app/Http/Controllers/Catalog/SubscriptionController.php`: transacción, bloqueos y revalidación de abonos.
- `tests/Feature/AuditIntegrityTest.php`: cuatro nuevas pruebas de regresión.
- `tests/mysql-audit-integrity.php`: revisión de MySQL y regresiones aisladas.
- `tests/mysql-audit-bridge.php`: puente HTTP con autenticación, sesiones y CSRF reales.
- `tests/browser-mysql-audit.cjs`: recorrido completo por navegador.
- `tests/verify-audit-export.php`: lectura de los PDF/XLSX descargados en el recorrido.
- `docs/fase-6-auditoria.md`: resultados, alcance y guion de presentación.

## Repetir las verificaciones

```powershell
php artisan test
php artisan route:list
php artisan migrate:status
npm run build
php tests/mysql-audit-integrity.php
php tests/mysql-phase-three.php
php tests/mysql-phase-four.php
```

Para navegador se necesita Edge y `playwright-core`. En este equipo se utilizó la instalación temporal ya disponible, sin añadir dependencias al proyecto:

```powershell
$env:PARKING_PLAYWRIGHT_PATH = Join-Path $env:TEMP 'parking-ui-tools\node_modules\playwright-core'
node tests/browser-mysql-audit.cjs
node tests/browser-mysql-phase-five.cjs
node tests/browser-mysql-phase-three.cjs
node tests/browser-mysql-phase-four.cjs
node tests/browser-mysql-administration.cjs
```

Ejecutar los scripts MySQL uno por uno. `PARKING_BROWSER_PATH` permite indicar otra ubicación de Edge.
