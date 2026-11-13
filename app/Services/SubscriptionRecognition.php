<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Informational recognition only; never authorizes a discount or changes billing. */
class SubscriptionRecognition
{
    public function forVehicle(int $vehicleId, CarbonInterface $at): array
    {
        $date = $at->copy()->setTimezone(config('app.timezone'))->toDateString();
        $matches = DB::table('subscriptions as s')
            ->join('vehicles as v', 'v.id', '=', 's.vehicle_id')
            ->join('clients as c', 'c.id', '=', 's.client_id')
            ->join('vehicle_types as vt', 'vt.id', '=', 'v.vehicle_type_id')
            ->join('rates as r', 'r.id', '=', 's.rate_id')
            ->where('s.vehicle_id', $vehicleId)->whereColumn('v.client_id', 's.client_id')
            ->whereColumn('r.vehicle_type_id', 'v.vehicle_type_id')->where('r.unit', 'month')
            ->where('s.is_active', true)->where('v.is_active', true)->where('c.is_active', true)
            ->where('vt.is_active', true)->where('r.is_active', true)
            ->whereDate('s.starts_on', '<=', $date)->whereDate('s.ends_on', '>=', $date)
            ->orderBy('s.id')->limit(2)->get(['s.id', 's.starts_on', 's.ends_on']);

        if ($matches->count() !== 1) {
            return ['status' => $matches->isEmpty() ? 'none' : 'ambiguous', 'coverage_applied' => false];
        }

        $subscription = $matches->first();

        return ['status' => 'active', 'id' => $subscription->id,
            'starts_on' => $subscription->starts_on, 'ends_on' => $subscription->ends_on,
            'coverage_applied' => false];
    }
}
