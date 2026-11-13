<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParkingMapController extends Controller
{
    public function index()
    {
        return view('catalog.map');
    }

    public function data(Request $request)
    {
        $showPlates = $request->user()->canAny(['vehiculos.view', 'tickets.view']);
        // A single SELECT keeps counters and cards on the same database snapshot.
        // Open tickets take precedence over cached space status, including legacy inconsistencies.
        $query = DB::table('spaces as s')
            ->leftJoin('floors as f', 'f.id', '=', 's.floor_id')
            ->leftJoin('sectors as sec', 'sec.id', '=', 's.sector_id')
            ->leftJoin('tickets as t', fn ($join) => $join->on('t.space_id', '=', 's.id')->where('t.status', 'open'))
            ->select(['s.id', 's.code', 's.status', 's.is_active', 's.floor_id', 's.sector_id',
                'f.name as floor_name', 'f.is_active as floor_active', 'sec.name as sector_name',
                'sec.is_active as sector_active', 'sec.floor_id as sector_floor_id', 't.id as ticket_id'])
            ->orderBy('f.name')->orderBy('sec.name')->orderBy('s.code')->orderBy('s.id')->orderBy('t.id');
        if ($showPlates) {
            $query->leftJoin('vehicles as v', 'v.id', '=', 't.vehicle_id')->addSelect('v.plate');
        }

        $spaces = $query->get()->groupBy('id')->map(function ($rows) use ($showPlates) {
            $space = $rows->first();
            $open = $rows->whereNotNull('ticket_id');
            $operational = $space->is_active
                && (! $space->floor_id || $space->floor_active)
                && (! $space->sector_id || ($space->sector_active && $space->sector_floor_id === $space->floor_id));
            $occupied = $open->isNotEmpty();
            $status = $occupied ? 'occupied' : ($operational && $space->status === 'available' ? 'available' : 'inactive');
            $note = match (true) {
                $open->count() > 1 => 'Revisar: varios tickets abiertos.',
                $occupied && (! $operational || $space->status !== 'occupied') => 'Revisar: ticket abierto y condición incompatible.',
                ! $occupied && $space->status === 'occupied' => 'Revisar: marcado ocupado sin ticket abierto.',
                ! $operational => 'Espacio, piso o sector inactivo o incompatible.',
                $space->status === 'maintenance' => 'En mantenimiento.',
                default => null,
            };

            return [
                'id' => $space->id, 'code' => $space->code, 'status' => $status, 'note' => $note,
                'floor' => ['id' => $space->floor_id, 'name' => $space->floor_name ?? 'Sin piso'],
                'sector' => ['id' => $space->sector_id, 'name' => $space->sector_name ?? 'Sin sector'],
                // Do not serialize vehicle IDs or plates without the relevant permission.
                'plates' => $showPlates ? $open->pluck('plate')->filter()->unique()->values()->all() : [],
            ];
        })->values();
        $available = $spaces->where('status', 'available')->count();
        $occupied = $spaces->where('status', 'occupied')->count();

        return response()->json([
            'data' => $spaces,
            'stats' => [
                'total' => $spaces->count(), 'available' => $available, 'occupied' => $occupied,
                'inactive' => $spaces->where('status', 'inactive')->count(),
                'occupancy_percent' => $available + $occupied > 0 ? round(100 * $occupied / ($available + $occupied), 1) : 0,
            ],
            'updated_at' => now()->toIso8601String(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
