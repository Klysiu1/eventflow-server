<?php

namespace App\Http\Controllers;

use App\Events\AlertCreated;
use App\Events\ZoneOccupancyUpdated;
use App\Models\Alert;
use App\Models\Event;
use App\Models\Zone;
use App\Models\ZoneLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index(string $eventId)
    {
        $zones = Zone::where('event_id', $eventId)->orderBy('name')->get();
        return response()->json($zones);
    }

    public function show(string $id)
    {
        $zone = Zone::with('event')->findOrFail($id);
        return response()->json($zone);
    }

    public function store(Request $request, ?string $eventId = null)
    {
        $targetEventId = $eventId ?? $request->input('event_id');

        $validated = $request->validate([
            'event_id' => $eventId ? 'nullable' : 'required|uuid|exists:events,id',
            'name' => 'required|string|min:2|max:100',
            'capacity' => 'required|integer|min:1|max:1000000',
            'alert_threshold' => 'nullable|numeric|min:1|max:100',
            'type' => 'nullable|string|max:50',
            'coordinates' => 'nullable',
            'area' => 'nullable|array',
            'current_count' => 'nullable|integer|min:0',
        ]);

        $event = Event::findOrFail($targetEventId);

        $threshold = $validated['alert_threshold'] ?? 90;
        $currentCount = $validated['current_count'] ?? 0;

        $zone = $event->zones()->create([
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'other',
            'capacity' => $validated['capacity'],
            'current_count' => $currentCount,
            'alert_threshold' => $threshold,
            'coordinates' => $validated['coordinates'] ?? null,
            'area' => $validated['area'] ?? null,
        ]);

        if ($currentCount > 0) {
            ZoneLog::create([
                'zone_id' => $zone->id,
                'count' => $currentCount,
                'logged_at' => now(),
                'source' => 'manual',
            ]);
        }

        return response()->json($zone, 201);
    }

    public function update(Request $request, string $id)
    {
        $zone = Zone::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:2|max:100',
            'capacity' => 'sometimes|required|integer|min:1|max:1000000',
            'alert_threshold' => 'sometimes|nullable|numeric|min:1|max:100',
            'type' => 'sometimes|nullable|string|max:50',
            'coordinates' => 'sometimes|nullable',
            'area' => 'sometimes|nullable|array',
            'current_count' => 'sometimes|nullable|integer|min:0',
        ]);

        $oldCount = $zone->current_count;
        $zone->update($validated);

        if (isset($validated['current_count']) && $validated['current_count'] !== $oldCount) {
            ZoneLog::create([
                'zone_id' => $zone->id,
                'count' => $zone->current_count,
                'logged_at' => now(),
                'source' => 'manual',
            ]);
            broadcast(new ZoneOccupancyUpdated($zone));
        }

        return response()->json($zone);
    }

    public function destroy(string $id)
    {
        $zone = Zone::findOrFail($id);
        $zone->delete();

        return response()->json(['message' => 'Strefa została usunięta.']);
    }

    public function updateOccupancy(Request $request)
    {
        $request->validate([
            'zoneId' => 'required|uuid',
            'count' => 'required|integer|min:0',
            'source' => 'nullable|string|in:sensor,manual,simulation',
        ]);

        $zone = Zone::findOrFail($request->zoneId);
        $zone->current_count = $request->count;
        $zone->save();

        ZoneLog::create([
            'zone_id' => $zone->id,
            'count' => $zone->current_count,
            'logged_at' => now(),
            'source' => $request->input('source', 'sensor'),
        ]);

        broadcast(new ZoneOccupancyUpdated($zone));

        $occupancyRate = $zone->current_count / max(1, $zone->capacity);
        $thresholdPercent = $zone->alert_threshold ?? 90;
        $criticalThreshold = $thresholdPercent / 100.0;
        $warningThreshold = 0.70;

        $level = null;
        if ($occupancyRate >= $criticalThreshold) {
            $level = 'critical';
        } elseif ($occupancyRate >= $warningThreshold) {
            $level = 'warning';
        }

        $newAlert = null;
        if ($level) {
            $existingAlert = Alert::where('zone_id', $zone->id)
                ->whereNull('resolved_at')
                ->where('level', $level)
                ->first();

            if (!$existingAlert) {
                // If elevating from warning to critical, resolve old warning
                if ($level === 'critical') {
                    Alert::where('zone_id', $zone->id)
                        ->whereNull('resolved_at')
                        ->where('level', 'warning')
                        ->update(['resolved_at' => now()]);
                }

                $percentRounded = round($occupancyRate * 100);
                $levelText = $level === 'critical' ? 'Krytyczne przepełnienie' : 'Wysokie zagęszczenie';
                $newAlert = Alert::create([
                    'zone_id' => $zone->id,
                    'level' => $level,
                    'message' => "{$levelText}! Zapełnienie strefy {$zone->name} wynosi {$percentRounded}%.",
                    'triggered_at' => now(),
                ]);

                broadcast(new AlertCreated($newAlert, $zone->event_id));
            }
        } else {
            // Under 70%: resolve all active alerts
            Alert::where('zone_id', $zone->id)
                ->whereNull('resolved_at')
                ->update(['resolved_at' => now()]);
        }

        return response()->json([
            'zone' => $zone,
            'occupancy_rate' => $occupancyRate,
            'alert' => $newAlert,
        ]);
    }

    public function history(string $id, Request $request)
    {
        $zone = Zone::findOrFail($id);

        $limit = (int) $request->input('limit', 100);
        $minutes = $request->input('minutes');

        $query = ZoneLog::where('zone_id', $zone->id);

        if ($minutes) {
            $query->where('logged_at', '>=', now()->subMinutes((int) $minutes));
        }

        $samples = $query->orderBy('logged_at', 'asc')
            ->limit($limit)
            ->get(['logged_at', 'count', 'source'])
            ->map(fn ($l) => [
                'logged_at' => Carbon::parse($l->logged_at)->toISOString(),
                'count' => (int) $l->count,
                'source' => $l->source,
            ]);

        return response()->json([
            'source' => 'api',
            'zone_id' => $zone->id,
            'zone_name' => $zone->name,
            'capacity' => $zone->capacity,
            'samples' => $samples,
        ]);
    }
}
