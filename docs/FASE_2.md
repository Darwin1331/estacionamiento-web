# Fase 2 — Catálogos del estacionamiento

Se añadieron Clientes, Abonados, Vehículos, Tipos de vehículos, Pisos, Sectores, Espacios y Tarifas al layout Tabler. Los listados usan DataTables con búsqueda, paginación y ordenamiento. Crear/editar se hace en modal; activar/desactivar requiere confirmación SweetAlert2. Las operaciones de cada módulo usan jQuery 4 con $.ajax(), CSRF y respuestas JSON, sin recargar la página. Los errores 422 se muestran en el campo correspondiente.

## Esquema

La migración `2026_11_11_000002_extend_phase_two_catalogs.php` está **pendiente** porque se solicitó expresamente no ejecutar `migrate`. Añade `floors` y `sectors`, `vehicles.is_active`, `spaces.floor_id`, `spaces.sector_id`, `spaces.is_active`, permite código único por piso/sector, amplía `rates.unit` y añade `rates.fraction_minutes`. Reutiliza las tablas y relaciones de la fase 1.

Los módulos que consultan estas columnas o tablas no podrán usarse contra MySQL hasta que se aplique **únicamente** la migración pendiente. No se ejecutó `migrate`, `fresh`, `refresh` ni se eliminaron datos. Las pruebas usan SQLite en memoria.

## Roles

- Administrador: gestión completa de los ocho catálogos.
- Operador: creación, edición y estado de Clientes, Abonados, Vehículos y Espacios; lectura de los demás.
- Supervisor: solo consulta.
- Cada escritura comprueba el rol en el backend.

## Reglas de negocio

- Clientes, vehículos, tipos, pisos, sectores, espacios, tarifas y abonados se desactivan sin borrar filas.
- Placas y documentos únicos. Los códigos de espacio son únicos dentro de cada piso/sector.
- Los espacios ocupados o con boleto abierto o abono vigente no se desactivan; no se permite editar manualmente un espacio ocupado.
- Una mensualidad exige vehículo del cliente, tarifa mensual del tipo del vehículo y fechas válidas. Se rechazan abonos activos solapados para el mismo vehículo o espacio.
- Las tarifas admiten minuto, hora, fracción, mes y día (día se conserva para compatibilidad con fase 1). Una tarifa por fracción exige duración de 1 a 59 minutos e importe positivo en bolivianos. El cobro efectivo queda para la fase siguiente.
- No se implementaron entradas, salidas, pagos ni reportes.

## Archivos principales

- `database/migrations/2026_11_11_000002_extend_phase_two_catalogs.php`
- `app/Models/Floor.php`, `Sector.php` y extensiones de `Vehicle.php`, `Space.php`, `Rate.php`.
- `app/Http/Controllers/Catalog/`: controladores y validación de los ocho catálogos.
- `resources/views/catalog/index.blade.php`: vista única de listado y modal.
- `resources/views/layouts/app.blade.php`: enlaces del sidebar.
- `resources/js/app.js`, `resources/css/app.css`: DataTables, modales, AJAX y estilos.
- `routes/web.php`: rutas protegidas de catálogos.
- `tests/Feature/PhaseTwoTest.php`: endpoints, validación, estados y roles.

## Verificación

`php artisan route:list`, `php artisan test`, `php artisan view:cache` y `npm run build`. La comprobación en Edge del módulo Clientes confirmó login, carga de DataTables y apertura del modal sin excepciones JavaScript.
