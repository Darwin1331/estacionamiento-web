# Sistema web de estacionamiento

Aplicación administrativa para registrar vehículos, controlar estancias y espacios, cobrar salidas y consultar ingresos. Desarrollada por **Darwin Choquehuanca Mamani** para la materia **Diseño y Programación Web II**.

## Objetivo

Centralizar la operación de un estacionamiento con acceso por permisos, trazabilidad de operadores y validaciones que eviten entradas incompatibles y cobros duplicados.

## Funciones y módulos

| Módulo | Funciones |
| --- | --- |
| Dashboard | Indicadores según los permisos del usuario. |
| Clientes y vehículos | Contactos, placas únicas, tipos y activación/desactivación lógica. |
| Abonados | Cliente y vehículo específicos, tarifa mensual, vigencia y reserva opcional; validación de fechas superpuestas. |
| Pisos, sectores y espacios | Organización física, compatibilidad de vehículos, disponibilidad y mantenimiento. |
| Tarifas | Cobro por minuto, fracción, hora, día o mes. |
| Entradas y tickets | Registro transaccional, ocupación del espacio, impresión y reimpresión autorizada. |
| Salidas y caja | Cotización en servidor, efectivo, confirmación manual de QR, comprobante y liberación del espacio. |
| Reportes | Ingresos, vehículos y ocupación con consultas AJAX, gráficos y exportación PDF/XLSX. |
| Administración | Usuarios, roles y permisos mediante Spatie. |

Los catálogos utilizan modales, jQuery/AJAX, DataTables y validación en servidor. Se conserva el historial mediante activación/desactivación lógica. Los permisos `tickets.cancel` y `caja.close` están declarados como pendientes: no hay anulación de tickets ni cierre de turno implementados.

## Tecnologías y requisitos

| Componente | Tecnología / requisito |
| --- | --- |
| Servidor | PHP 8.3 o superior, Composer 2, Laravel 13 |
| Base de datos | MySQL con tablas InnoDB; PDO MySQL habilitado |
| Interfaz | Blade, Tabler (Bootstrap 5), jQuery, DataTables, SweetAlert2, Chart.js |
| Permisos y documentos | Spatie Laravel Permission, DomPDF, Laravel Excel |
| Assets | Node.js compatible con Vite 7 (20.19+ o 22.12+), npm |
| Pruebas | PHPUnit; PDO SQLite para la suite en memoria |

Habilitar las extensiones PHP requeridas por las dependencias, incluidas `mbstring`, `dom`, `xml`, `fileinfo`, `zip`, `gd` y los controladores PDO. `composer check-platform-reqs` comprueba los requisitos efectivos del archivo de bloqueo.

## Instalación en Windows (PowerShell)

Instalar Git, PHP, Composer, Node.js y MySQL, y agregar los ejecutables al `PATH`. Iniciar MySQL y crear una base vacía, por ejemplo `estacionamiento`, con codificación `utf8mb4` y un usuario autorizado para esa base.

```powershell
git clone https://github.com/Darwin1331/estacionamiento-web.git
cd estacionamiento-web
composer install
composer check-platform-reqs
npm ci
Copy-Item .env.example .env
php artisan key:generate
```

Copiar `.env.example` **solo en una instalación nueva**. En `.env`, configurar los valores locales; nunca publicar este archivo:

```dotenv
APP_NAME="Sistema de Estacionamiento"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=America/La_Paz
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=estacionamiento
DB_USERNAME=usuario_local
DB_PASSWORD=
```

Completar `DB_PASSWORD` según la instalación y las tres variables `DEMO_ADMIN_PASSWORD`, `DEMO_OPERATOR_PASSWORD` y `DEMO_SUPERVISOR_PASSWORD` con contraseñas propias de **al menos 12 caracteres**. Los correos se configuran mediante `DEMO_ADMIN_EMAIL`, `DEMO_OPERATOR_EMAIL` y `DEMO_SUPERVISOR_EMAIL`. No hay contraseñas universales.

```powershell
php artisan config:clear
php artisan migrate
php artisan db:seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Abrir `http://127.0.0.1:8000`. `DatabaseSeeder` ejecuta `RoleSeeder` (incluye permisos), `VehicleTypeSeeder`, `PaymentMethodSeeder` y `DemoUserSeeder`. Este último crea usuarios que no existan; no restablece contraseñas existentes. Pisos, sectores, espacios, tarifas y datos de operación se registran desde el sistema.

Para desarrollar, mantener `php artisan serve` en una terminal y ejecutar `npm run dev` en otra. Para una demostración sin Vite activo, usar `npm run build` y comprobar que no haya un archivo `public/hot` obsoleto.

En una actualización existente: respaldar la base, instalar dependencias con los archivos de bloqueo, revisar `php artisan migrate:status`, aplicar únicamente las migraciones pendientes y compilar assets. No usar `migrate:fresh` ni `db:wipe` sobre datos que deban conservarse; no repetir seeders para actualizar permisos personalizados.

