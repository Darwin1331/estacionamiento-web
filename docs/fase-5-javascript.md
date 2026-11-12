# Fase 5: JavaScript por módulo

`resources/js/app.js` configura jQuery 4, CSRF, Tabler y SweetAlert2. Lee el JSON de configuración ya presente en la vista y carga con `import()` el módulo de la página. Vite conserva `resources/js/app.js` como único punto de entrada y genera archivos separados para cada importación dinámica. Las páginas de login y dashboard no cargan DataTables ni Chart.js. Chart.js se carga únicamente desde `reports.js` cuando se abre Reportes.

| Página | Archivo que inicializa el módulo | Comportamiento compartido |
| --- | --- | --- |
| Clientes | `clientes.js` | `catalogo-base.js` |
| Abonados | `abonados.js` | `catalogo-base.js` |
| Vehículos | `vehiculos.js` | `catalogo-base.js` |
| Tipos de vehículo | `tipos-vehiculo.js` | `catalogo-base.js` |
| Pisos | `pisos.js` | `catalogo-base.js` |
| Sectores | `sectores.js` | `catalogo-base.js` |
| Espacios | `espacios.js` | `catalogo-base.js` |
| Tarifas | `tarifas.js` | `catalogo-base.js` |
| Entradas | `entradas.js` | `operaciones-base.js`, impresión de tickets |
| Salidas | `salidas.js` | `operaciones-base.js`, impresión de tickets |
| Tickets | `tickets.js` | `operaciones-base.js` |
| Caja | `caja.js` | `operaciones-base.js` |
| Reportes | `reports.js` | Solo la página de reportes |
| Usuarios | `usuarios.js` | Solo la página de usuarios |
| Roles y permisos | `roles-permisos.js` | Solo la página de roles |

Los dos archivos base contienen únicamente acciones realmente comunes: tabla y CRUD de catálogos; tabla, indicadores e impresión de operaciones. Cada archivo de catálogo define qué módulo puede iniciar y, cuando corresponde, cómo filtrar opciones dependientes. `abonados.js` vincula vehículos con clientes y filtra tarifas y espacios por tipo; `espacios.js` vincula sectores con pisos. Las operaciones especiales permanecen en sus módulos: búsqueda y registro de entradas, cotización y cobro en salidas, filtro y comprobante en caja, e impresión de boletos en tickets.

Cada vista aporta sus URLs y permisos efectivos a través de un elemento `script type="application/json"`; no se añadieron rutas ni se cambiaron controladores. Los módulos usan `$.ajax()` y conservan los modales, DataTables, SweetAlert2, validación 422 y actualización sin recargar. La autorización y validación de datos siguen en Laravel. Las cadenas provenientes de usuarios se escapan antes de insertarse en tablas o detalles; los documentos imprimibles siguen procediendo de plantillas Blade del servidor.

Las inicializaciones comprueban un marcador en su elemento de configuración. Esto evita registrar eventos dos veces si una inicialización vuelve a invocarse. `app.js` solo selecciona el módulo actual y mantiene la configuración global.

## Verificación

```powershell
php artisan test --compact
npm run build
$env:PARKING_PLAYWRIGHT_PATH = Join-Path $env:TEMP 'parking-ui-tools\node_modules\playwright-core'
node tests/browser-mysql-phase-five.cjs
node tests/browser-mysql-phase-three.cjs
node tests/browser-mysql-phase-four.cjs
node tests/browser-mysql-administration.cjs
```

La suite PHP cubre las reglas del backend de todas las fases. La prueba nueva de Edge y MySQL comprueba que los ocho catálogos carguen exclusivamente su archivo de módulo, que sus formularios y DataTables funcionen, que la validación 422 aparezca dentro del modal, que una segunda inicialización no duplique peticiones y que un texto con HTML de usuario no se ejecute. Las pruebas anteriores repiten entradas, salidas, caja, reportes, exportaciones, usuarios y roles con los nuevos archivos de Vite. Las comprobaciones de navegador crean datos solo dentro de una transacción MySQL que se revierte al terminar.

La refactorización no requiere migraciones ni modifica datos o permisos existentes. La auditoría final permanece pendiente.
