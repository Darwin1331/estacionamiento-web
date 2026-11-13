@extends('layouts.app')
@section('title', $title)
@section('description', 'Control de estancias, boletos y pagos del estacionamiento.')
@section('content')
<div id="operations-page">
    <div class="row g-3 mb-3" aria-live="polite">
        <div class="col-sm"><div class="card card-body"><span class="text-secondary">Espacios disponibles</span><strong class="h1 mb-0" data-stat="available">—</strong></div></div>
        <div class="col-sm"><div class="card card-body"><span class="text-secondary">Espacios ocupados</span><strong class="h1 mb-0" data-stat="occupied">—</strong></div></div>
        @can('caja.view')<div class="col-sm"><div class="card card-body"><span class="text-secondary">Ingresos de hoy (Bs)</span><strong class="h1 mb-0" data-stat="todayIncome">—</strong></div></div>@endcan
    </div>
    <div class="card">
        <div class="card-header gap-2 flex-wrap">
            <h2 class="card-title me-auto">{{ $module === 'caja' ? 'Historial de transacciones' : ($module === 'tickets' ? 'Historial de boletos' : 'Vehículos estacionados') }}</h2>
            @if($module === 'entradas')
                @can('entradas.create')<button class="btn btn-primary" id="open-entry" type="button">Registrar entrada</button>@endcan
            @endif
            @if(in_array($module, ['salidas', 'caja']))
                @can('salidas.view')<button class="btn btn-primary" id="open-search" type="button">Buscar boleto / placa</button>@endcan
            @endif
            <button type="button" class="btn btn-outline-secondary" id="refresh-operations">Actualizar</button>
        </div>
        <div class="card-body">
            <div id="operations-error" class="alert alert-danger d-none" role="alert"></div>
            @if($module === 'caja')
            <form id="cash-filters" class="row g-2 mb-3 align-items-end">
                <div class="col-sm"><label class="form-label" for="cash-from">Desde</label><input class="form-control" type="date" id="cash-from" name="from"></div>
                <div class="col-sm"><label class="form-label" for="cash-to">Hasta</label><input class="form-control" type="date" id="cash-to" name="to"></div>
                <div class="col-sm"><label class="form-label" for="cash-method">Método</label><select class="form-select" id="cash-method" name="method"><option value="">Todos</option><option value="cash">Efectivo</option><option value="qr">QR</option></select></div>
                <div class="col-sm"><label class="form-label" for="cash-search">Boleto, placa o referencia</label><input class="form-control" id="cash-search" name="search" maxlength="80"></div>
                <div class="col-sm-auto"><button class="btn btn-primary">Filtrar</button></div>
            </form>
            <p class="text-secondary">Total pagado en el filtro: <strong id="filtered-total">—</strong> Bs</p>
            @endif
            <div class="table-responsive"><table id="operations-table" class="table table-vcenter w-100"></table></div>
        </div>
    </div>
</div>

@if($module === 'entradas' && $config['canEnter'])
<div class="modal modal-blur fade" id="entry-modal" tabindex="-1" aria-labelledby="entry-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content" id="entry-form">
        <div class="modal-header"><h2 class="modal-title" id="entry-title">Registrar entrada</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
            <div class="alert alert-danger d-none" data-form-errors role="alert"></div>
            <label class="form-label" for="entry-plate">Placa del vehículo</label>
            <div class="input-group mb-2"><input id="entry-plate" class="form-control text-uppercase" maxlength="20" autocomplete="off"><button class="btn btn-outline-primary" type="button" id="find-vehicle">Buscar</button></div>
            <div id="vehicle-result" class="text-secondary mb-3" aria-live="polite">Busca la placa antes de seleccionar el espacio.</div>
            <div id="entry-subscription" class="alert alert-secondary d-none" role="status"></div>
            <input type="hidden" name="vehicle_id">
            @if($config['canCreateVehicle'])<button type="button" id="new-vehicle" class="btn btn-outline-primary mb-3">Registrar vehículo nuevo</button>@endif
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="entry-space">Espacio disponible</label><select class="form-select" id="entry-space" name="space_id" required></select></div>
                <div class="col-md-6"><label class="form-label" for="entry-rate">Tarifa</label><select class="form-select" id="entry-rate" name="rate_id" required></select></div>
            </div>
            <div class="alert alert-info mt-3 mb-0">Se cobra cada unidad iniciada, con un mínimo de una unidad. El precio queda fijado al registrar la entrada. Los abonos no se descuentan automáticamente de este cobro por estancia.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Registrar e imprimir boleto</button></div>
    </form></div>
