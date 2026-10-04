<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Simulation;
use App\Models\User;
use App\Models\Zone;
use InvalidArgumentException;

class SimulationService
{
    /**
     * Get simulation context for the specified event or default active event.
     */
    public function getContext(Event $event): array
    {
        $event->loadMissing('zones');

        $zones = $event->zones->map(function (Zone $z) {
            $nameLower = mb_strtolower($z->name);
            $type = $z->type ?: 'other';
            if ($type === 'other') {
                if (str_contains($nameLower, 'scena') || str_contains($nameLower, 'stage')) {
                    $type = 'stage';
                } elseif (str_contains($nameLower, 'wejście') || str_contains($nameLower, 'gate') || str_contains($nameLower, 'bramk')) {
                    $type = 'entrance';
                } elseif (str_contains($nameLower, 'wyjście') || str_contains($nameLower, 'exit') || str_contains($nameLower, 'parking')) {
                    $type = 'exit';
                }
            }

            return [
                'id' => (string) $z->id,
                'name' => $z->name,
                'shortName' => mb_strlen($z->name) > 15 ? mb_substr($z->name, 0, 12) . '…' : $z->name,
                'type' => $type,
                'capacity' => (int) $z->capacity,
                'currentCount' => (int) ($z->current_count ?? 0),
            ];
        })->values()->all();

        // Ensure at least one entrance and one stage if multiple zones exist
        $hasEntrance = false;
        $hasStage = false;
        foreach ($zones as $zone) {
            if ($zone['type'] === 'entrance') $hasEntrance = true;
            if ($zone['type'] === 'stage') $hasStage = true;
        }

        if (!$hasEntrance && isset($zones[0])) {
            $zones[0]['type'] = 'entrance';
        }
        if (!$hasStage && isset($zones[1])) {
            $zones[1]['type'] = 'stage';
        }

        return [
            'mode' => 'live',
            'event' => [
                'id' => (string) $event->id,
                'name' => $event->name,
            ],
            'zones' => $zones,
        ];
    }

