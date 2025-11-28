<?php

/**
 * Real-World Use Case: Ride Sharing Application (Pathao/Uber Style - Dhaka)
 *
 * This example demonstrates how H3 can be used to build core features
 * of a ride-sharing application like Pathao or Uber in Dhaka:
 * - Driver location indexing and lookup
 * - Surge pricing zones
 * - Estimated pickup times
 * - Service area boundaries
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

echo "=== Ride Sharing Application Example (Dhaka, Bangladesh) ===\n\n";

// Configuration
$DRIVER_RESOLUTION = 9;      // ~174m - good for driver tracking
$SURGE_RESOLUTION = 7;       // ~1.4km - good for surge zones
$SERVICE_RESOLUTION = 6;     // ~3.2km - service area boundaries

// -----------------------------------------------------------------------------
// Part 1: Driver Location Index
// -----------------------------------------------------------------------------

echo "PART 1: Driver Location Management\n";
echo str_repeat("=", 50) . "\n\n";

class DriverLocationIndex
{
    private H3 $h3;
    private int $resolution;
    private array $driversByCell = [];
    private array $driverLocations = [];

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function updateDriverLocation(string $driverId, float $lat, float $lng, string $vehicleType = 'Bike'): void
    {
        // Remove from old cell if exists
        if (isset($this->driverLocations[$driverId])) {
            $oldCell = $this->driverLocations[$driverId]['cell'];
            $this->driversByCell[$oldCell] = array_filter(
                $this->driversByCell[$oldCell] ?? [],
                fn($id) => $id !== $driverId
            );
        }

        // Add to new cell
        $cell = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        $this->driverLocations[$driverId] = [
            'cell' => $cell,
            'lat' => $lat,
            'lng' => $lng,
            'vehicle' => $vehicleType,
            'timestamp' => time(),
        ];

        if (!isset($this->driversByCell[$cell])) {
            $this->driversByCell[$cell] = [];
        }
        $this->driversByCell[$cell][] = $driverId;
    }

    public function findNearbyDrivers(float $lat, float $lng, int $maxDistance = 3): array
    {
        $userCell = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        $searchCells = $this->h3->gridDisk($userCell, $maxDistance);

        $nearbyDrivers = [];
        foreach ($searchCells as $cell) {
            if (isset($this->driversByCell[$cell])) {
                foreach ($this->driversByCell[$cell] as $driverId) {
                    $driver = $this->driverLocations[$driverId];
                    $distance = $this->h3->greatCircleDistanceM($lat, $lng, $driver['lat'], $driver['lng']);
                    $nearbyDrivers[] = [
                        'id' => $driverId,
                        'vehicle' => $driver['vehicle'],
                        'distance' => $distance,
                        'cell' => $this->h3->h3ToString($driver['cell']),
                    ];
                }
            }
        }

        usort($nearbyDrivers, fn($a, $b) => $a['distance'] <=> $b['distance']);
        return $nearbyDrivers;
    }

    public function getDriverCount(): int
    {
        return count($this->driverLocations);
    }
}

// Initialize driver index
$driverIndex = new DriverLocationIndex($h3, $DRIVER_RESOLUTION);

// Simulate drivers around Dhaka
$drivers = [
    'D001' => ['lat' => 23.7925, 'lng' => 90.4078, 'vehicle' => 'Bike'],      // Gulshan 2
    'D002' => ['lat' => 23.7937, 'lng' => 90.4066, 'vehicle' => 'Car'],       // Banani
    'D003' => ['lat' => 23.7104, 'lng' => 90.4074, 'vehicle' => 'CNG'],       // Motijheel
    'D004' => ['lat' => 23.7461, 'lng' => 90.3742, 'vehicle' => 'Bike'],      // Dhanmondi
    'D005' => ['lat' => 23.7590, 'lng' => 90.3926, 'vehicle' => 'Car'],       // Tejgaon
    'D006' => ['lat' => 23.7920, 'lng' => 90.4080, 'vehicle' => 'Bike'],      // Near Gulshan
];

echo "Registering drivers:\n";
foreach ($drivers as $id => $loc) {
    $driverIndex->updateDriverLocation($id, $loc['lat'], $loc['lng'], $loc['vehicle']);
    echo "  $id ({$loc['vehicle']}) registered at ({$loc['lat']}, {$loc['lng']})\n";
}

echo "\nTotal drivers online: " . $driverIndex->getDriverCount() . "\n";

// Find drivers for a ride request
$riderLat = 23.7930;  // Gulshan area
$riderLng = 90.4075;

echo "\n--- Ride Request ---\n";
echo "Pickup location (Gulshan): ($riderLat, $riderLng)\n\n";

$nearbyDrivers = $driverIndex->findNearbyDrivers($riderLat, $riderLng);

echo "Nearby drivers:\n";
foreach (array_slice($nearbyDrivers, 0, 3) as $i => $driver) {
    // Assuming 15 km/h average speed in Dhaka traffic
    $eta = round(($driver['distance'] / 1000) / 15 * 60);
    echo sprintf(
        "  %d. %s (%s) - %.0f m away (~%d min ETA)\n",
        $i + 1,
        $driver['id'],
        $driver['vehicle'],
        $driver['distance'],
        max(1, $eta)
    );
}

// -----------------------------------------------------------------------------
// Part 2: Surge Pricing Zones
// -----------------------------------------------------------------------------

echo "\n\nPART 2: Surge Pricing System\n";
echo str_repeat("=", 50) . "\n\n";

class SurgePricingManager
{
    private H3 $h3;
    private int $resolution;
    private array $demandByCell = [];
    private array $supplyByCell = [];

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function recordRideRequest(float $lat, float $lng): void
    {
        $cell = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        $cellHex = $this->h3->h3ToString($cell);

        if (!isset($this->demandByCell[$cellHex])) {
            $this->demandByCell[$cellHex] = 0;
        }
        $this->demandByCell[$cellHex]++;
    }

    public function recordDriverAvailable(float $lat, float $lng): void
    {
        $cell = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        $cellHex = $this->h3->h3ToString($cell);

        if (!isset($this->supplyByCell[$cellHex])) {
            $this->supplyByCell[$cellHex] = 0;
        }
        $this->supplyByCell[$cellHex]++;
    }

    public function getSurgeMultiplier(float $lat, float $lng): float
    {
        $cell = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        $cellHex = $this->h3->h3ToString($cell);

        $demand = $this->demandByCell[$cellHex] ?? 0;
        $supply = $this->supplyByCell[$cellHex] ?? 1;

        if ($supply === 0) $supply = 1;

        $ratio = $demand / $supply;

        // Surge multiplier based on demand/supply ratio
        if ($ratio <= 1) return 1.0;
        if ($ratio <= 2) return 1.25;
        if ($ratio <= 3) return 1.5;
        if ($ratio <= 4) return 1.75;
        if ($ratio <= 5) return 2.0;
        return min(3.0, 1.0 + ($ratio * 0.25));
    }

    public function getSurgeZones(): array
    {
        $zones = [];
        foreach ($this->demandByCell as $cellHex => $demand) {
            $cell = $this->h3->stringToH3($cellHex);
            $supply = $this->supplyByCell[$cellHex] ?? 1;

            $center = $this->h3->cellToLatLng($cell);
            $boundary = $this->h3->cellToBoundary($cell);

            $zones[] = [
                'cell' => $cellHex,
                'demand' => $demand,
                'supply' => $supply,
                'multiplier' => $this->getSurgeMultiplier($center['lat'], $center['lng']),
                'center' => $center,
                'boundary' => $boundary,
            ];
        }

        usort($zones, fn($a, $b) => $b['multiplier'] <=> $a['multiplier']);
        return $zones;
    }
}

$surgeManager = new SurgePricingManager($h3, $SURGE_RESOLUTION);

// Simulate ride requests (high demand in Gulshan during office hours)
$requestLocations = [
    // Gulshan area (high demand - office district)
    ['lat' => 23.7925, 'lng' => 90.4078],
    ['lat' => 23.7930, 'lng' => 90.4080],
    ['lat' => 23.7920, 'lng' => 90.4075],
    ['lat' => 23.7928, 'lng' => 90.4082],
    ['lat' => 23.7935, 'lng' => 90.4070],
    ['lat' => 23.7922, 'lng' => 90.4076],
    ['lat' => 23.7932, 'lng' => 90.4085],
    ['lat' => 23.7918, 'lng' => 90.4072],
    // Motijheel (moderate demand - business district)
    ['lat' => 23.7104, 'lng' => 90.4074],
    ['lat' => 23.7110, 'lng' => 90.4080],
    ['lat' => 23.7100, 'lng' => 90.4070],
];

foreach ($requestLocations as $loc) {
    $surgeManager->recordRideRequest($loc['lat'], $loc['lng']);
}

// Record driver availability
foreach ($drivers as $loc) {
    $surgeManager->recordDriverAvailable($loc['lat'], $loc['lng']);
}

echo "Surge pricing zones:\n\n";

$surgeZones = $surgeManager->getSurgeZones();
foreach (array_slice($surgeZones, 0, 3) as $zone) {
    echo sprintf(
        "  Zone: %s\n    Demand: %d requests | Supply: %d drivers\n    Surge: %.2fx\n\n",
        $zone['cell'],
        $zone['demand'],
        $zone['supply'],
        $zone['multiplier']
    );
}

// Calculate fare with surge (Bangladeshi pricing)
$baseFare = 25;        // BDT base fare
$perKm = 12;           // BDT per km
$perMin = 2;           // BDT per minute
$distance = 5.2;       // km
$duration = 25;        // minutes (Dhaka traffic!)

$testLat = 23.7925;    // Gulshan
$testLng = 90.4078;
$surge = $surgeManager->getSurgeMultiplier($testLat, $testLng);

$normalFare = $baseFare + ($perKm * $distance) + ($perMin * $duration);
$surgeFare = $normalFare * $surge;

echo "--- Fare Calculation (Gulshan to Dhanmondi) ---\n";
echo sprintf("Route: %.1f km, %d minutes\n", $distance, $duration);
echo sprintf("Normal fare: ৳%.0f\n", $normalFare);
echo sprintf("Surge multiplier: %.2fx\n", $surge);
echo sprintf("Surge fare: ৳%.0f\n", $surgeFare);

// -----------------------------------------------------------------------------
// Part 3: Service Area Management
// -----------------------------------------------------------------------------

echo "\n\nPART 3: Service Area Boundaries\n";
echo str_repeat("=", 50) . "\n\n";

class ServiceAreaManager
{
    private H3 $h3;
    private int $resolution;
    private array $serviceCells = [];

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function addServiceArea(float $lat, float $lng, int $radius): void
    {
        $center = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        $cells = $this->h3->gridDisk($center, $radius);

        foreach ($cells as $cell) {
            $this->serviceCells[$cell] = true;
        }
    }

    public function isInServiceArea(float $lat, float $lng): bool
    {
        $cell = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        return isset($this->serviceCells[$cell]);
    }

    public function getServiceAreaCellCount(): int
    {
        return count($this->serviceCells);
    }

    public function getServiceAreaKm2(): float
    {
        $totalArea = 0;
        foreach (array_keys($this->serviceCells) as $cell) {
            $totalArea += $this->h3->cellAreaKm2($cell);
        }
        return $totalArea;
    }

    public function getBoundaryEdges(): array
    {
        $boundaryEdges = [];

        foreach (array_keys($this->serviceCells) as $cell) {
            $edges = $this->h3->originToDirectedEdges($cell);

            foreach ($edges as $edge) {
                if (!$this->h3->isValidDirectedEdge($edge)) {
                    continue;
                }

                $dest = $this->h3->getDirectedEdgeDestination($edge);
                if (!isset($this->serviceCells[$dest])) {
                    $boundaryEdges[] = $edge;
                }
            }
        }

        return $boundaryEdges;
    }
}

$serviceManager = new ServiceAreaManager($h3, $SERVICE_RESOLUTION);

// Define service area covering major Dhaka neighborhoods
$serviceManager->addServiceArea(23.7925, 90.4078, 3);  // Gulshan/Banani
$serviceManager->addServiceArea(23.7461, 90.3742, 2);  // Dhanmondi
$serviceManager->addServiceArea(23.7104, 90.4074, 2);  // Motijheel
$serviceManager->addServiceArea(23.8759, 90.3795, 2);  // Uttara

echo "Service area configured:\n";
echo sprintf("  Total cells: %d\n", $serviceManager->getServiceAreaCellCount());
echo sprintf("  Total area: %.2f km²\n\n", $serviceManager->getServiceAreaKm2());

// Check if locations are within service area
$testLocations = [
    ['name' => 'Gulshan 2', 'lat' => 23.7925, 'lng' => 90.4078],
    ['name' => 'Dhanmondi 27', 'lat' => 23.7461, 'lng' => 90.3742],
    ['name' => 'Uttara Sector 7', 'lat' => 23.8759, 'lng' => 90.3795],
    ['name' => 'Savar', 'lat' => 23.8583, 'lng' => 90.2667],
    ['name' => 'Narayanganj', 'lat' => 23.6238, 'lng' => 90.5000],
];

echo "Service area coverage:\n";
foreach ($testLocations as $loc) {
    $inService = $serviceManager->isInServiceArea($loc['lat'], $loc['lng']);
    echo sprintf(
        "  %s: %s\n",
        $loc['name'],
        $inService ? 'AVAILABLE' : 'OUT OF SERVICE AREA'
    );
}

// -----------------------------------------------------------------------------
// Part 4: ETA Calculation
// -----------------------------------------------------------------------------

echo "\n\nPART 4: ETA Calculation\n";
echo str_repeat("=", 50) . "\n\n";

function calculateETA(H3 $h3, float $driverLat, float $driverLng, float $pickupLat, float $pickupLng): array
{
    // Get direct distance
    $distanceM = $h3->greatCircleDistanceM($driverLat, $driverLng, $pickupLat, $pickupLng);
    $distanceKm = $distanceM / 1000;

    // Get grid distance for path complexity estimate
    $driverCell = $h3->latLngToCell($driverLat, $driverLng, 9);
    $pickupCell = $h3->latLngToCell($pickupLat, $pickupLng, 9);

    try {
        $gridDistance = $h3->gridDistance($driverCell, $pickupCell);
    } catch (\Exception $e) {
        $gridDistance = 0;
    }

    // Estimate actual road distance (typically 1.4x direct distance in Dhaka due to roads)
    $roadDistanceKm = $distanceKm * 1.4;

    // Calculate ETA based on average Dhaka speed (12 km/h during peak, 20 km/h off-peak)
    $avgSpeedKmH = 15; // Average considering Dhaka traffic
    $etaMinutes = ($roadDistanceKm / $avgSpeedKmH) * 60;

    return [
        'direct_distance_km' => $distanceKm,
        'road_distance_km' => $roadDistanceKm,
        'grid_distance_cells' => $gridDistance,
        'eta_minutes' => ceil($etaMinutes),
    ];
}

// Calculate ETA for closest driver
if (count($nearbyDrivers) > 0) {
    $closestDriver = $nearbyDrivers[0];
    $driverLoc = $drivers[$closestDriver['id']];

    $eta = calculateETA($h3, $driverLoc['lat'], $driverLoc['lng'], $riderLat, $riderLng);

    echo "ETA for closest driver ({$closestDriver['id']} - {$closestDriver['vehicle']}):\n";
    echo sprintf("  Direct distance: %.2f km\n", $eta['direct_distance_km']);
    echo sprintf("  Est. road distance: %.2f km\n", $eta['road_distance_km']);
    echo sprintf("  Grid distance: %d cells\n", $eta['grid_distance_cells']);
    echo sprintf("  Estimated arrival: %d minutes\n", $eta['eta_minutes']);
}

// -----------------------------------------------------------------------------
// Part 5: Vehicle Type Filtering
// -----------------------------------------------------------------------------

echo "\n\nPART 5: Vehicle Type Selection\n";
echo str_repeat("=", 50) . "\n\n";

$vehicleTypes = ['Bike', 'CNG', 'Car'];

foreach ($vehicleTypes as $type) {
    $filteredDrivers = array_filter($nearbyDrivers, fn($d) => $d['vehicle'] === $type);
    $count = count($filteredDrivers);

    if ($count > 0) {
        $closest = array_values($filteredDrivers)[0];
        $eta = calculateETA($h3, $drivers[$closest['id']]['lat'], $drivers[$closest['id']]['lng'], $riderLat, $riderLng);

        echo sprintf("  %s: %d available, nearest %dm away (~%d min)\n",
            $type, $count, round($closest['distance']), $eta['eta_minutes']);
    } else {
        echo sprintf("  %s: No drivers available\n", $type);
    }
}

echo "\n=== End of Ride Sharing Example ===\n";
