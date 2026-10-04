<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Event;
use App\Models\Zone;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::with('zones');

        if ($request->has('status') && in_array($request->status, ['planned', 'active', 'ended'])) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $term = '%' . $request->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('venue', 'like', $term);
            });
        }

        $events = $query->orderBy('start_at', 'desc')->get();
        return response()->json($events);
    }

    public function show(string $id)
    {
        $event = Event::with('zones')->findOrFail($id);
        return response()->json($event);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:200',
            'venue' => 'required|string|min:2|max:200',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after:start_at',
            'max_capacity' => 'required|integer|min:1|max:1000000',
            'status' => 'required|in:planned,active,ended',
            'zones' => 'nullable|array',
            'zones.*.name' => 'required|string|min:2|max:100',
            'zones.*.capacity' => 'required|integer|min:1',
            'zones.*.alert_threshold' => 'nullable|numeric|min:1|max:100',
            'zones.*.coordinates' => 'nullable',
            'zones.*.area' => 'nullable|array',
            'zones.*.type' => 'nullable|string',
        ]);

        $event = Event::create([
            'name' => $validated['name'],
            'venue' => $validated['venue'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'] ?? null,
            'max_capacity' => $validated['max_capacity'],
            'status' => $validated['status'],
            'created_by' => $request->user()?->id,
        ]);

        if (!empty($validated['zones'])) {
            foreach ($validated['zones'] as $zData) {
                $threshold = $zData['alert_threshold'] ?? 90;
                $event->zones()->create([
                    'name' => $zData['name'],
                    'type' => $zData['type'] ?? 'other',
                    'capacity' => $zData['capacity'],
                    'current_count' => 0,
                    'alert_threshold' => $threshold,
                    'coordinates' => $zData['coordinates'] ?? null,
                    'area' => $zData['area'] ?? null,
                ]);
            }
        }

        $event->load('zones');
        return response()->json($event, 201);
    }

    public function update(Request $request, string $id)
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:2|max:200',
            'venue' => 'sometimes|required|string|min:2|max:200',
            'start_at' => 'sometimes|required|date',
            'end_at' => 'nullable|date|after:start_at',
            'max_capacity' => 'sometimes|required|integer|min:1|max:1000000',
            'status' => 'sometimes|required|in:planned,active,ended',
        ]);

        $event->update($validated);
        $event->load('zones');

        return response()->json($event);
    }

    public function destroy(string $id)
    {
        $event = Event::findOrFail($id);
        $event->delete();

        return response()->json(['message' => 'Wydarzenie zostało usunięte.']);
    }

    public function activeAlerts(string $eventId)
    {
        $alerts = Alert::with('zone')
            ->whereHas('zone', function ($query) use ($eventId) {
                $query->where('event_id', $eventId);
            })
            ->whereNull('resolved_at')
            ->orderBy('triggered_at', 'desc')
            ->get();

        return response()->json($alerts);
    }

    public function alerts(string $eventId, Request $request)
    {
        $query = Alert::with('zone')
            ->whereHas('zone', function ($q) use ($eventId) {
                $q->where('event_id', $eventId);
            });

        if ($request->has('level') && in_array($request->level, ['warning', 'critical'])) {
            $query->where('level', $request->level);
        }

        if ($request->has('zone_id') && $request->zone_id !== 'all') {
            $query->where('zone_id', $request->zone_id);
        }

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->whereNull('resolved_at');
            } elseif ($request->status === 'resolved') {
                $query->whereNotNull('resolved_at');
            }
        }

        if ($request->has('search')) {
            $term = '%' . $request->search . '%';
            $query->where('message', 'like', $term);
        }

        $alerts = $query->orderBy('triggered_at', 'desc')->get();
        return response()->json($alerts);
    }
}
