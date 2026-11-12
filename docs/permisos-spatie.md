# Roles y permisos con Spatie

Se utiliza `spatie/laravel-permission` 8.5, guard `web`, `HasRoles` en `User` y modelos que extienden los de Spatie. Las rutas usan `can:permiso`; los botones usan las mismas comprobaciones `can()`.

## Estado de MySQL y conversión

Las dos migraciones se aplicaron en MySQL como lote 4. Se verificaron los tres usuarios y sus roles de Spatie después de la conversión.

Orden de las migraciones incrementales:

1. `2026_11_11_000003_create_role_permissions`: crea el catálogo y la relación `role_permission` si aún no fueron migrados.
2. `2026_11_11_000004_adapt_permissions_to_spatie`: adapta las tablas existentes, añade `guard_name`, conserva las claves de permisos y sus etiquetas, y crea `model_has_roles` y `model_has_permissions`.

La conversión inicializó los permisos mediante `PermissionSeeder` dentro de la propia migración. No se ejecutó `DatabaseSeeder` ni ningún seeder de usuarios.

La configuración de Spatie reutiliza `roles`, `permissions` y `role_permission`. No se publica la migración genérica del paquete, pues intentaría crear tablas ya existentes. Los IDs y las asignaciones de `role_permission` se conservan. `users.role_id` queda como dato histórico compatible con importaciones antiguas; la autorización usa exclusivamente las asignaciones de Spatie. Los usuarios existentes reciben su mismo rol mediante `assignRole()` sin guardar ni cambiar sus contraseñas.

Si los permisos manuales ya estaban configurados, la conversión los conserva, incluyendo roles sin ningún permiso. Si se parte directamente de fase 2, se asignan los accesos iniciales anteriores: Supervisor consulta los catálogos y Operador registra, edita y cambia estado en clientes, abonados, vehículos y espacios. Los seeders posteriores no restauran permisos revocados.

El Administrador tiene acceso permanente a las funciones de la aplicación mediante `Gate::before`. Su configuración no puede modificarse desde la interfaz ni desde el endpoint. Las cuentas Administrador y la propia cuenta están protegidas contra cambios administrativos de identidad/rol y activación/desactivación desde este módulo. El nombre visible del operador es **Operador / Cajero**, conservando el rol existente.

## Prueba en el navegador

Con las migraciones ya aplicadas:

1. Inicia sesión con tu cuenta de Administrador y abre **Administración → Roles y permisos**.
2. En **Operador / Cajero**, pulsa **Configurar permisos**. Se abre un modal Tabler con los permisos actuales agrupados por módulo.
3. Mantén **Clientes — Consultar**, desmarca **Clientes — Registrar** y pulsa **Guardar permisos → Confirmar** en SweetAlert2. El modal se cierra y el contador se actualiza sin recargar la página.
4. Abre otra ventana privada e inicia sesión como Operador. En **Gestión → Clientes** debe aparecer el listado sin el botón **Nuevo registro**.
5. Vuelve a marcar **Clientes — Registrar** como Administrador. Abre nuevamente Clientes en la sesión del Operador: el botón debe estar disponible y el registro debe funcionar sin volver a iniciar sesión.
6. Desmarca **Clientes — Consultar**. El enlace desaparece del menú del Operador; visitar directamente `/clientes` o `/clientes/datos` devuelve HTTP 403.
7. Repite con el Supervisor y con **Editar** o **Activar / desactivar** para comprobar que cada acción tiene un permiso independiente.
8. Cierra sesión y vuelve a entrar: los permisos guardados deben permanecer. Si se revoca Dashboard, el login abre el primer módulo de consulta permitido; si no hay ninguno, muestra la página sin módulos habilitados.
9. Abre los permisos del Administrador: se muestran completos en modo de solo lectura.

Entradas, salidas, tickets y caja ya están disponibles: consulta [la guía de Fase 3](fase-3-operaciones.md) para habilitar sus permisos y probar el flujo completo. Los reportes y exportaciones están disponibles en [la guía de Fase 4](fase-4-reportes.md). Anular boletos y cerrar caja siguen identificados como **Próximo**.

## Verificación automatizada

`php artisan test` usa SQLite en memoria, separado de MySQL. Incluye los CRUD anteriores, concesión y revocación de permisos, 403, persistencia al cerrar sesión, protección del administrador, respuestas AJAX y conservación de datos al convertir el esquema anterior. `npm run build` compila los recursos de Tabler y jQuery.

