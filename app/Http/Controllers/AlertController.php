<?php

namespace App\Http\Controllers;

use App\Events\AlertCreated;
use App\Models\Alert;
use App\Models\Zone;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $query = Alert::with('zone');

        if ($request->has('event_id')) {
            $eventId = $request->event_id;
            $query->whereHas('zone', fn ($q) => $q->where('event_id', $eventId));
        }

        if ($request->has('zone_id') && $request->zone_id !== 'all') {
            $query->where('zone_id', $request->zone_id);
        }

        if ($request->has('level') && in_array($request->level, ['warning', 'critical'])) {
            $query->where('level', $request->level);
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
            $query->where(function ($q) use ($term) {
                $q->where('message', 'like', $term)
                  ->orWhereHas('zone', fn ($zq) => $zq->where('name', 'like', $term));
            });
        }

        $alerts = $query->orderBy('triggered_at', 'desc')->get();
        return response()->json($alerts);
    }

    public function show(string $id)
    {
        $alert = Alert::with(['zone', 'resolver'])->findOrFail($id);
        return response()->json($alert);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'zone_id' => 'required|uuid|exists:zones,id',
            'level' => 'required|in:warning,critical',
            'message' => 'required|string|min:2|max:500',
        ]);

        $zone = Zone::findOrFail($validated['zone_id']);

        $alert = Alert::create([
            'zone_id' => $zone->id,
            'level' => $validated['level'],
            'message' => $validated['message'],
            'triggered_at' => now(),
        ]);

        broadcast(new AlertCreated($alert, $zone->event_id));

        $alert->load('zone');
        return response()->json($alert, 201);
    }

    public function resolve(string $id, Request $request)
    {
        $alert = Alert::findOrFail($id);

        if ($alert->resolved_at) {
            return response()->json([
                'message' => 'Alert został już wcześniej rozwiązany.',
                'alert' => $alert,
            ]);
        }

        $alert->resolved_at = now();
        $alert->resolved_by = $request->user()?->id;
        $alert->save();

        $alert->load('zone');
        broadcast(new \App\Events\AlertResolved($alert, $alert->zone->event_id));

        return response()->json([
            'message' => 'Alert został pomyślnie rozwiązany.',
            'alert' => $alert,
        ]);
    }
}
