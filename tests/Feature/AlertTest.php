<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Event;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_and_filter_alerts(): void
    {
        $event = Event::create([
            'name' => 'Event',
            'venue' => 'Venue',
            'start_at' => now(),
            'max_capacity' => 1000,
            'status' => 'active',
        ]);

        $zone = Zone::create([
            'event_id' => $event->id,
            'name' => 'Brama A',
            'capacity' => 1000,
            'current_count' => 900,
        ]);

        Alert::create([
            'zone_id' => $zone->id,
            'level' => 'critical',
            'message' => 'Zator na bramce A',
            'triggered_at' => now(),
        ]);

        Alert::create([
            'zone_id' => $zone->id,
            'level' => 'warning',
            'message' => 'Ostrzeżenie',
            'triggered_at' => now()->subHour(),
            'resolved_at' => now(),
        ]);

        $allResponse = $this->getJson('/api/v1/alerts');
        $allResponse->assertStatus(200)->assertJsonCount(2);

        $criticalResponse = $this->getJson('/api/v1/alerts?level=critical');
        $criticalResponse->assertStatus(200)->assertJsonCount(1)->assertJsonPath('0.level', 'critical');

        $activeResponse = $this->getJson('/api/v1/alerts?status=active');
        $activeResponse->assertStatus(200)->assertJsonCount(1);
    }

    public function test_can_create_manual_alert(): void
    {
        $event = Event::create([
            'name' => 'Event',
            'venue' => 'Venue',
            'start_at' => now(),
            'max_capacity' => 1000,
            'status' => 'active',
        ]);

        $zone = Zone::create([
            'event_id' => $event->id,
            'name' => 'Sektor B',
            'capacity' => 1000,
            'current_count' => 800,
        ]);

        $response = $this->postJson('/api/v1/alerts', [
            'zone_id' => $zone->id,
            'level' => 'warning',
            'message' => 'Ręczne zgłoszenie dyspozytora: interwencja ochrony',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('level', 'warning')
            ->assertJsonPath('message', 'Ręczne zgłoszenie dyspozytora: interwencja ochrony');

        $this->assertDatabaseHas('alerts', [
            'zone_id' => $zone->id,
            'level' => 'warning',
        ]);
    }

    public function test_can_resolve_alert(): void
    {
        $event = Event::create([
            'name' => 'Event',
            'venue' => 'Venue',
            'start_at' => now(),
            'max_capacity' => 1000,
            'status' => 'active',
        ]);

        $zone = Zone::create([
            'event_id' => $event->id,
            'name' => 'Sektor C',
            'capacity' => 1000,
            'current_count' => 800,
        ]);

        $alert = Alert::create([
            'zone_id' => $zone->id,
            'level' => 'critical',
            'message' => 'Przepełnienie!',
            'triggered_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/alerts/{$alert->id}/resolve");

        $response->assertStatus(200)
            ->assertJsonPath('alert.id', $alert->id);

        $this->assertDatabaseMissing('alerts', [
            'id' => $alert->id,
            'resolved_at' => null,
        ]);
    }
}