## Crear un rol personalizado y asignarlo a un empleado

1. Como Administrador, abre **Administración → Roles y permisos → Nuevo rol**.
2. Escribe **Recepcionista** y marca **Clientes — Consultar**, **Clientes — Registrar**, **Vehículos — Consultar** y **Vehículos — Registrar**. Puedes usar **Seleccionar todos** o **Ninguno** dentro de cada módulo y ajustar las casillas individualmente.
3. Pulsa **Crear rol → Confirmar**. La tarjeta aparece sin recargar. **Configurar permisos** permite modificar el nombre del rol personalizado y sus casillas. Los nombres de Administrador, Operador y Supervisor permanecen protegidos.
4. Abre **Administración → Usuarios → Nuevo empleado**. Introduce nombre, correo, contraseña y confirmación; selecciona **Recepcionista**. Pulsa **Guardar empleado → Confirmar**. La cuenta queda activa y su contraseña se almacena con `Hash`.
5. En una ventana privada, inicia sesión con el nuevo empleado. Entrará al primer módulo habilitado. Podrá registrar clientes y vehículos, pero no abrir Caja, reportes ni Administración si no tiene sus permisos. Una ruta directa sin permiso devuelve 403.
6. Desde la sesión del Administrador, desmarca **Clientes — Registrar** en el rol y guarda. En la sesión del empleado, la próxima solicitud de registro será denegada; al abrir nuevamente Clientes desaparece el botón de registro. No hace falta cerrar sesión para aplicar la revocación.
7. En Usuarios, **Editar** permite cambiar nombre, correo y rol de un empleado. La contraseña existente permanece intacta. Si una cuenta importada tenía varios roles, el modal indica que el rol seleccionado reemplazará sus asignaciones anteriores.
8. Usa **Desactivar → Confirmar**. La cuenta ya no podrá iniciar sesión y su siguiente solicitud autenticada será rechazada. **Activar** restaura el acceso con la misma contraseña y su rol actual.

Para tareas operativas, asigna además los permisos de consulta y ejecución indicados en la guía de Fase 3. Los clientes y conductores son registros de Gestión, no cuentas de personal. Un operador puede registrar un vehículo con cliente o sin cliente, sin crear usuarios. No existe registro público.

### Autorización de la administración delegada

Se reutilizan los permisos existentes, sin migraciones ni seeders:

| Acción | Permisos exigidos |
| --- | --- |
| Ver roles y su matriz | `roles.view` |
| Crear roles, renombrar los personalizados y configurar permisos | `roles.manage` |
| Ver empleados | `usuarios.view` |
| Crear o editar empleados y asignarles un rol | `usuarios.view` y `roles.manage` |
| Activar/desactivar empleados | `usuarios.status` |

Un gestor delegado solamente puede asignar permisos registrados del guard `web` que él mismo posea. Tampoco puede modificar roles o cuentas con permisos superiores a los suyos, ni asignar el rol Administrador. Estas restricciones se aplican en el servidor, incluso si se manipula el formulario. Los roles personalizados usan identificadores internos generados por el servidor; su nombre no otorga privilegios de superadministrador. Un rol sin casillas seleccionadas queda sin permisos.

La creación y actualización se realizan en transacciones mediante `syncPermissions()`, `assignRole()` y `syncRoles()`. No hay una acción de eliminación. El código de usuarios está en `resources/js/usuarios.js`; el de roles y permisos, en `resources/js/roles-permisos.js`. `app.js` conserva la configuración global e inicializa cada módulo una sola vez.

### Prueba del navegador con MySQL

```powershell
$env:PARKING_PLAYWRIGHT_PATH = Join-Path $env:TEMP 'parking-ui-tools\node_modules\playwright-core'
node tests/browser-mysql-administration.cjs
```

Utiliza Edge y el puente local de pruebas para atender solicitudes reales con Laravel/MySQL dentro de una transacción. Crea exclusivamente registros temporales, verifica modales, errores 422, selección de grupos, renombrado y revocación, creación de empleado, cambio de rol y estado, diseño móvil y ausencia de recargas. Al terminar revierte la transacción y compara los usuarios, roles, permisos y cantidades de registros con el estado inicial. El login y las restricciones de acceso se prueban también en `AdministrationManagementTest`.
