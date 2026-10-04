<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with realistic crowd monitoring data.
     */
    public function run(): void
    {
        // 1. Users
        $admin = User::create([
            'name' => 'Administrator Systemu',
            'email' => 'admin@eventflow.pl',
            'password_hash' => Hash::make('password123'),
            'role' => 'admin',
            'last_active' => now(),
        ]);

        $organizer = User::create([
            'name' => 'Jan Kowalski (Koordynator)',
            'email' => 'klysiudev@zohomail.eu',
            'password_hash' => Hash::make('makapaka'),
            'role' => 'organizer',
            'last_active' => now(),
        ]);

        $viewer = User::create([
            'name' => 'Służby Ratownicze / Obserwator',
            'email' => 'viewer@eventflow.pl',
            'password_hash' => Hash::make('password123'),
            'role' => 'viewer',
            'last_active' => now(),
        ]);

        // Settings for organizer
        Setting::create([
            'user_id' => $organizer->id,
            'profile' => [
                'name' => $organizer->name,
                'email' => $organizer->email,
                'organization' => 'Kraków Live Security',
            ],
            'notifications' => [
                'critical' => true,
                'warning' => true,
                'sound' => true,
                'browser' => false,
            ],
            'thresholds' => [
                'warning' => 70,
                'critical' => 90,
            ],
        ]);

        // 2. Active Event
        $festival = Event::create([
            'name' => 'Rock Festival Kraków 2026',
            'venue' => 'Stadion Główny',
            'start_at' => now()->startOfDay()->addHours(12),
            'end_at' => now()->startOfDay()->addHours(23)->addMinutes(59),
            'max_capacity' => 25000,
            'status' => 'active',
            'created_by' => $organizer->id,
        ]);

        // Zones for active festival
        $stageZone = Zone::create([
            'event_id' => $festival->id,
            'name' => 'Strefa A - Scena Główna',
            'type' => 'stage',
            'capacity' => 10000,
            'current_count' => 8200,
            'alert_threshold' => 90,
            'coordinates' => ['lat' => 50.0647, 'lng' => 19.9450],
            'area' => ['x' => 35, 'y' => 10, 'width' => 30, 'height' => 30],
        ]);

        $foodZone = Zone::create([
            'event_id' => $festival->id,
            'name' => 'Strefa B - Food Court & Gastro',
            'type' => 'other',
            'capacity' => 5000,
            'current_count' => 2400,
            'alert_threshold' => 85,
            'coordinates' => ['lat' => 50.0632, 'lng' => 19.9430],
            'area' => ['x' => 10, 'y' => 55, 'width' => 25, 'height' => 25],
        ]);

        $entryZone = Zone::create([
            'event_id' => $festival->id,
            'name' => 'Brama Główna Zachodnia',
            'type' => 'entrance',
            'capacity' => 4000,
            'current_count' => 3650,
            'alert_threshold' => 90,
            'coordinates' => ['lat' => 50.0620, 'lng' => 19.9410],
            'area' => ['x' => 5, 'y' => 15, 'width' => 20, 'height' => 30],
        ]);

        $exitZone = Zone::create([
            'event_id' => $festival->id,
            'name' => 'Wyjście Północne / Parking',
            'type' => 'exit',
            'capacity' => 6000,
            'current_count' => 1800,
            'alert_threshold' => 90,
            'coordinates' => ['lat' => 50.0670, 'lng' => 19.9460],
            'area' => ['x' => 75, 'y' => 15, 'width' => 20, 'height' => 35],
        ]);

        // 3. Historical logs (past 6 hours, 15-minute intervals)
        $now = now();
        $baseCounts = [
            $stageZone->id => [2000, 3100, 4500, 5600, 6800, 7500, 7900, 8100, 8200],
            $foodZone->id => [800, 1200, 1800, 2600, 2900, 2700, 2500, 2400, 2400],
            $entryZone->id => [1200, 1800, 2400, 3100, 3400, 3800, 3900, 3750, 3650],
            $exitZone->id => [300, 450, 700, 950, 1200, 1400, 1600, 1750, 1800],
        ];

        $steps = 9;
        for ($i = 0; $i < $steps; $i++) {
            $timestamp = Carbon::parse($now)->subMinutes(($steps - 1 - $i) * 20);
            foreach ($baseCounts as $zoneId => $curve) {
                ZoneLog::create([
                    'zone_id' => $zoneId,
                    'count' => $curve[$i],
                    'logged_at' => $timestamp,
                    'source' => 'sensor',
                ]);
            }
        }

        // 4. Alerts
        // Active warning for Stage
        Alert::create([
            'zone_id' => $stageZone->id,
            'level' => 'warning',
            'message' => 'Wysokie zagęszczenie! Zapełnienie strefy ' . $stageZone->name . ' wynosi 82%.',
            'triggered_at' => now()->subMinutes(18),
        ]);

        // Active critical for Entrance Gate
        Alert::create([
            'zone_id' => $entryZone->id,
            'level' => 'critical',
            'message' => 'Krytyczne przepełnienie! Zapełnienie strefy ' . $entryZone->name . ' wynosi 91%. Zator przedbramkowy.',
            'triggered_at' => now()->subMinutes(8),
        ]);

        // Resolved historical alert
        Alert::create([
            'zone_id' => $foodZone->id,
            'level' => 'warning',
            'message' => 'Wysokie zagęszczenie! Zapełnienie strefy ' . $foodZone->name . ' wynosi 72%.',
            'triggered_at' => now()->subHours(2),
            'resolved_at' => now()->subHours(1),
            'resolved_by' => $organizer->id,
        ]);

        // 5. Planned Event
        $marathon = Event::create([
            'name' => 'Półmaraton Warszawski 2026',
            'venue' => 'Błonia PGE Narodowego',
            'start_at' => now()->addDays(14)->setTime(9, 0),
            'end_at' => now()->addDays(14)->setTime(16, 0),
            'max_capacity' => 15000,
            'status' => 'planned',
            'created_by' => $admin->id,
        ]);

        Zone::create([
            'event_id' => $marathon->id,
            'name' => 'Miasteczko Biegacza & Scena',
            'type' => 'stage',
            'capacity' => 8000,
            'current_count' => 0,
            'alert_threshold' => 90,
            'area' => ['x' => 10, 'y' => 10, 'width' => 40, 'height' => 35],
        ]);

        Zone::create([
            'event_id' => $marathon->id,
            'name' => 'Bramka Startowa A',
            'type' => 'entrance',
            'capacity' => 4000,
            'current_count' => 0,
            'alert_threshold' => 90,
            'area' => ['x' => 60, 'y' => 10, 'width' => 30, 'height' => 20],
        ]);

        Zone::create([
            'event_id' => $marathon->id,
            'name' => 'Strefa Finiszu i Medali',
            'type' => 'exit',
            'capacity' => 3000,
            'current_count' => 0,
            'alert_threshold' => 90,
            'area' => ['x' => 60, 'y' => 40, 'width' => 30, 'height' => 30],
        ]);
    }
}
