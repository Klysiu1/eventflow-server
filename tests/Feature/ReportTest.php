<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Zone;
use App\Models\ZoneLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_generate_event_report(): void
    {
        $today = Carbon::today()->format('Y-m-d');

        $event = Event::create([
            'name' => 'Festiwal Muzyki',
            'venue' => 'Stadion',
            'start_at' => "{$today} 12:00:00",
            'max_capacity' => 10000,
            'status' => 'active',
        ]);

        $zone = Zone::create([
            'event_id' => $event->id,
            'name' => 'Sektor 1',
            'capacity' => 5000,
            'current_count' => 4500,
            'alert_threshold' => 90,
        ]);

        ZoneLog::create([
            'zone_id' => $zone->id,
            'count' => 3000,
            'logged_at' => "{$today} 13:00:00",
        ]);

        ZoneLog::create([
            'zone_id' => $zone->id,
            'count' => 4600,
            'logged_at' => "{$today} 14:00:00",
        ]);

        $response = $this->getJson("/api/v1/events/{$event->id}/reports?from={$today}&to={$today}");

        $response->assertStatus(200)
            ->assertJsonPath('source', 'api')
            ->assertJsonPath('event.name', 'Festiwal Muzyki')
            ->assertJsonPath('zones.0.samples', 2)
            ->assertJsonPath('zones.0.peak', 4600)
            ->assertJsonPath('zones.0.critical_samples', 1)
            ->assertJsonStructure([
                'source',
                'generated_at',
                'event',
                'range',
                'zones',
                'measurements',
                'timeline',
                'summary' => ['measured_zones', 'total_zones', 'samples', 'peak_count'],
            ]);
    }

    public function test_can_export_report_as_csv(): void
    {
        $today = Carbon::today()->format('Y-m-d');

        $event = Event::create([
            'name' => 'Festiwal Muzyki',
            'venue' => 'Stadion',
            'start_at' => "{$today} 12:00:00",
            'max_capacity' => 10000,
            'status' => 'active',
        ]);

        $zone = Zone::create([
            'event_id' => $event->id,
            'name' => 'Sektor 1',
            'capacity' => 5000,
            'current_count' => 2000,
            'alert_threshold' => 90,
        ]);

        ZoneLog::create([
            'zone_id' => $zone->id,
            'count' => 2000,
            'logged_at' => "{$today} 13:00:00",
        ]);

        $response = $this->get("/api/v1/events/{$event->id}/reports/export?from={$today}&to={$today}&mode=summary");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('Festiwal Muzyki', $response->getContent());
        $this->assertStringContainsString('Sektor 1', $response->getContent());
    }
}