## Roles y permisos

| Rol inicial | Permisos predeterminados |
| --- | --- |
| Administrador | Todos los permisos del catálogo. |
| Operador | Dashboard y consulta de catálogos; creación, edición y cambio de estado de clientes, abonados, vehículos y espacios. |
| Supervisor | Dashboard y consulta de catálogos. |

El administrador debe habilitar expresamente las operaciones y reportes necesarios para cada rol. Las asignaciones se administran desde **Administración → Roles y permisos** y pueden diferir de estos valores iniciales. Registrar una salida exige `salidas.execute` y `caja.charge`; reimprimir exige `tickets.reprint`. Cada exportación exige el permiso del reporte y `exportaciones.pdf` o `exportaciones.excel`.

## Entradas, salidas y caja

1. Crear tipos, pisos, sectores, espacios y tarifas activos. Registrar cliente y vehículo cuando corresponda.
2. En **Entradas**, buscar la placa y seleccionar espacio y tarifa compatibles. Se bloquean filas dentro de una transacción para impedir entradas duplicadas y respetar reservas.
3. El ticket conserva el importe unitario de entrada. Las unidades se redondean hacia arriba, con un mínimo de una unidad; los meses se calculan por calendario desde la entrada.
4. En **Salidas**, buscar ticket o placa y consultar la cotización. El servidor recalcula al confirmar; si el importe cambió, se debe actualizar y confirmar nuevamente.
5. Cobrar en efectivo o confirmar manualmente el QR. **No existe integración bancaria ni verificación automática de QR**. Un pago confirmado registra operador y fecha, cierra el ticket y libera el espacio en la misma transacción.

Los abonados tienen un registro contractual y reserva opcional. Entrada y cotización muestran la vigencia verificable del vehículo cuando el usuario tiene `abonados.view`; el aviso es informativo y mantiene el cobro normal. Su importe no equivale a un pago confirmado. La cobertura automática queda pendiente según [docs/abonados.md](docs/abonados.md).

## Mapa de ocupación

En **Gestión → Espacios → Mapa de ocupación** se muestran tarjetas por piso y sector, con código, estado y placa si el usuario tiene `vehiculos.view` o `tickets.view`. La pantalla y su endpoint exigen `espacios.view`.

El mapa consulta los espacios y tickets abiertos, sin almacenar otra ocupación. Verde indica disponibilidad física, rojo un ticket abierto y gris inactividad, mantenimiento o una condición que requiere revisión. Los tickets abiertos tienen prioridad incluso ante un estado incoherente. Las reservas de abonados siguen validándose al registrar una entrada: el verde no garantiza que cualquier vehículo pueda usar el espacio.

Incluye totales y porcentaje `ocupados / (disponibles + ocupados)`, con cero cuando no hay capacidad utilizable. Actualiza cada 30 segundos y mediante el botón **Actualizar**, evita peticiones simultáneas y pausa el sondeo con la pestaña oculta. Los fallos de conexión se indican sin presentar datos antiguos como recientes; la pérdida de acceso detiene el sondeo y limpia los datos.

## Reportes y exportaciones

Los reportes cargan resultados mediante AJAX y permiten aplicar filtros según el reporte. PDF y XLSX se generan en servidor con DomPDF y Laravel Excel; los permisos se verifican también al descargar. La caja muestra pagos registrados, no una conciliación bancaria ni un cierre de turno.

## Pruebas y comprobaciones

```powershell
php artisan test
npm run build
php artisan route:list
php artisan migrate:status
```

PHPUnit usa SQLite en memoria según `phpunit.xml`; no ejecutar las pruebas con una configuración de producción cacheada. La suite cubre autenticación, permisos, catálogos, operaciones, integridad y exportaciones. Los scripts `tests/browser-*.cjs` y `tests/mysql-*.php` son comprobaciones adicionales para un entorno local MySQL; revisar sus requisitos antes de ejecutarlos. Las pruebas de navegador usan Edge y una instalación externa de `playwright-core`, configurable con `PARKING_BROWSER_PATH` y `PARKING_PLAYWRIGHT_PATH`.

Los resultados y límites de la revisión final están en [docs/auditoria-final.md](docs/auditoria-final.md).

## Estructura

```text
app/Http/Controllers/   Catálogos, operaciones, reportes y administración
app/Models/             Entidades y relaciones
app/Services/           Cobro, operación y consultas de reportes
app/Exports/            Exportaciones Excel
config/                 Configuración y catálogo de permisos
database/               Migraciones, seeders y factories
resources/views/        Pantallas Blade y documentos imprimibles
resources/js/           Módulos AJAX por pantalla
resources/css/          Tabler e identidad institucional
routes/web.php          Rutas y autorización
tests/                  Pruebas automatizadas y auditorías locales
docs/                   Decisiones y evidencia de revisión
```

Antes de publicar: configurar HTTPS y `APP_DEBUG=false`, proteger credenciales, revisar permisos, respaldar MySQL y verificar el flujo completo con datos de demostración.