</div>
@if($config['canCreateVehicle'])
<div class="modal modal-blur fade" id="vehicle-modal" tabindex="-1" aria-labelledby="vehicle-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content" id="operation-vehicle-form">
        <div class="modal-header"><h2 class="modal-title" id="vehicle-title">Registrar vehículo</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body"><div class="alert alert-danger d-none" data-form-errors role="alert"></div><div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="new-plate">Placa</label><input class="form-control text-uppercase" id="new-plate" name="plate" required maxlength="20"></div>
            <div class="col-md-6"><label class="form-label" for="new-type">Tipo de vehículo</label><select class="form-select" id="new-type" name="vehicle_type_id" required></select></div>
            @foreach(['brand' => 'Marca', 'model' => 'Modelo', 'color' => 'Color'] as $key => $label)
            <div class="col-md-4"><label class="form-label" for="new-{{ $key }}">{{ $label }}</label><input class="form-control" id="new-{{ $key }}" name="{{ $key }}" maxlength="255"></div>
            @endforeach
        </div></div>
        <div class="modal-footer"><button type="button" class="btn" data-bs-dismiss="modal">Volver</button><button class="btn btn-primary" type="submit">Guardar vehículo</button></div>
    </form></div>
</div>
@endif
@endif

@if($config['canQuote'])
<div class="modal modal-blur fade" id="checkout-modal" tabindex="-1" aria-labelledby="checkout-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title" id="checkout-title">Salida y pago</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
            <form id="ticket-search-form" class="mb-3">
                <div class="alert alert-danger d-none" data-form-errors role="alert"></div>
                <label class="form-label" for="ticket-search">Código de boleto o placa</label><div class="input-group"><input class="form-control" id="ticket-search" name="search" maxlength="80" required><button class="btn btn-outline-primary" type="submit">Buscar</button></div>
            </form>
            <div id="checkout-detail" class="d-none">
                <dl id="stay-detail" class="row"></dl>
                <div id="checkout-subscription" class="alert alert-secondary d-none" role="status"></div>
                <div class="alert alert-info">Total a pagar: <strong class="fs-2" id="checkout-total"></strong> Bs <button class="btn btn-sm btn-outline-primary ms-2" type="button" id="refresh-quote">Actualizar cálculo</button><div class="small mt-2">El importe se verifica de nuevo al confirmar el pago.</div></div>
                <form id="checkout-form">
                    <div class="alert alert-danger d-none" data-form-errors role="alert"></div>
                    <input name="expected_total" type="hidden">
                    @if($config['canCharge'])
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="checkout-method">Método de pago</label><select class="form-select" name="method" id="checkout-method" required><option value="cash">Efectivo</option><option value="qr">QR (confirmación manual)</option></select></div>
                        <div class="col-md-6"><label class="form-label" for="checkout-reference">Referencia (opcional)</label><input class="form-control" id="checkout-reference" name="reference" maxlength="255"></div>
                    </div>
                    <label id="qr-confirmation" class="form-check mt-3 d-none"><input class="form-check-input" type="checkbox" name="qr_confirmed" value="1"><span class="form-check-label">Verifiqué la recepción del pago QR.</span></label>
                    <button class="btn btn-success mt-3" type="submit">Confirmar pago y salida</button>
                    @else<div class="alert alert-secondary">Necesitas permisos para registrar salidas y realizar cobros.</div>@endif
                </form>
            </div>
        </div>
        <div class="modal-footer"><button class="btn" type="button" data-bs-dismiss="modal">Cerrar</button></div>
    </div></div>
</div>
@endif

<div class="modal modal-blur fade" id="print-modal" tabindex="-1" aria-labelledby="print-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title" id="print-title">Documento digital</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body" id="print-document"></div>
        <div class="modal-footer"><button class="btn" type="button" data-bs-dismiss="modal">Cerrar</button><button type="button" class="btn btn-primary" id="print-operation">Imprimir</button></div>
    </div></div>
</div>
<script type="application/json" id="operations-config">@json($config)</script>
@endsection
