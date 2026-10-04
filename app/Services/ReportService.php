<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ZoneLog;
use Carbon\Carbon;
use InvalidArgumentException;

class ReportService
{
    /**
     * Build an event crowd occupancy report between $from and $to dates.
     */
    public function generateReport(Event $event, string $from, string $to): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            throw new InvalidArgumentException('Podaj poprawny format dat (YYYY-MM-DD).');
        }

        if ($from > $to) {
            throw new InvalidArgumentException('Data początkowa nie może być późniejsza niż data końcowa.');
        }

        $startDate = Carbon::parse($from)->startOfDay();
        $endDate = Carbon::parse($to)->addDay()->startOfDay();

        $event->loadMissing('zones');
        $zones = $event->zones;
        $zoneIds = $zones->pluck('id')->all();

        $logs = ZoneLog::whereIn('zone_id', $zoneIds)
            ->where('logged_at', '>=', $startDate)
            ->where('logged_at', '<', $endDate)
            ->orderBy('logged_at', 'asc')
            ->get();

        $logsByZone = $logs->groupBy('zone_id');
        $measurements = [];
        $zonesSummary = [];

        foreach ($zones as $zone) {
            $threshold = $zone->alert_threshold ?? 90;
            $zoneLogs = $logsByZone->get($zone->id, collect());

            $zoneSamples = [];
            foreach ($zoneLogs as $log) {
                $count = (int) $log->count;
                $capacity = max(1, (int) $zone->capacity);
                $percent = round(($count / $capacity) * 100, 1);
                $loggedAtIso = Carbon::parse($log->logged_at)->toISOString();

                $sample = [
                    'zone_id' => (string) $zone->id,
                    'zone_name' => $zone->name,
                    'logged_at' => $loggedAtIso,
                    'count' => $count,
                    'capacity' => (int) $zone->capacity,
                    'percent' => $percent,
                ];

                $zoneSamples[] = $sample;
                $measurements[] = $sample;
            }

            $sampleCount = count($zoneSamples);
            $counts = array_column($zoneSamples, 'count');
            $percents = array_column($zoneSamples, 'percent');

            $zonesSummary[] = [
                'id' => (string) $zone->id,
                'name' => $zone->name,
                'capacity' => (int) $zone->capacity,
                'threshold' => $threshold,
                'samples' => $sampleCount,
                'peak' => $sampleCount > 0 ? max($counts) : null,
                'peak_percent' => $sampleCount > 0 ? max($percents) : null,
                'average' => $sampleCount > 0 ? round(array_sum($counts) / $sampleCount, 1) : null,
                'critical_samples' => count(array_filter($zoneSamples, fn ($s) => $s['percent'] >= $threshold)),
            ];
        }

        // Sort all measurements by timestamp then zone name
        usort($measurements, function ($a, $b) {
            $cmp = strcmp($a['logged_at'], $b['logged_at']);
            return $cmp !== 0 ? $cmp : strcmp($a['zone_name'], $b['zone_name']);
        });

        // Group timeline by timestamp
        $timelineMap = [];
        foreach ($measurements as $sample) {
            $ts = Carbon::parse($sample['logged_at'])->getTimestampMs();
            if (!isset($timelineMap[$ts])) {
                $timelineMap[$ts] = [
                    'timestamp' => $ts,
                    'count' => 0,
                    'zones' => 0,
                ];
            }
            $timelineMap[$ts]['count'] += $sample['count'];
            $timelineMap[$ts]['zones'] += 1;
        }

        ksort($timelineMap);
        $timeline = array_values($timelineMap);

        $totalZones = count($zones);
        $timelineCounts = array_column($timeline, 'count');
        $measurementPercents = array_column($measurements, 'percent');

        $summary = [
            'measured_zones' => count(array_filter($zonesSummary, fn ($z) => $z['samples'] > 0)),
            'total_zones' => $totalZones,
            'samples' => count($measurements),
            'peak_count' => !empty($timelineCounts) ? max($timelineCounts) : null,
            'peak_percent' => !empty($measurementPercents) ? max($measurementPercents) : null,
            'partial' => !empty(array_filter($timeline, fn ($p) => $p['zones'] !== $totalZones)),
        ];

        return [
            'source' => 'api',
            'generated_at' => now()->toISOString(),
            'event' => [
                'id' => (string) $event->id,
                'name' => $event->name,
                'venue' => $event->venue,
                'start_at' => $event->start_at?->toISOString(),
                'end_at' => $event->end_at?->toISOString(),
                'max_capacity' => (int) $event->max_capacity,
                'status' => $event->status,
            ],
            'range' => [
                'from' => $from,
                'to' => $to,
            ],
            'zones' => $zonesSummary,
            'measurements' => $measurements,
            'timeline' => $timeline,
            'summary' => $summary,
        ];
    }

    /**
     * Export report data as UTF-8 BOM CSV.
     */
    public function toCsv(array $report, string $mode = 'summary'): string
    {
        $rows = [];
        $csvCell = function ($value) {
            $str = (string) ($value ?? '');
            if (preg_match('/^[=+\-@]/', $str)) {
                $str = "'" . $str;
            }
            return '"' . str_replace('"', '""', $str) . '"';
        };

        if ($mode === 'measurements') {
            $rows[] = ['Źródło', 'Wydarzenie', 'Od', 'Do', 'Strefa', 'Data i godzina', 'Osoby', 'Pojemność', 'Obciążenie %'];
            foreach ($report['measurements'] as $m) {
                $rows[] = [
                    $report['source'],
                    $report['event']['name'],
                    $report['range']['from'],
                    $report['range']['to'],
                    $m['zone_name'],
                    $m['logged_at'],
                    $m['count'],
                    $m['capacity'],
                    $m['percent'] . '%',
                ];
            }
        } else {
            $rows[] = ['Źródło', 'Wydarzenie', 'Od', 'Do', 'Strefa', 'Pojemność', 'Próg krytyczny', 'Liczba pomiarów', 'Szczyt (osoby)', 'Szczyt (%)', 'Średnia (osoby)', 'Przekroczenia progu'];
            foreach ($report['zones'] as $z) {
                $rows[] = [
                    $report['source'],
                    $report['event']['name'],
                    $report['range']['from'],
                    $report['range']['to'],
                    $z['name'],
                    $z['capacity'],
                    $z['threshold'] . '%',
                    $z['samples'],
                    $z['peak'] ?? 'brak danych',
                    $z['peak_percent'] !== null ? $z['peak_percent'] . '%' : 'brak danych',
                    $z['average'] ?? 'brak danych',
                    $z['critical_samples'],
                ];
            }
        }

        $lines = array_map(fn ($r) => implode(';', array_map($csvCell, $r)), $rows);
        return "\xEF\xBB\xBF" . implode("\r\n", $lines);
    }
}
