# Auditoría final de mejoras

Fecha: **13 de noviembre de 2026**, zona `America/La_Paz`.

## Alcance entregado

- README propio del proyecto: instalación Windows/MySQL, configuración, seeders, permisos, módulos, operación, pruebas y límites reales.
- Mapa dentro de Espacios, con agrupación por piso/sector, estados, placas autorizadas, indicadores y actualización AJAX manual o cada 30 segundos. Una sola consulta obtiene tarjetas y métricas; no se escribe otra fuente de ocupación.
- Protección ante solicitudes simultáneas, pausa por visibilidad, errores de red, sesión vencida y revocación. Las notas de inconsistencias no modifican datos.
- Reconocimiento **informativo** del abono vigente del vehículo en entrada/cotización, con verificación de relaciones y permisos. Se conservan importes, pagos, transacciones y trazabilidad existentes.
- Ajuste puntual del encabezado del catálogo para permitir envolver sus controles en pantallas estrechas. El mapa conserva Tabler, colores institucionales y menú lateral; Espacios sigue marcado como sección activa.

## Pruebas ejecutadas en esta revisión

| Comando / comprobación | Resultado real |
| --- | --- |
| `php artisan test` antes de los cambios funcionales | 64 pruebas, 844 aserciones aprobadas. |
| `php artisan test --compact` después de los cambios | **75 pruebas, 933 aserciones aprobadas**. |
| `npm run build` | Vite compiló correctamente; 32 módulos transformados. |
| `php artisan route:list` | **79 rutas**, incluidas las dos nuevas del mapa. |
| `php artisan migrate:status` | **7 migraciones aplicadas, ninguna pendiente**. No se añadieron ni ejecutaron migraciones sobre MySQL. |
| `node tests/browser-mysql-audit.cjs` | Aprobado en Edge con MySQL, login real, cookies y CSRF; comprobación de conservación de registros al revertir la transacción. |
| `node tests/browser-mysql-phase-five.cjs` | Aprobado: ocho módulos de catálogo, modales, DataTables, crear/editar/desactivar, HTTP 422, escape HTML e inicialización única. |
| `node tests/browser-mysql-phase-three.cjs` | Aprobado: entrada, ticket, cotización, errores de validación, QR manual, comprobante, filtros, liberación y HTTP 403. |
| `php tests/mysql-phase-three.php` | **42 comprobaciones aprobadas**, incluidos bloqueos de espacio/ticket entre conexiones; transacción revertida y registros existentes conservados. |
| `git diff --check` | Sin errores de espacios en los cambios. |

Los errores iniciales de preparación de las nuevas pruebas del mapa (usuario sin atributo activo cargado y sector de prueba con nombre duplicado) se corrigieron en sus fixtures. No se eliminaron pruebas ni aserciones existentes.

La suite PHPUnit usa SQLite en memoria, no MySQL. Los recorridos adicionales sí usan MySQL. Los dos scripts de navegador de fases anteriores emplean el puente con autenticación simulada; `browser-mysql-audit.cjs` usa sesiones y autenticación reales. No se modificaron credenciales `.env` ni contraseñas reales. Las transacciones de prueba pueden consumir IDs autoincrementales aunque se reviertan; sus saltos no representan pérdida de datos.

## Cobertura de los cambios

`ParkingMapTest` añade seis pruebas: acceso autenticado/activo, permisos en ambas rutas, revocación, privacidad de placas, mapa vacío, porcentaje sin división por cero, ubicaciones inactivas o inconsistentes, mantenimiento, tickets históricos/duplicados, prioridad de tickets abiertos y disponibilidad tras cobrar.

`SubscriptionRecognitionTest` añade cinco pruebas: límites de vigencia inclusivos, futuro/vencimiento, desactivación, vehículo exacto, propietario actual, relaciones activas, tarifa mensual compatible, ambigüedad heredada, permisos y conservación del cobro y del historial.

El recorrido de navegador existente se amplía sin quitar verificaciones: crea un abono vigente por modal, comprueba su aviso, cobra la tarifa normal, inspecciona el mapa ocupado y comprueba su liberación. También verifica PDF y XLSX descargados realmente, leyendo su contenido para contrastar placa e importe.

`tests/Support/parking-map-browser.cjs` verifica el módulo en navegador:

- Agrupación, tarjeta ocupada y placa con datos reales de Laravel/MySQL.
- Anchos de escritorio (1360 px) y móvil (375 px), sin desbordamiento horizontal del mapa.
- Botón manual y temporizador de 30 segundos; reloj del navegador adelantado para no esperar cada intervalo.
- Pausa/reanudación mediante eventos de visibilidad controlados, y bloqueo de solicitudes duplicadas con una petición retenida.
- Recuperación de fallo de red y conservación de datos con aviso de desactualización.
- Escape de HTML y limpieza/detención ante HTTP 403. Estos últimos casos usan respuestas controladas; los permisos del servidor se verifican aparte en PHPUnit y el recorrido de administración.