    /**
     * Run dynamic crowd simulation according to Fruin LoS & Green Guide standards.
     */
    public function calculate(array $input, Event $event): array
    {
        $context = $this->getContext($event);
        $contextZones = $context['zones'];

        $scenario = $input['scenario'] ?? null;
        $zoneId = $input['zoneId'] ?? null;
        $peopleCount = (int) ($input['peopleCount'] ?? 0);
        $durationMinutes = (int) ($input['durationMinutes'] ?? 0);

        if (!in_array($scenario, ['entrance_closure', 'concert_end'], true)) {
            throw new InvalidArgumentException('Wybierz poprawny scenariusz symulacji.');
        }

        $sourceZone = collect($contextZones)->firstWhere('id', $zoneId);
        if (!$sourceZone) {
            throw new InvalidArgumentException('Wybrana strefa początkowa nie istnieje.');
        }

        $expectedType = $scenario === 'entrance_closure' ? 'entrance' : 'stage';
        if ($sourceZone['type'] !== $expectedType) {
            throw new InvalidArgumentException("Dla scenariusza {$scenario} wymagana jest strefa typu {$expectedType}.");
        }

        if ($peopleCount < 1 || $peopleCount > 100000) {
            throw new InvalidArgumentException('Liczba osób musi mieścić się w przedziale od 1 do 100 000.');
        }

        if ($durationMinutes < 1 || $durationMinutes > 120) {
            throw new InvalidArgumentException('Czas trwania musi wynosić od 1 do 120 minut.');
        }

        $destinationCandidates = array_values(array_filter($contextZones, fn ($z) => $z['id'] !== $zoneId));
        $destinationZones = [];
        $distributionWeights = [];

        if ($scenario === 'entrance_closure') {
            $primaryEntrances = array_values(array_filter($destinationCandidates, fn ($z) => $z['type'] === 'entrance'));
            $secondary = array_values(array_filter($destinationCandidates, fn ($z) => $z['type'] === 'exit' || str_contains(mb_strtolower($z['name']), 'parking')));
            $others = array_values(array_filter($destinationCandidates, fn ($z) => $z['type'] !== 'entrance' && $z['type'] !== 'exit' && !str_contains(mb_strtolower($z['name']), 'parking')));

            if (!empty($primaryEntrances)) {
                $destinationZones = array_merge($primaryEntrances, array_slice($secondary, 0, 1));
            } elseif (!empty($secondary)) {
                $destinationZones = array_merge($secondary, array_slice($others, 0, 1));
            } else {
                $destinationZones = array_slice($destinationCandidates, 0, 3);
            }

            $primaryCount = max(1, count(array_filter($destinationZones, fn ($z) => $z['type'] === 'entrance')));
            $hasSecondary = count(array_filter($destinationZones, fn ($z) => $z['type'] !== 'entrance')) > 0;
            $secondaryCount = max(1, count(array_filter($destinationZones, fn ($z) => $z['type'] !== 'entrance')));
            $primaryWeightTotal = $hasSecondary ? 0.75 : 1.0;
            $secondaryWeightTotal = $hasSecondary ? 0.25 : 0.0;

            foreach ($destinationZones as $z) {
                if ($z['type'] === 'entrance') {
                    $distributionWeights[] = $primaryWeightTotal / $primaryCount;
                } else {
                    $distributionWeights[] = $secondaryWeightTotal / $secondaryCount;
                }
            }
        } else {
            // concert_end
            $exits = array_values(array_filter($destinationCandidates, fn ($z) => $z['type'] === 'exit' || str_contains(mb_strtolower($z['name']), 'wyjście') || str_contains(mb_strtolower($z['name']), 'parking')));
            $amenities = array_values(array_filter($destinationCandidates, fn ($z) => $z['type'] === 'other' && !str_contains(mb_strtolower($z['name']), 'wyjście') && !str_contains(mb_strtolower($z['name']), 'parking')));
            usort($amenities, fn ($a, $b) => $b['capacity'] <=> $a['capacity']);
            $otherStages = array_values(array_filter($destinationCandidates, fn ($z) => $z['type'] === 'stage'));

            if (!empty($exits) || !empty($amenities) || !empty($otherStages)) {
                $destinationZones = array_merge($exits, array_slice($amenities, 0, 2), array_slice($otherStages, 0, 1));
            } else {
                $destinationZones = array_slice($destinationCandidates, 0, 3);
            }

            $totalExits = max(1, count($exits));
            $totalAmenities = max(1, min(2, count($amenities)));
            $totalStages = max(1, count($otherStages));

            $hasExits = count($exits) > 0;
            $hasAmenities = count($amenities) > 0;
            $hasStages = count($otherStages) > 0;

            $exitWeight = $hasExits ? 0.65 : 0;
            $amenityWeight = $hasAmenities ? 0.25 : 0;
            $stageWeight = $hasStages ? 0.10 : 0;

            $sumW = ($exitWeight + $amenityWeight + $stageWeight) ?: 1;
            $exitWeight /= $sumW;
            $amenityWeight /= $sumW;
            $stageWeight /= $sumW;

            foreach ($destinationZones as $z) {
                $isExit = $z['type'] === 'exit' || str_contains(mb_strtolower($z['name']), 'wyjście') || str_contains(mb_strtolower($z['name']), 'parking');
                if ($isExit) {
                    $distributionWeights[] = $exitWeight / $totalExits;
                } elseif ($z['type'] === 'stage') {
                    $distributionWeights[] = $stageWeight / $totalStages;
                } else {
                    $distributionWeights[] = $amenityWeight / $totalAmenities;
                }
            }
        }

        // Normalize weights
        $weightSum = array_sum($distributionWeights) ?: 1;
        $distributionWeights = array_map(fn ($w) => $w / $weightSum, $distributionWeights);

        // Zone impacts
        $zoneResults = [];
        foreach ($destinationZones as $idx => $zone) {
            $weight = $distributionWeights[$idx] ?? 0;
            $addedPeople = (int) round($peopleCount * $weight);
            $beforePercent = (int) round(($zone['currentCount'] / max(1, $zone['capacity'])) * 100);
            $afterCount = $zone['currentCount'] + $addedPeople;
            $afterPercent = (int) round(($afterCount / max(1, $zone['capacity'])) * 100);

            $zoneResults[] = [
                'zoneId' => $zone['id'],
                'name' => $zone['name'],
                'beforePercent' => $beforePercent,
                'afterPercent' => $afterPercent,
                'addedPeople' => $addedPeople,
            ];
        }

        // Find most impacted zone
        $mostImpacted = null;
        foreach ($zoneResults as $z) {
            if ($mostImpacted === null) {
                $mostImpacted = $z;
                continue;
            }
            $zDelta = $z['afterPercent'] - $z['beforePercent'];
            $bestDelta = $mostImpacted['afterPercent'] - $mostImpacted['beforePercent'];
            if ($zDelta > $bestDelta) {
                $mostImpacted = $z;
            } elseif ($zDelta === $bestDelta && $z['afterPercent'] > $mostImpacted['afterPercent']) {
                $mostImpacted = $z;
            }
        }

        $maxBefore = $mostImpacted['beforePercent'] ?? 40;
        $maxAfter = $mostImpacted['afterPercent'] ?? 40;

        // Build timeline (6 points from 0 to durationMinutes)
        $timeline = [];
        $steps = 5;
        for ($i = 0; $i <= $steps; $i++) {
            $minute = (int) round(($i / $steps) * $durationMinutes);
            $progress = $i / $steps;
            if ($scenario === 'entrance_closure') {
                $factor = sin(($progress * M_PI) / 2);
            } else {
                if ($progress <= 0.6) {
                    $factor = ($progress / 0.6) * 1.05;
                } else {
                    $factor = 1.05 - (0.05 * ($progress - 0.6) / 0.4);
                }
            }

            $delta = $maxAfter - $maxBefore;
            $occupancyPercent = $delta <= 0
                ? $maxAfter
                : (int) round($maxBefore + $delta * min(1.05, $factor));

            $timeline[] = [
                'minute' => $minute,
                'occupancyPercent' => $occupancyPercent,
            ];
        }

        $occupancyValues = array_column($timeline, 'occupancyPercent');
        $peakOccupancyPercent = max(!empty($occupancyValues) ? max($occupancyValues) : 0, $maxAfter);
        $zonesAtRisk = count(array_filter($zoneResults, fn ($z) => $z['afterPercent'] >= 90 && ($z['afterPercent'] > $z['beforePercent'] || $z['addedPeople'] > 0)));

        $timeToOverloadMinutes = null;
        if ($maxAfter >= 90) {
            if ($maxBefore >= 90) {
                $timeToOverloadMinutes = 0;
            } else {
                $found = null;
                foreach ($timeline as $point) {
                    if ($point['occupancyPercent'] >= 90) {
                        $found = $point;
                        break;
                    }
                }
                if ($found) {
                    $timeToOverloadMinutes = $found['minute'];
                } else {
                    $denom = max(1, $maxAfter - $maxBefore);
                    $timeToOverloadMinutes = max(1, (int) round(($durationMinutes * (90 - $maxBefore)) / $denom));
                }
            }
        }

        $flowRate = (int) round($peopleCount / max(1, $durationMinutes));
        $impactedName = $mostImpacted['name'] ?? 'docelowej';
        $formattedFlow = number_format($flowRate, 0, ',', ' ');
        $formattedSlow = number_format((int) round($flowRate * 0.6), 0, ',', ' ');

        if ($peakOccupancyPercent >= 110) {
            $recommendation = "Krytyczne przeciążenie ({$peakOccupancyPercent}% w strefie {$impactedName}). Szacowany napływ {$formattedFlow} os./min przekracza bezpieczną przepustowość ewakuacyjną (Fruin LoS F). Wymagane natychmiastowe uruchomienie buforów przedbramkowych i spowolnienie strumienia do maks. {$formattedSlow} os./min.";
        } elseif ($peakOccupancyPercent >= 90) {
            $timeText = $timeToOverloadMinutes !== null ? "{$timeToOverloadMinutes}. minucie" : 'czasie symulacji';
            $recommendation = "Podwyższone ryzyko zatoru ({$peakOccupancyPercent}% w strefie {$impactedName}). Przekroczenie progu bezpieczeństwa w {$timeText}. Skieruj służby porządkowe do kierowania strumieniem pieszych i udrożnij alternatywne przejścia.";
        } elseif ($peakOccupancyPercent >= 70) {
            $recommendation = "Ruch umiarkowany (szczyt {$peakOccupancyPercent}% w strefie {$impactedName}). Przepływ {$formattedFlow} os./min mieści się w dopuszczalnym standardzie Fruin LoS C. Monitoruj na bieżąco zapełnienie ciągów pieszych.";
        } else {
            $recommendation = "Przepływ w normie (szczyt {$peakOccupancyPercent}% w strefie {$impactedName}). Natężenie {$formattedFlow} os./min mieści się w bezpiecznym zakresie normy Fruin LoS A/B. Dostępna rezerwa przepustowości jest w pełni wystarczająca.";
        }

        $sourceAction = $scenario === 'entrance_closure' ? 'Zamknięcie wejścia' : 'Zakończenie koncertu';
        $destinationZoneIds = array_slice(array_column($zoneResults, 'zoneId'), 0, 2);

        return [
            'mode' => 'live',
            'status' => 'completed',
            'parameters' => $input,
            'summary' => [
                'peakOccupancyPercent' => $peakOccupancyPercent,
                'timeToOverloadMinutes' => $timeToOverloadMinutes,
                'zonesAtRisk' => $zonesAtRisk,
            ],
            'timeline' => $timeline,
            'zones' => array_map(fn ($z) => [
                'zoneId' => $z['zoneId'],
                'name' => $z['name'],
                'beforePercent' => $z['beforePercent'],
                'afterPercent' => $z['afterPercent'],
            ], $zoneResults),
            'flow' => [
                'sourceZoneId' => $sourceZone['id'],
                'sourceAction' => $sourceAction,
                'destinationZoneIds' => $destinationZoneIds,
                'recommendation' => $recommendation,
            ],
        ];
    }
}
