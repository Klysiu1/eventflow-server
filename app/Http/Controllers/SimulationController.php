<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Simulation;
use App\Services\SimulationService;
use Illuminate\Http\Request;

class SimulationController extends Controller
{
    public function __construct(
        protected SimulationService $simulationService
    ) {}

    public function context(Request $request, ?string $eventId = null)
    {
        $targetEventId = $eventId ?? $request->input('event_id') ?? $request->input('eventId');

        $event = $targetEventId
            ? Event::with('zones')->findOrFail($targetEventId)
            : Event::with('zones')->where('status', 'active')->first() ?? Event::with('zones')->first();

        if (!$event) {
            return response()->json([
                'message' => 'Brak dostępnych wydarzeń do symulacji.',
            ], 404);
        }

        $context = $this->simulationService->getContext($event);
        return response()->json($context);
    }

    public function run(Request $request)
    {
        $validated = $request->validate([
            'eventId' => 'required|uuid|exists:events,id',
            'scenario' => 'required|in:entrance_closure,concert_end',
            'zoneId' => 'required|uuid|exists:zones,id',
            'peopleCount' => 'required|integer|min:1|max:100000',
            'durationMinutes' => 'required|integer|min:1|max:120',
        ]);

        $event = Event::with('zones')->findOrFail($validated['eventId']);

        $results = $this->simulationService->calculate($validated, $event);

        $simulation = Simulation::create([
            'event_id' => $event->id,
            'scenario' => $validated,
            'results' => $results,
            'created_by' => $request->user()?->id,
        ]);

        $results['simulationId'] = $simulation->id;
        return response()->json($results);
    }

    public function index(?string $eventId = null)
    {
        $query = Simulation::with('event');
        if ($eventId) {
            $query->where('event_id', $eventId);
        }

        $simulations = $query->orderBy('created_at', 'desc')->limit(50)->get();
        return response()->json($simulations);
    }

    public function show(string $id)
    {
        $simulation = Simulation::with(['event', 'creator'])->findOrFail($id);
        return response()->json($simulation);
    }
}