## Revisión visual

Se generaron y abrieron capturas reales de escritorio y móvil del mapa. Las tarjetas, leyenda, métricas, navegación y controles permanecen legibles, sin desplazamiento horizontal de la página a 375 px. Los archivos se guardan localmente en `%TEMP%\parking-final-review\mapa-escritorio.png` y `mapa-movil.png`.

Las capturas contienen datos de la base local y fixtures temporales; no se publican en Git ni se presentan como una instalación con datos definitivos. No se hizo un rediseño general ni una validación exhaustiva en todos los navegadores o tamaños. Los tickets y comprobantes se verificaron como documentos digitales; no se usó una impresora física.

## Limitaciones y decisiones pendientes

1. **Cobertura económica por mensualidad pendiente.** El aviso reconoce vigencia, pero no aplica gratuidad ni confirma pago. Tampoco se implementó una política activable de cobertura. Integrarla con caja, comprobantes y auditoría requiere definir pago confirmado, espacios cubiertos y estancias que cruzan el vencimiento. Se aplicó la excepción de estabilidad solicitada; ver [abonados.md](abonados.md).
2. Disponible en el mapa significa disponibilidad física. Reservas y compatibilidad se validan al entrar. El porcentaje excluye espacios inactivos sin ticket; un ticket abierto siempre cuenta como ocupado, incluso si requiere revisión.
3. El mapa informa inconsistencias heredadas, sin repararlas automáticamente. No es un plano a escala ni un historial de cambios de habilitación.
4. QR sigue siendo confirmación manual. Anulación de tickets y cierre de caja siguen figurando como permisos pendientes del sistema anterior.
5. No se realizó una prueba de carga masiva ni impresión física. La pausa por visibilidad se probó con eventos controlados, no con suspensión del equipo.

## Archivos creados y modificados

| Área | Archivos |
| --- | --- |
| Documentación | `README.md` (modificado); `docs/abonados.md`, `docs/auditoria-final.md` (nuevos). |
| Backend mapa | `app/Http/Controllers/ParkingMapController.php` (nuevo); `routes/web.php` (modificado). |
| Backend abonos | `app/Services/SubscriptionRecognition.php` (nuevo); `app/Http/Controllers/OperationsController.php` (modificado). |
| JavaScript | `resources/js/parking-map.js`, `resources/js/subscription-notice.js` (nuevos); `resources/js/app.js`, `entradas.js`, `salidas.js` (modificados). |
| Vistas | `resources/views/catalog/map.blade.php`, `catalog/space-navigation.blade.php` (nuevos); `catalog/index.blade.php`, `layouts/partials/navigation.blade.php`, `operations/index.blade.php` (modificados). |
| Estilos | `resources/css/app.css` (modificado). |
| Pruebas | `tests/Feature/ParkingMapTest.php`, `tests/Feature/SubscriptionRecognitionTest.php`, `tests/Support/parking-map-browser.cjs` (nuevos); `tests/browser-mysql-audit.cjs` (ampliado). |

## Git y publicación

Rama de entrega: `mejora/documentacion-mapa-abonados`. Se partió del árbol limpio de `mejoras-finales`, en `0107887`. Tanto esa rama como `main` conservan ese commit; no se fusionaron ni se publicaron cambios remotos.

Commits separados para documentación, mapa, reconocimiento de abonos y evidencia de auditoría. Consultar `git log --oneline main..HEAD` para sus identificadores. Los assets de `public/build` están ignorados por el repositorio y deben compilarse en el destino.

Antes de publicar: revisar esta rama, respaldar MySQL, compilar assets, comprobar permisos de los usuarios de demostración y recorrer entrada → mapa → salida → caja → reporte. Mantener expresamente documentada la limitación de mensualidades; no presentar su cobertura como terminada. Para un despliegue público, usar HTTPS y `APP_DEBUG=false`.

## Repetir la auditoría

```powershell
php artisan test
npm run build
php artisan route:list
php artisan migrate:status
$env:PARKING_PLAYWRIGHT_PATH = Join-Path $env:TEMP 'parking-ui-tools\node_modules\playwright-core'
node tests/browser-mysql-audit.cjs
node tests/browser-mysql-phase-five.cjs
node tests/browser-mysql-phase-three.cjs
php tests/mysql-phase-three.php
```

Los scripts MySQL deben ejecutarse uno por uno, en un entorno local con su configuración habitual y sin otros cambios concurrentes en la base. `PARKING_BROWSER_PATH` permite indicar otro ejecutable de Edge. No es necesario añadir dependencias al proyecto para usar una instalación externa de `playwright-core`.
