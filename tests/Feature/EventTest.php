<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Event;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_events_with_zones(): void
    {
        $event = Event::create([
            'name' => 'Festiwal Muzyki',
            'venue' => 'Arena',
            'start_at' => now(),
            'max_capacity' => 5000,
            'status' => 'active',
        ]);

        Zone::create([
            'event_id' => $event->id,
            'name' => 'Scena',
            'capacity' => 3000,
            'current_count' => 1500,
            'alert_threshold' => 90,
        ]);

        $response = $this->getJson('/api/v1/events');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Festiwal Muzyki')
            ->assertJsonPath('0.zones.0.name', 'Scena');
    }

    public function test_can_create_event_with_initial_zones(): void
    {
        $response = $this->postJson('/api/v1/events', [
            'name' => 'Nowe Wydarzenie',
            'venue' => 'Centrum Kongresowe',
            'start_at' => '2026-11-01 10:00:00',
            'end_at' => '2026-11-01 18:00:00',
            'max_capacity' => 2000,
            'status' => 'planned',
            'zones' => [
                [
                    'name' => 'Sala Główna',
                    'capacity' => 1200,
                    'alert_threshold' => 90,
                    'type' => 'stage',
                ],
                [
                    'name' => 'Hol Wejściowy',
                    'capacity' => 800,
                    'alert_threshold' => 85,
                    'type' => 'entrance',
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Nowe Wydarzenie')
            ->assertJsonCount(2, 'zones');

        $this->assertDatabaseHas('events', ['name' => 'Nowe Wydarzenie']);
        $this->assertDatabaseHas('zones', ['name' => 'Sala Główna']);
    }

    public function test_can_update_event(): void
    {
        $event = Event::create([
            'name' => 'Stara Nazwa',
            'venue' => 'Stary Obiekt',
            'start_at' => now(),
            'max_capacity' => 1000,
            'status' => 'planned',
        ]);

        $response = $this->putJson("/api/v1/events/{$event->id}", [
            'name' => 'Zaktualizowana Nazwa',
            'status' => 'active',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Zaktualizowana Nazwa')
            ->assertJsonPath('status', 'active');
    }

    public function test_can_delete_event(): void
    {
        $event = Event::create([
            'name' => 'Wydarzenie do usunięcia',
            'venue' => 'Klub',
            'start_at' => now(),
            'max_capacity' => 500,
            'status' => 'planned',
        ]);

        $response = $this->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_can_fetch_active_alerts_for_event(): void
    {
        $event = Event::create([
            'name' => 'Impreza',
            'venue' => 'Hala',
            'start_at' => now(),
            'max_capacity' => 3000,
            'status' => 'active',
        ]);

        $zone = Zone::create([
            'event_id' => $event->id,
            'name' => 'Sektor 1',
            'capacity' => 1000,
            'current_count' => 950,
            'alert_threshold' => 90,
        ]);

        Alert::create([
            'zone_id' => $zone->id,
            'level' => 'critical',
            'message' => 'Krytyczne zapełnienie!',
            'triggered_at' => now(),
        ]);

        Alert::create([
            'zone_id' => $zone->id,
            'level' => 'warning',
            'message' => 'Stary alert',
            'triggered_at' => now()->subHour(),
            'resolved_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/events/{$event->id}/alerts/active");

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.level', 'critical');
    }
}
