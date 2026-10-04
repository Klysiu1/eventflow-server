<?php

namespace App\Console\Commands;

use App\Events\AlertCreated;
use App\Events\AlertResolved;
use App\Events\ZoneOccupancyUpdated;
use App\Models\Alert;
use App\Models\Event;
use App\Models\Zone;
use App\Models\ZoneLog;
use Illuminate\Console\Command;

class CrowdStreamCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crowd:stream 
                            {eventId? : Identyfikator wydarzenia (opcjonalny, domyślnie aktywne)} 
                            {--interval=2 : Czas odstępu w sekundach między odczytami} 
                            {--steps=0 : Liczba kroków symulacji (0 = działa w nieskończoność)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Symulator sensorów IoT generujący ruch tłumu i rozsyłający dane w czasie rzeczywistym';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $eventId = $this->argument('eventId');
        $interval = max(1, (int) $this->option('interval'));
        $maxSteps = (int) $this->option('steps');

        $event = $eventId
            ? Event::with('zones')->find($eventId)
            : Event::with('zones')->where('status', 'active')->first() ?? Event::with('zones')->first();

        if (!$event) {
            $this->error('Nie znaleziono żadnego wydarzenia do symulacji. Uruchom "php artisan db:seed".');
            return Command::FAILURE;
        }

        $this->info("==========================================================");
        $this->info("   EventFlow · Symulator Sensorów IoT w Czasie Rzeczywistym");
        $this->info("==========================================================");
        $this->line("Wydarzenie: <fg=cyan>{$event->name}</> ({$event->venue})");
        $this->line("Liczba stref: <fg=yellow>" . count($event->zones) . "</>");
        $this->line("Interwał pomiarów: <fg=green>{$interval}s</>");
        $this->line("Naciśnij <fg=red>Ctrl+C</>, aby zatrzymać strumieniowanie.\n");

        $step = 0;

        while (true) {
            $step++;
            $event->load('zones');
            $tableRows = [];

            foreach ($event->zones as $zone) {
                // Realistic random crowd fluctuation based on zone type
                $type = $zone->type ?? 'other';
                $delta = 0;

                switch ($type) {
                    case 'entrance':
                        // Fluctuates around high ingress flow
                        $delta = rand(-40, 120);
                        break;
                    case 'stage':
                        // Crowd concentration
                        $delta = rand(-50, 90);
                        break;
                    case 'exit':
                        // People exiting or moving to parking
                        $delta = rand(-60, 80);
                        break;
                    default:
                        $delta = rand(-40, 50);
                        break;
                }

                $newCount = max(50, min((int) ($zone->capacity * 1.10), $zone->current_count + $delta));
                $zone->current_count = $newCount;
                $zone->save();

                // Log measurement
                ZoneLog::create([
                    'zone_id' => $zone->id,
                    'count' => $newCount,
                    'logged_at' => now(),
                    'source' => 'sensor',
                ]);

                // Broadcast occupancy update
                broadcast(new ZoneOccupancyUpdated($zone));

                // Check occupancy rate and manage alerts
                $occupancyRate = $newCount / max(1, $zone->capacity);
                $percent = round($occupancyRate * 100);
                $threshold = ($zone->alert_threshold ?? 90) / 100.0;

                $level = null;
                if ($occupancyRate >= $threshold) {
                    $level = 'critical';
                } elseif ($occupancyRate >= 0.70) {
                    $level = 'warning';
                }

                $alertStatus = '-';

                if ($level) {
                    $existingAlert = Alert::where('zone_id', $zone->id)
                        ->whereNull('resolved_at')
                        ->where('level', $level)
                        ->first();

                    if (!$existingAlert) {
                        if ($level === 'critical') {
                            Alert::where('zone_id', $zone->id)
                                ->whereNull('resolved_at')
                                ->where('level', 'warning')
                                ->update(['resolved_at' => now()]);
                        }

                        $alert = Alert::create([
                            'zone_id' => $zone->id,
                            'level' => $level,
                            'message' => ($level === 'critical' ? 'Krytyczne przepełnienie' : 'Wysokie zagęszczenie') . "! Zapełnienie strefy {$zone->name} wynosi {$percent}%.",
                            'triggered_at' => now(),
                        ]);

                        broadcast(new AlertCreated($alert, $zone->event_id));
                        $alertStatus = "<fg=red>NOWY ALERT: {$level}</>";
                    } else {
                        $alertStatus = "<fg=yellow>Aktywny: {$level}</>";
                    }
                } else {
                    // Safe occupancy (< 70%): auto-resolve any active alerts
                    $activeAlerts = Alert::where('zone_id', $zone->id)->whereNull('resolved_at')->get();
                    if ($activeAlerts->isNotEmpty()) {
                        foreach ($activeAlerts as $a) {
                            $a->resolved_at = now();
                            $a->save();
                            broadcast(new AlertResolved($a, $zone->event_id));
                        }
                        $alertStatus = "<fg=green>ROZWIĄZANO</>";
                    }
                }

                $statusLabel = $percent >= 90
                    ? "<fg=red>{$percent}% [KRYTYCZNY]</>"
                    : ($percent >= 70 ? "<fg=yellow>{$percent}% [OSTRZEŻENIE]</>" : "<fg=green>{$percent}% [BEZPIECZNY]</>");

                $tableRows[] = [
                    $zone->name,
                    $type,
                    number_format($zone->capacity, 0, ',', ' '),
                    number_format($newCount, 0, ',', ' '),
                    $statusLabel,
                    $alertStatus,
                ];
            }

            $timeStr = now()->toTimeString();
            $this->line("\n<fg=gray>[{$timeStr}] Krok {$step}: Aktualizacja sensorów IoT</>");
            $this->table(
                ['Strefa', 'Typ', 'Pojemność', 'Liczba osób', 'Obciążenie', 'Status alertu'],
                $tableRows
            );

            if ($maxSteps > 0 && $step >= $maxSteps) {
                $this->info("Zakończono zaplanowaną liczbę kroków ({$maxSteps}).");
                break;
            }

            sleep($interval);
        }

        return Command::SUCCESS;
    }
}
