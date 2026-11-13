@extends('layouts.app')
@section('title', 'Mapa de ocupación')
@section('description', 'Espacios del estacionamiento agrupados por piso y sector.')
@section('content')
@include('catalog.space-navigation')
<div id="parking-map" data-url="{{ route('catalog.espacios.map.data') }}">
    <div class="row g-3 mb-3">
        @foreach(['total' => 'Total', 'available' => 'Disponibles', 'occupied' => 'Ocupados', 'inactive' => 'Inactivos', 'occupancy_percent' => 'Ocupación'] as $key => $label)
        <div class="col-6 col-md"><div class="card stat-card h-100"><div class="card-body">
            <div class="stat-label">{{ $label }}</div><div class="stat-value" data-map-stat="{{ $key }}">—</div>
        </div></div></div>
        @endforeach
    </div>
    <div class="card mb-3"><div class="card-body">
        <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div class="d-flex flex-wrap gap-2" aria-label="Leyenda">
                <span class="badge bg-green-lt">Disponible</span>
                <span class="badge bg-red-lt">Ocupado</span>
                <span class="badge bg-secondary-lt">Inactivo / mantenimiento</span>
            </div>
            <button type="button" class="btn btn-outline-primary" id="map-refresh">Actualizar</button>
        </div>
        <p class="text-secondary small mt-3 mb-1">Ocupación = ocupados / (disponibles + ocupados). Los tickets abiertos tienen prioridad. Disponible no garantiza compatibilidad ni ausencia de reserva para un abonado.</p>
        <p class="text-secondary small mb-0" id="map-update-status" role="status">Cargando espacios…</p>
    </div></div>
    <div id="map-error" class="alert alert-warning d-none" role="alert"></div>
    <div id="map-groups" aria-busy="true"></div>
</div>
@endsection
