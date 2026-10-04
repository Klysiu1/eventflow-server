<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Event;
use App\Models\Zone;
use App\Models\ZoneLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_update_zone(): void
    {
        $event = Event::create([
            'name' => 'Test Event',
            'venue' => 'Venue',
            'start_at' => now(),
            'max_capacity' => 5000,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/v1/events/{$event->id}/zones", [
            'name' => 'Strefa VIP',
            'type' => 'stage',
            'capacity' => 500,
            'alert_threshold' => 85,
            'area' => ['x' => 10, 'y' => 10, 'width' => 20, 'height' => 20],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Strefa VIP')
            ->assertJsonPath('capacity', 500)
            ->assertJsonPath('alert_threshold', 85);

        $zoneId = $response->json('id');

        $updateResponse = $this->putJson("/api/v1/zones/{$zoneId}", [
            'name' => 'Strefa VIP Gold',
            'capacity' => 600,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('name', 'Strefa VIP Gold')
            ->assertJsonPath('capacity', 600);
    }

    public function test_update_occupancy_triggers_critical_alert(): void
    {
        $event = Event::create([
            'name' => 'Koncert',
            'venue' => 'Klub',
            'start_at' => now(),
            'max_capacity' => 1000,
            'status' => 'active',
        ]);

        $zone = Zone::create([
            'event_id' => $event->id,
            'name' => 'Parkiet',
            'capacity' => 1000,
            'current_count' => 500,
            'alert_threshold' => 90,
        ]);

        $response = $this->postJson('/api/v1/zones/update', [
            'zoneId' => $zone->id,
            'count' => 950,
            'source' => 'sensor',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('zone.current_count', 950)
            ->assertJsonPath('alert.level', 'critical');

        $this->assertDatabaseHas('zone_logs', [
            'zone_id' => $zone->id,
            'count' => 950,
        ]);

        $this->assertDatabaseHas('alerts', [
            'zone_id' => $zone->id,
            'level' => 'critical',
            'resolved_at' => null,
        ]);
    }

    public function test_update_occupancy_resolves_alert_when_safe(): void
    {
        $event = Event::create([
            'name' => 'Koncert',
            'venue' => 'Klub',
            'start_at' => now(),
            'max_capacity' => 1000,
            'status' => 'active',
        ]);

        $zone = Zone::create([
            'event_id' => $event->id,
            'name' => 'Parkiet',
            'capacity' => 1000,
            'current_count' => 950,
            'alert_threshold' => 90,
        ]);

        Alert::create([
            'zone_id' => $zone->id,
            'level' => 'critical',
            'message' => 'Krytyczne przepełnienie!',
            'triggered_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/zones/update', [
            'zoneId' => $zone->id,
            'count' => 500,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('alerts', [
            'zone_id' => $zone->id,
            'resolved_at' => null,
        ]);
    }

    public function test_can_fetch_zone_history(): void
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
            'name' => 'Strefa',
            'capacity' => 1000,
            'current_count' => 500,
        ]);

        ZoneLog::create([
            'zone_id' => $zone->id,
            'count' => 400,
            'logged_at' => now()->subMinutes(10),
            'source' => 'sensor',
        ]);

        ZoneLog::create([
            'zone_id' => $zone->id,
            'count' => 500,
            'logged_at' => now(),
            'source' => 'sensor',
        ]);

        $response = $this->getJson("/api/v1/zones/{$zone->id}/history");

        $response->assertStatus(200)
            ->assertJsonPath('source', 'api')
            ->assertJsonCount(2, 'samples');
    }
}
