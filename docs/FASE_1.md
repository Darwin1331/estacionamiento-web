# Fase 1 — Estacionamiento
Implementación con Laravel 13, MySQL, Blade y Vite. Sin CRUD de clientes, vehículos ni espacios.

## Inicio
1. Mantener la conexión MySQL del archivo .env.
2. Definir DEMO_ADMIN_PASSWORD, DEMO_OPERATOR_PASSWORD y DEMO_SUPERVISOR_PASSWORD (mínimo 12 caracteres). Las variables de correo son opcionales.
3. En una instalación nueva: php artisan migrate y php artisan db:seed. Nunca ejecutar fresh o refresh sobre datos existentes.
4. Ejecutar npm install y npm run build (npm.cmd en PowerShell si la política bloquea npm.ps1).
5. Ejecutar php artisan serve y abrir http://127.0.0.1:8000/login.

Las contraseñas locales están en .env, excluido del control de versiones; en users solo se guardan hashes. No publicar ese archivo.
Correos predeterminados: admin@estacionamiento.test, operador@estacionamiento.test, supervisor@estacionamiento.test.
Los seeders son repetibles: no reinician contraseñas ni reactivan cuentas ya existentes.

## Acceso
Los tres roles acceden al panel. Solo Administrador puede listar usuarios y activar/desactivar cuentas.
No se ofrece eliminación física ni auto-desactivación. Se protege al último administrador activo.
El middleware verifica el estado en cada petición protegida. Logout invalida la sesión y regenera CSRF.
Login regenera el identificador de sesión, usa contraseñas hash y limita intentos.
El cambio de estado usa PATCH, CSRF, jQuery AJAX y confirmación SweetAlert2; también funciona como formulario normal.

## Datos y relaciones
Se conservan las tres migraciones originales de Laravel y se añade una migración:
- roles 1:N users; usuarios con role_id nullable para conservar cuentas existentes, que no obtienen acceso hasta asignarles rol.
- clients 1:N vehicles y subscriptions. subscriptions representa los abonos/abonados por cliente y vehículo, con vigencia, tarifa y espacio opcional.
- vehicle_types 1:N vehicles, spaces y rates.
- tickets pertenece a vehículo, espacio y tarifa, con abono opcional, usuario de apertura y cierre.
- payments pertenece a método y usuario cobrador; puede referenciar boleto o abono.
- Claves foráneas con restricción de eliminación, matrículas y códigos únicos, importes DECIMAL y fechas tipadas.
Los futuros servicios de cobro deberán exigir exactamente un destino (boleto o abono), importes positivos y transacciones para ocupación/cierre. No se implementan esas operaciones en esta fase.

## Indicadores
Consultas Eloquent reales: vehículos totales, espacios disponibles/ocupados e ingresos confirmados acumulados y del día. Los pagos anulados y espacios en mantenimiento se excluyen de sus respectivos indicadores.
Moneda de presentación: Bs. Zona horaria configurable mediante APP_TIMEZONE; valor predeterminado America/La_Paz.
No se siembran vehículos, espacios, tarifas, boletos ni pagos: inicialmente los indicadores pueden estar en cero.

## Organización
app/Models: entidades y relaciones.
app/Http/Controllers/Auth: sesiones; Admin: acceso de usuarios; DashboardController: indicadores.
app/Http/Middleware: estado y roles.
resources/views: layouts, auth, admin/users y dashboard.
resources/js/app.js: jQuery 4, Tabler y SweetAlert2; Tabler incluye Bootstrap 5, sin cargar otro runtime.
database/seeders: catálogos y usuarios de demostración.
tests/Feature/PhaseOneTest.php: pruebas aisladas con SQLite en memoria; no alteran MySQL.

## Verificación
php artisan test
php artisan route:list
php artisan view:cache
npm run build

Referencias: https://laravel.com/docs/13.x/authentication y https://docs.tabler.io/ui/getting-started

## Archivos creados o modificados
- .env: tres contraseñas aleatorias de demostración, sin cambiar credenciales MySQL.
- .env.example, config/demo.php, config/app.php: configuración de demostración y zona horaria.
- bootstrap/app.php, routes/web.php: registro de middleware y rutas.
- app/Models/User.php, Role.php, Client.php, Subscription.php, Vehicle.php, VehicleType.php, Space.php, Rate.php, Ticket.php, PaymentMethod.php y Payment.php: modelos y relaciones.
- app/Http/Middleware/EnsureUserIsActive.php y EnsureUserHasRole.php.
- app/Http/Controllers/Auth/AuthenticatedSessionController.php, Admin/UserStatusController.php y DashboardController.php.
- database/migrations/2026_11_11_000001_create_parking_schema.php.
- database/seeders/DatabaseSeeder.php, RoleSeeder.php, VehicleTypeSeeder.php, PaymentMethodSeeder.php y DemoUserSeeder.php.
- resources/views/layouts/app.blade.php, auth/login.blade.php, admin/users/index.blade.php y dashboard.blade.php.
- resources/css/app.css, resources/js/app.js y vite.config.js.
- tests/Feature/PhaseOneTest.php y ExampleTest.php.
- docs/FASE_1.md; recursos compilados en public/build.

## Resultado de ejecución local
Corrección visual: cada columna del dashboard tenía un cierre div faltante, anidando las tarjetas y reduciendo sucesivamente su ancho. Tabler 1.7.4 también ocultaba el sidebar por faltar data-bs-navbar-position="combined" en html al combinar navegación superior y lateral. Se corrigieron ambas causas, se aplicó col-12 col-md-6 col-lg-3, se añadieron iconos SVG mediante resources/views/components/icon.blade.php y el menú completo con módulos pendientes sin enlaces. Usuarios sigue reservado al Administrador.
Verificación visual con Edge y el CSS compilado: 1440/1024 px (4 columnas), 768 px (2), 390 px (1); sin desbordamiento horizontal y con apertura del menú móvil correcta.
Migración adicional aplicada a MySQL como lote 2, conservando el lote original. Seeders ejecutados y cuentas verificadas contra sus hashes.
12 pruebas aprobadas, 61 aserciones, sobre SQLite en memoria. Compilación Vite, caché de vistas y listado de rutas correctos.
Se repararon nombres truncados de archivos preexistentes en vendor (PHPUnit y componentes internos de Laravel, entre otros) y se regeneró el autoload de Composer. No se cambió el código de esas dependencias ni sus versiones.
La inspección db:show encontró una incompatibilidad del servidor local con performance_schema.session_status; la conexión, las migraciones, los seeders y las consultas de la aplicación funcionaron correctamente.
