@extends('layouts.app')
@section('title', 'Dashboard')
@section('description', 'Resumen de vehículos, disponibilidad e ingresos del estacionamiento.')
@section('content')
<div class="row row-cards g-3">
    @foreach([
        ['Vehículos registrados', number_format($vehicles), 'car', 'blue', 'Total de vehículos en el sistema'],
        ['Espacios disponibles', number_format($available), 'parking', 'green', 'Listos para recibir vehículos'],
        ['Espacios ocupados', number_format($occupied), 'car', 'orange', 'Espacios actualmente en uso'],
        ['Ingresos acumulados', 'Bs '.number_format($income, 2), 'cash', 'purple', 'Pagos confirmados'],
    ] as [$label, $value, $icon, $color, $detail])
        <div class="col-12 col-md-6 col-lg-3">
            <article class="card h-100 stat-card">
                <div class="card-body">
                    <span class="avatar bg-{{ $color }}-lt mb-3"><x-icon :name="$icon" /></span>
                    <h2 class="stat-label text-secondary">{{ $label }}</h2>
                    <div class="stat-value">{{ $value }}</div>
                    <p class="text-secondary small mb-0 mt-2">{{ $detail }}</p>
                </div>
            </article>
        </div>
    @endforeach
    <div class="col-12 col-lg-7">
        <section class="card h-100">
            <div class="card-header">
                <h2 class="card-title d-flex align-items-center gap-2"><x-icon name="parking" /> Disponibilidad del estacionamiento</h2>
            </div>
            <div class="card-body">
                @php($operationalSpaces = $available + $occupied)
                @if($operationalSpaces > 0)
                    <div class="d-flex justify-content-between mb-2 gap-3">
                        <span class="text-secondary">Ocupación de espacios operativos</span>
                        <strong>{{ number_format($occupied / $operationalSpaces * 100, 0) }} %</strong>
                    </div>
                    <div class="progress mb-4" role="progressbar" aria-label="Ocupación" aria-valuenow="{{ round($occupied / $operationalSpaces * 100) }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar bg-primary" style="width: {{ $occupied / $operationalSpaces * 100 }}%"></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6"><span class="text-secondary d-block">Disponibles</span><strong class="fs-2">{{ number_format($available) }}</strong></div>
                        <div class="col-6"><span class="text-secondary d-block">Ocupados</span><strong class="fs-2">{{ number_format($occupied) }}</strong></div>
                    </div>
                @else
                    <div class="py-3 text-center">
                        <span class="avatar avatar-lg bg-secondary-lt mb-3"><x-icon name="parking" /></span>
                        <h3 class="mb-2">Sin espacios operativos</h3>
                        <p class="text-secondary mb-0">La disponibilidad se mostrará cuando existan espacios registrados y habilitados.</p>
                    </div>
                @endif
                <p class="small text-secondary mt-3 mb-0">Los espacios en mantenimiento no se incluyen en este resumen.</p>
            </div>
        </section>
    </div>
    <div class="col-12 col-lg-5">
        <section class="card h-100">
            <div class="card-header">
                <h2 class="card-title d-flex align-items-center gap-2"><x-icon name="cash" /> Ingresos de hoy</h2>
            </div>
            <div class="card-body">
                <span class="badge bg-green-lt mb-3">Pagos confirmados</span>
                <div class="stat-value mb-3">Bs {{ number_format($todayIncome, 2) }}</div>
                <p class="text-secondary">Total cobrado durante el día, según los pagos registrados en el sistema.</p>
                <div class="border-top pt-3 text-secondary small d-flex gap-2"><x-icon name="check" /> Los pagos anulados quedan excluidos.</div>
            </div>
        </section>
    </div>
</div>
@endsection
