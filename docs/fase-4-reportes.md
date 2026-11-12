# Fase 4: reportes, estadísticas y exportaciones

## Probar desde el navegador

1. Inicia sesión como Administrador y abre **Reportes → Reportes y estadísticas** (`/reportes`).
2. En **Ingresos**, selecciona fecha inicial, fecha final, tipo y, opcionalmente, método de pago. Pulsa **Consultar**.
3. Revisa las tarjetas y los gráficos diarios, mensuales y por método. El selector de tabla permite ver días, meses, métodos, comparación y pagos individuales. **Ver detalle** abre un modal con los datos de la fila y sus pagos correspondientes.
4. En **Vehículos más frecuentes**, consulta el ranking y abre el detalle de visitas de una placa.
5. En **Ocupación del estacionamiento**, revisa disponibles, ocupados y porcentaje actual; la tabla y el gráfico muestran la ocupación histórica del periodo.
6. Pulsa **Exportar**, selecciona PDF o Excel y descarga desde el modal. El archivo incluye resumen, filtros, emisión y todas las tablas del reporte elegido. No es necesario cambiar de página.
7. Para comprobar un periodo sin movimientos, selecciona fechas sin registros. Los importes y conteos serán cero; el ranking mostrará que no hay registros. La ocupación **actual** seguirá mostrando el estado actual.

Si el servidor PHP estaba abierto antes de habilitar GD, reinícialo para que cargue la extensión y pueda generar PDF. Si usas Vite en desarrollo, conserva `npm run dev`; para servir los recursos compilados utiliza el procedimiento habitual del proyecto.

## Permisos existentes

No se modificaron las asignaciones de usuarios ni roles. El Administrador conserva su acceso completo. Para habilitar otros roles, el administrador puede utilizar la matriz existente:

| Acción | Permiso |
| --- | --- |
| Consultar ingresos | `ingresos.view` |
| Consultar frecuencia de vehículos | `estadisticas.view` |
| Consultar ocupación | `ocupacion.view` |
| Exportar PDF | `exportaciones.pdf` |
| Exportar Excel | `exportaciones.excel` |

Cada exportación exige tanto el permiso de consulta del reporte como el del formato. Las pestañas y botones reflejan los permisos; acceder directamente a una ruta sin autorización devuelve 403. Un rol que solo tenga reportes habilitados puede entrar directamente a esta sección después del login.

## Definiciones estadísticas

- Los periodos son inclusivos, de hasta 366 días, en **America/La_Paz**. Las consultas convierten sus límites a la zona de almacenamiento indicada por `config('app.timezone')`, sin modificar datos ni la configuración actual.
- Ingresos usa exclusivamente pagos con estado `paid`, según `paid_at`. Incluye pagos de estancias y de abonos; un pago con ambos vínculos históricos se cuenta una sola vez. Los importes se suman en centavos enteros.
- La comparación usa el periodo inmediatamente anterior con igual cantidad de días y los mismos filtros. Si su total es cero, la variación porcentual no tiene una base válida y se muestra sin valor.
- Los meses incluyen únicamente los días seleccionados. Efectivo y QR se muestran junto con cualquier otro método histórico existente.
- El ranking cuenta boletos abiertos o cerrados según su fecha de entrada, excluyendo anulados. Conserva vehículos y tipos actualmente inactivos para consultar el historial.
- La capacidad actual excluye espacios, pisos o sectores desactivados y espacios en mantenimiento. Los ocupados provienen de boletos vigentes; un espacio marcado ocupado sin boleto se identifica como bloqueado. Con filtro de tipo se consideran espacios compatibles, incluyendo los genéricos.
- El historial recorta cada estancia al periodo y a cada día. Una estancia que cruza medianoche aporta tiempo a ambos días. Los intervalos superpuestos de un mismo espacio se unen para no duplicar su ocupación.
- Se muestran horas-espacio, máximo simultáneo y media diaria de espacios ocupados. Para hoy se usa el tiempo transcurrido hasta emitir el reporte. No se inventa un porcentaje histórico de capacidad: la base existente no guarda los cambios pasados de habilitación y mantenimiento.
- Pantalla, PDF y Excel consumen `ParkingReports`. La consulta conserva una copia durante 15 minutos, exclusiva del usuario y los filtros, para que la descarga coincida aunque se registre otro pago mientras se consulta. Si expira, vuelve a pulsar **Consultar**.
- PDF usa carta horizontal, encabezados repetidos y paginación. XLSX incluye varias hojas, celdas numéricas para importes y fechas, formatos y resumen de totales. Placas y referencias se guardan como texto, sin ejecutar fórmulas.

## Dependencias

- `barryvdh/laravel-dompdf` **3.1.2** (motor DomPDF **3.1.6**).
- `maatwebsite/excel` **4.0.6**, compatible con Laravel 13 (PhpSpreadsheet **5.11.0**).
- `chart.js` **4.6.1**, cargado solo en la sección de reportes.
- `smalot/pdfparser` **2.13.1**, dependencia de desarrollo para verificar el contenido de PDF en las pruebas.
- Se habilitó `extension=gd` en `C:\php84\php.ini`; respaldo: `C:\php84\php.ini.before-phase4-gd`.

No se necesitan nuevas tablas o migraciones. No se ejecutaron migraciones ni seeders durante esta fase.

## Verificaciones realizadas

```powershell
php artisan test --compact
php artisan route:list
npm run build
php tests/mysql-phase-four.php
```

- **53 pruebas y 713 aserciones** aprobadas, incluidas las fases anteriores y ocho pruebas de reportes.
- **73 rutas** registradas. `php artisan route` es un espacio de subcomandos; el listado se verifica con `route:list`.
- Compilación Vite correcta.
- **57 comprobaciones MySQL**: ingresos, filtros, ranking, medianoche, ocupación, periodos vacíos, PDF leído, XLSX leído, tipos numéricos, igualdad con pantalla y permisos reales.
- Los datos de prueba MySQL se crean dentro de una transacción y se revierten. Se comparan usuarios, roles, permisos, migraciones y cantidades de registros antes y después. No se cambian las asignaciones de permisos existentes.

Prueba del navegador con Edge y `playwright-core` disponible fuera del proyecto:

```powershell
$env:PARKING_PLAYWRIGHT_PATH = Join-Path $env:TEMP 'parking-ui-tools\node_modules\playwright-core'
node tests/browser-mysql-phase-four.cjs
```

Esta prueba dirige las solicitudes reales del navegador al kernel HTTP de Laravel mediante `mysql-browser-bridge.php --reports`, manteniendo los datos temporales en una transacción MySQL. Comprueba pestañas, filtros, gráficos, detalles, descargas binarias PDF/XLSX, presentación móvil, periodos vacíos y validación 422 sin recargar. La autenticación se fija únicamente dentro del proceso de prueba; el login normal y CSRF de la aplicación no se modifican.

La fase 4 queda implementada. La auditoría final queda fuera de este trabajo.
