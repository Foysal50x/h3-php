<?php

/**
 * Real-World Use Case: Fleet Management & Logistics (Dhaka, Bangladesh)
 *
 * This example demonstrates how H3 can be used for fleet management:
 * - Vehicle tracking and monitoring
 * - Route optimization
 * - Geofencing for restricted areas
 * - Coverage analysis
 * - Dispatch optimization
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

echo "=== Fleet Management & Logistics Example (Dhaka, Bangladesh) ===\n\n";

// Configuration
$TRACKING_RESOLUTION = 10;     // ~76m - vehicle tracking
$GEOFENCE_RESOLUTION = 8;      // ~461m - geofence zones
$COVERAGE_RESOLUTION = 7;      // ~1.4km - coverage analysis

// -----------------------------------------------------------------------------
// Part 1: Real-Time Vehicle Tracking
// -----------------------------------------------------------------------------

echo "PART 1: Real-Time Vehicle Tracking\n";
echo str_repeat("=", 50) . "\n\n";

class VehicleTracker
{
    private H3 $h3;
    private int $resolution;
    private array $vehicles = [];
    private array $vehicleHistory = [];

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function updatePosition(string $vehicleId, float $lat, float $lng, string $timestamp): void
    {
        $cell = $this->h3->latLngToCell($lat, $lng, $this->resolution);

        $previousCell = $this->vehicles[$vehicleId]['cell'] ?? null;
        $cellChanged = $previousCell !== null && $previousCell !== $cell;

        $this->vehicles[$vehicleId] = [
            'lat' => $lat,
            'lng' => $lng,
            'cell' => $cell,
            'cell_hex' => $this->h3->h3ToString($cell),
            'timestamp' => $timestamp,
            'cell_changed' => $cellChanged,
        ];

        // Track history
        if (!isset($this->vehicleHistory[$vehicleId])) {
            $this->vehicleHistory[$vehicleId] = [];
        }
        $this->vehicleHistory[$vehicleId][] = [
            'cell' => $cell,
            'lat' => $lat,
            'lng' => $lng,
            'timestamp' => $timestamp,
        ];
    }

    public function getVehicle(string $vehicleId): ?array
    {
        return $this->vehicles[$vehicleId] ?? null;
    }

    public function getAllVehicles(): array
    {
        return $this->vehicles;
    }

    public function getVehicleHistory(string $vehicleId): array
    {
        return $this->vehicleHistory[$vehicleId] ?? [];
    }

    public function getUniqueVisitedCells(string $vehicleId): array
    {
        $history = $this->vehicleHistory[$vehicleId] ?? [];
        return array_unique(array_column($history, 'cell'));
    }

    public function calculateDistanceTraveled(string $vehicleId): float
    {
        $history = $this->vehicleHistory[$vehicleId] ?? [];
        if (count($history) < 2) return 0;

        $totalDistance = 0;
        for ($i = 1; $i < count($history); $i++) {
            $totalDistance += $this->h3->greatCircleDistanceKm(
                $history[$i - 1]['lat'], $history[$i - 1]['lng'],
                $history[$i]['lat'], $history[$i]['lng']
            );
        }

        return $totalDistance;
    }
}

$tracker = new VehicleTracker($h3, $TRACKING_RESOLUTION);

// Simulate vehicle movements in Dhaka
$vehicleRoutes = [
    'TRUCK-001' => [
        ['lat' => 23.7590, 'lng' => 90.3926, 'time' => '08:00:00'],  // Tejgaon Warehouse
        ['lat' => 23.7650, 'lng' => 90.3950, 'time' => '08:15:00'],
        ['lat' => 23.7750, 'lng' => 90.4000, 'time' => '08:30:00'],
        ['lat' => 23.7850, 'lng' => 90.4050, 'time' => '08:45:00'],
        ['lat' => 23.7925, 'lng' => 90.4078, 'time' => '09:00:00'],  // Gulshan Delivery
    ],
    'TRUCK-002' => [
        ['lat' => 23.7461, 'lng' => 90.3742, 'time' => '08:00:00'],  // Dhanmondi Warehouse
        ['lat' => 23.7550, 'lng' => 90.3800, 'time' => '08:20:00'],
        ['lat' => 23.7662, 'lng' => 90.3588, 'time' => '08:40:00'],  // Mohammadpur
        ['lat' => 23.7800, 'lng' => 90.3650, 'time' => '09:00:00'],
    ],
    'VAN-001' => [
        ['lat' => 23.8759, 'lng' => 90.3795, 'time' => '08:00:00'],  // Uttara Depot
        ['lat' => 23.8650, 'lng' => 90.3750, 'time' => '08:10:00'],
        ['lat' => 23.8550, 'lng' => 90.3700, 'time' => '08:20:00'],
    ],
];

// Process all vehicle updates
foreach ($vehicleRoutes as $vehicleId => $route) {
    foreach ($route as $point) {
        $tracker->updatePosition($vehicleId, $point['lat'], $point['lng'], $point['time']);
    }
}

echo "Vehicle Status Report:\n\n";

foreach ($tracker->getAllVehicles() as $vehicleId => $data) {
    $distance = $tracker->calculateDistanceTraveled($vehicleId);
    $cellsVisited = count($tracker->getUniqueVisitedCells($vehicleId));

    echo sprintf("Vehicle: %s\n", $vehicleId);
    echo sprintf("  Current Position: (%.4f, %.4f)\n", $data['lat'], $data['lng']);
    echo sprintf("  Current Cell: %s\n", $data['cell_hex']);
    echo sprintf("  Last Update: %s\n", $data['timestamp']);
    echo sprintf("  Distance Traveled: %.2f km\n", $distance);
    echo sprintf("  Cells Visited: %d\n\n", $cellsVisited);
}

// -----------------------------------------------------------------------------
// Part 2: Geofencing System
// -----------------------------------------------------------------------------

echo "\nPART 2: Geofencing System\n";
echo str_repeat("=", 50) . "\n\n";

class GeofenceManager
{
    private H3 $h3;
    private int $resolution;
    private array $geofences = [];

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function createGeofence(string $id, string $name, float $lat, float $lng, int $radiusK, string $type = 'restrict'): void
    {
        $center = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        $cells = $this->h3->gridDisk($center, $radiusK);

        // Get boundary cells (outer ring)
        $boundaryCells = $this->h3->gridRing($center, $radiusK);

        $this->geofences[$id] = [
            'name' => $name,
            'type' => $type, // 'restrict', 'alert', 'checkpoint'
            'center' => $center,
            'center_coords' => ['lat' => $lat, 'lng' => $lng],
            'radius_k' => $radiusK,
            'cells' => $cells,
            'boundary_cells' => $boundaryCells,
            'cell_count' => count($cells),
        ];
    }

    public function checkVehicle(float $lat, float $lng): array
    {
        $vehicleCell = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        $alerts = [];

        foreach ($this->geofences as $id => $fence) {
            $inFence = in_array($vehicleCell, $fence['cells']);
            $onBoundary = in_array($vehicleCell, $fence['boundary_cells']);

            if ($inFence || $onBoundary) {
                $alerts[] = [
                    'geofence_id' => $id,
                    'geofence_name' => $fence['name'],
                    'type' => $fence['type'],
                    'status' => $onBoundary ? 'boundary' : 'inside',
                    'distance_to_center' => $this->h3->gridDistance($vehicleCell, $fence['center']),
                ];
            }
        }

        return $alerts;
    }

    public function getGeofenceBoundary(string $id): array
    {
        if (!isset($this->geofences[$id])) {
            return [];
        }

        $boundaryCoords = [];
        foreach ($this->geofences[$id]['boundary_cells'] as $cell) {
            $center = $this->h3->cellToLatLng($cell);
            $boundaryCoords[] = $center;
        }

        return $boundaryCoords;
    }

    public function getGeofences(): array
    {
        return $this->geofences;
    }
}

$geofenceManager = new GeofenceManager($h3, $GEOFENCE_RESOLUTION);

// Create geofences for Dhaka
$geofenceManager->createGeofence('GF001', 'Tejgaon Warehouse Zone', 23.7590, 90.3926, 2, 'checkpoint');
$geofenceManager->createGeofence('GF002', 'Cantonment Restricted Area', 23.8100, 90.4050, 1, 'restrict');
$geofenceManager->createGeofence('GF003', 'Gulshan Delivery Zone', 23.7925, 90.4078, 3, 'alert');
$geofenceManager->createGeofence('GF004', 'Uttara Depot Zone', 23.8759, 90.3795, 2, 'checkpoint');

echo "Geofences configured:\n\n";

foreach ($geofenceManager->getGeofences() as $id => $fence) {
    $area = 0;
    foreach ($fence['cells'] as $cell) {
        $area += $h3->cellAreaKm2($cell);
    }

    echo sprintf("  %s: %s\n", $id, $fence['name']);
    echo sprintf("    Type: %s\n", strtoupper($fence['type']));
    echo sprintf("    Center: (%.4f, %.4f)\n", $fence['center_coords']['lat'], $fence['center_coords']['lng']);
    echo sprintf("    Cells: %d (%.2f km²)\n\n", $fence['cell_count'], $area);
}

// Check vehicles against geofences
echo "--- Geofence Alerts ---\n\n";

foreach ($tracker->getAllVehicles() as $vehicleId => $data) {
    $alerts = $geofenceManager->checkVehicle($data['lat'], $data['lng']);

    if (count($alerts) > 0) {
        echo "Vehicle $vehicleId:\n";
        foreach ($alerts as $alert) {
            echo sprintf(
                "  [%s] %s - %s (distance: %d cells from center)\n",
                strtoupper($alert['type']),
                $alert['geofence_name'],
                $alert['status'],
                $alert['distance_to_center']
            );
        }
        echo "\n";
    }
}

// -----------------------------------------------------------------------------
// Part 3: Coverage Analysis
// -----------------------------------------------------------------------------

echo "\nPART 3: Service Coverage Analysis\n";
echo str_repeat("=", 50) . "\n\n";

class CoverageAnalyzer
{
    private H3 $h3;
    private int $resolution;

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function analyzeCoverage(array $vehicleHistories): array
    {
        $allVisitedCells = [];

        foreach ($vehicleHistories as $vehicleId => $history) {
            foreach ($history as $point) {
                $cell = $this->h3->latLngToCell($point['lat'], $point['lng'], $this->resolution);
                $cellHex = $this->h3->h3ToString($cell);

                if (!isset($allVisitedCells[$cellHex])) {
                    $allVisitedCells[$cellHex] = [
                        'cell' => $cell,
                        'visits' => 0,
                        'vehicles' => [],
                        'first_visit' => $point['timestamp'],
                        'last_visit' => $point['timestamp'],
                    ];
                }

                $allVisitedCells[$cellHex]['visits']++;
                $allVisitedCells[$cellHex]['last_visit'] = $point['timestamp'];

                if (!in_array($vehicleId, $allVisitedCells[$cellHex]['vehicles'])) {
                    $allVisitedCells[$cellHex]['vehicles'][] = $vehicleId;
                }
            }
        }

        // Calculate total coverage area
        $totalArea = 0;
        foreach ($allVisitedCells as $cellHex => &$data) {
            $data['area_km2'] = $this->h3->cellAreaKm2($data['cell']);
            $totalArea += $data['area_km2'];
        }

        return [
            'cells' => $allVisitedCells,
            'total_cells' => count($allVisitedCells),
            'total_area_km2' => $totalArea,
        ];
    }

    public function findCoverageGaps(array $targetArea, array $coveredCells): array
    {
        $gaps = [];

        foreach ($targetArea as $targetCell) {
            $found = false;
            foreach ($coveredCells as $covered) {
                if ($covered['cell'] === $targetCell) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $gaps[] = [
                    'cell' => $targetCell,
                    'cell_hex' => $this->h3->h3ToString($targetCell),
                    'center' => $this->h3->cellToLatLng($targetCell),
                ];
            }
        }

        return $gaps;
    }
}

$coverageAnalyzer = new CoverageAnalyzer($h3, $COVERAGE_RESOLUTION);

// Collect all vehicle histories
$allHistories = [];
foreach (array_keys($vehicleRoutes) as $vehicleId) {
    $allHistories[$vehicleId] = $tracker->getVehicleHistory($vehicleId);
}

$coverage = $coverageAnalyzer->analyzeCoverage($allHistories);

echo "Fleet Coverage Report:\n\n";
echo sprintf("  Total cells covered: %d\n", $coverage['total_cells']);
echo sprintf("  Total area covered: %.2f km²\n\n", $coverage['total_area_km2']);

echo "Coverage detail (cells with multiple visits):\n";
foreach ($coverage['cells'] as $cellHex => $data) {
    if ($data['visits'] > 1) {
        echo sprintf(
            "  %s: %d visits by %d vehicles\n",
            $cellHex,
            $data['visits'],
            count($data['vehicles'])
        );
    }
}

// -----------------------------------------------------------------------------
// Part 4: Dispatch Optimization
// -----------------------------------------------------------------------------

echo "\n\nPART 4: Dispatch Optimization\n";
echo str_repeat("=", 50) . "\n\n";

class DispatchOptimizer
{
    private H3 $h3;
    private int $resolution;

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function findNearestVehicle(array $vehicles, float $jobLat, float $jobLng): array
    {
        $jobCell = $this->h3->latLngToCell($jobLat, $jobLng, $this->resolution);
        $candidates = [];

        foreach ($vehicles as $vehicleId => $data) {
            $distance = $this->h3->greatCircleDistanceKm(
                $data['lat'], $data['lng'],
                $jobLat, $jobLng
            );

            try {
                $gridDistance = $this->h3->gridDistance($data['cell'], $jobCell);
            } catch (\Exception $e) {
                $gridDistance = 999;
            }

            // Estimate ETA (12 km/h average Dhaka speed)
            $etaMinutes = ($distance / 12) * 60;

            $candidates[] = [
                'vehicle_id' => $vehicleId,
                'distance_km' => $distance,
                'grid_distance' => $gridDistance,
                'eta_minutes' => ceil($etaMinutes),
            ];
        }

        usort($candidates, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

        return $candidates;
    }

    public function optimizeRoute(float $startLat, float $startLng, array $stops): array
    {
        // Simple nearest neighbor algorithm
        $route = [];
        $remaining = $stops;
        $currentLat = $startLat;
        $currentLng = $startLng;
        $totalDistance = 0;

        while (count($remaining) > 0) {
            $nearestIdx = null;
            $nearestDist = PHP_FLOAT_MAX;

            foreach ($remaining as $idx => $stop) {
                $dist = $this->h3->greatCircleDistanceKm(
                    $currentLat, $currentLng,
                    $stop['lat'], $stop['lng']
                );

                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearestIdx = $idx;
                }
            }

            $route[] = array_merge($remaining[$nearestIdx], [
                'distance_from_prev' => $nearestDist,
            ]);

            $totalDistance += $nearestDist;
            $currentLat = $remaining[$nearestIdx]['lat'];
            $currentLng = $remaining[$nearestIdx]['lng'];

            unset($remaining[$nearestIdx]);
            $remaining = array_values($remaining);
        }

        return [
            'route' => $route,
            'total_distance_km' => $totalDistance,
            'estimated_time_min' => ceil(($totalDistance / 12) * 60), // 12 km/h Dhaka speed
        ];
    }
}

$dispatcher = new DispatchOptimizer($h3, $TRACKING_RESOLUTION);

// New job request in Banani
$jobLocation = ['lat' => 23.7937, 'lng' => 90.4066];

echo "--- New Delivery Request ---\n";
echo sprintf("Location (Banani): (%.4f, %.4f)\n\n", $jobLocation['lat'], $jobLocation['lng']);

$candidates = $dispatcher->findNearestVehicle(
    $tracker->getAllVehicles(),
    $jobLocation['lat'],
    $jobLocation['lng']
);

echo "Available vehicles (sorted by distance):\n";
foreach ($candidates as $i => $c) {
    echo sprintf(
        "  %d. %s - %.2f km away (ETA: %d min)\n",
        $i + 1,
        $c['vehicle_id'],
        $c['distance_km'],
        $c['eta_minutes']
    );
}

echo sprintf("\nRecommendation: Dispatch %s\n", $candidates[0]['vehicle_id']);

// Route optimization
echo "\n--- Route Optimization ---\n\n";

$deliveryStops = [
    ['id' => 'STOP-A', 'name' => 'Gulshan 2', 'lat' => 23.7925, 'lng' => 90.4078],
    ['id' => 'STOP-B', 'name' => 'Banani 11', 'lat' => 23.7937, 'lng' => 90.4066],
    ['id' => 'STOP-C', 'name' => 'Baridhara', 'lat' => 23.8000, 'lng' => 90.4200],
    ['id' => 'STOP-D', 'name' => 'Bashundhara', 'lat' => 23.8135, 'lng' => 90.4250],
];

$startLocation = $tracker->getVehicle('TRUCK-001');
$optimizedRoute = $dispatcher->optimizeRoute(
    $startLocation['lat'],
    $startLocation['lng'],
    $deliveryStops
);

echo "Optimized delivery route for TRUCK-001:\n\n";
echo sprintf("Start: (%.4f, %.4f) - Gulshan\n\n", $startLocation['lat'], $startLocation['lng']);

foreach ($optimizedRoute['route'] as $i => $stop) {
    echo sprintf(
        "  %d. %s (%s) - %.2f km from previous\n",
        $i + 1,
        $stop['name'],
        $stop['id'],
        $stop['distance_from_prev']
    );
}

echo sprintf("\nTotal distance: %.2f km\n", $optimizedRoute['total_distance_km']);
echo sprintf("Estimated time: %d minutes (Dhaka traffic)\n", $optimizedRoute['estimated_time_min']);

// -----------------------------------------------------------------------------
// Part 5: Service Territory Planning
// -----------------------------------------------------------------------------

echo "\n\nPART 5: Service Territory Planning\n";
echo str_repeat("=", 50) . "\n\n";

function planServiceTerritories(H3 $h3, array $depots, int $resolution, int $radiusK): array
{
    $territories = [];
    $allCells = [];

    foreach ($depots as $depot) {
        $center = $h3->latLngToCell($depot['lat'], $depot['lng'], $resolution);
        $zoneCells = $h3->gridDisk($center, $radiusK);

        // Compact for efficient storage
        $compacted = $h3->compactCells($zoneCells);

        $totalArea = 0;
        foreach ($zoneCells as $cell) {
            $totalArea += $h3->cellAreaKm2($cell);
        }

        $territories[$depot['id']] = [
            'name' => $depot['name'],
            'center' => $center,
            'cells' => $zoneCells,
            'compacted' => $compacted,
            'area_km2' => $totalArea,
        ];

        foreach ($zoneCells as $cell) {
            if (!isset($allCells[$cell])) {
                $allCells[$cell] = [];
            }
            $allCells[$cell][] = $depot['id'];
        }
    }

    // Find overlaps
    $overlaps = [];
    foreach ($allCells as $cell => $depotIds) {
        if (count($depotIds) > 1) {
            $overlaps[$cell] = $depotIds;
        }
    }

    return [
        'territories' => $territories,
        'overlaps' => $overlaps,
        'overlap_count' => count($overlaps),
    ];
}

$depots = [
    ['id' => 'DEPOT-A', 'name' => 'Gulshan Hub', 'lat' => 23.7925, 'lng' => 90.4078],
    ['id' => 'DEPOT-B', 'name' => 'Dhanmondi Hub', 'lat' => 23.7461, 'lng' => 90.3742],
    ['id' => 'DEPOT-C', 'name' => 'Uttara Hub', 'lat' => 23.8759, 'lng' => 90.3795],
    ['id' => 'DEPOT-D', 'name' => 'Motijheel Hub', 'lat' => 23.7104, 'lng' => 90.4074],
];

$territoryPlan = planServiceTerritories($h3, $depots, $GEOFENCE_RESOLUTION, 3);

echo "Service Territory Plan:\n\n";

foreach ($territoryPlan['territories'] as $depotId => $territory) {
    echo sprintf("  %s (%s):\n", $depotId, $territory['name']);
    echo sprintf("    Coverage: %d cells (%.2f km²)\n", count($territory['cells']), $territory['area_km2']);
    echo sprintf("    Compacted: %d cells (%.0f%% compression)\n\n",
        count($territory['compacted']),
        (1 - count($territory['compacted']) / count($territory['cells'])) * 100
    );
}

echo sprintf("Territory overlaps: %d cells\n", $territoryPlan['overlap_count']);

if ($territoryPlan['overlap_count'] > 0) {
    echo "\nOverlap analysis (cells served by multiple hubs):\n";
    $shown = 0;
    foreach ($territoryPlan['overlaps'] as $cell => $depotIds) {
        if ($shown >= 5) {
            echo "  ... and " . ($territoryPlan['overlap_count'] - 5) . " more\n";
            break;
        }
        echo sprintf("  %s: served by %s\n", $h3->h3ToString($cell), implode(', ', $depotIds));
        $shown++;
    }
}

echo "\n=== End of Fleet Management Example ===\n";
