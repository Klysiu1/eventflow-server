<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationTest extends TestCase
{
    use RefreshDatabase;

    protected Event $event;
    protected Zone $entranceZone;
    protected Zone $stageZone;
    protected Zone $exitZone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'name' => 'Festiwal Muzyki',
            'venue' => 'Stadion',
            'start_at' => now(),
            'max_capacity' => 20000,
            'status' => 'active',
        ]);

        $this->entranceZone = Zone::create([
            'event_id' => $this->event->id,
            'name' => 'Wejście Główne',
            'type' => 'entrance',
            'capacity' => 5000,
            'current_count' => 4500,
            'alert_threshold' => 90,
        ]);

        $this->stageZone = Zone::create([
            'event_id' => $this->event->id,
            'name' => 'Scena Główna',
            'type' => 'stage',
            'capacity' => 10000,
            'current_count' => 7000,
            'alert_threshold' => 90,
        ]);

        $this->exitZone = Zone::create([
            'event_id' => $this->event->id,
            'name' => 'Wyjście Wschód',
            'type' => 'exit',
            'capacity' => 5000,
            'current_count' => 1000,
            'alert_threshold' => 90,
        ]);
    }

    public function test_can_fetch_simulation_context(): void
    {
        $response = $this->getJson("/api/v1/simulations/context?eventId={$this->event->id}");

        $response->assertStatus(200)
            ->assertJsonPath('mode', 'live')
            ->assertJsonPath('event.id', $this->event->id)
            ->assertJsonCount(3, 'zones');
    }

    public function test_can_run_entrance_closure_simulation(): void
    {
        $response = $this->postJson('/api/v1/simulations/run', [
            'eventId' => $this->event->id,
            'scenario' => 'entrance_closure',
            'zoneId' => $this->entranceZone->id,
            'peopleCount' => 3000,
            'durationMinutes' => 15,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('mode', 'live')
            ->assertJsonPath('status', 'completed')
            ->assertJsonStructure([
                'mode',
                'status',
                'summary' => ['peakOccupancyPercent', 'timeToOverloadMinutes', 'zonesAtRisk'],
                'timeline',
                'zones',
                'flow' => ['sourceZoneId', 'sourceAction', 'destinationZoneIds', 'recommendation'],
                'simulationId',
            ]);

        $this->assertDatabaseHas('simulations', [
            'event_id' => $this->event->id,
        ]);
    }

    public function test_can_run_concert_end_simulation(): void
    {
        $response = $this->postJson('/api/v1/simulations/run', [
            'eventId' => $this->event->id,
            'scenario' => 'concert_end',
            'zoneId' => $this->stageZone->id,
            'peopleCount' => 5000,
            'durationMinutes' => 20,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('mode', 'live')
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('flow.sourceAction', 'Zakończenie koncertu');
    }

    public function test_can_list_simulations(): void
    {
        $this->postJson('/api/v1/simulations/run', [
            'eventId' => $this->event->id,
            'scenario' => 'concert_end',
            'zoneId' => $this->stageZone->id,
            'peopleCount' => 2000,
            'durationMinutes' => 10,
        ]);

        $response = $this->getJson("/api/v1/simulations?eventId={$this->event->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }
}
