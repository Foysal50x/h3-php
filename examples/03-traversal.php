<?php

/**
 * H3 Traversal Functions - Real World Examples (Bangladesh/Dhaka)
 *
 * This file demonstrates grid traversal functions:
 * - gridDisk: Get all cells within k distance (filled disk)
 * - gridDiskDistances: Get cells with their distances
 * - gridRing: Get cells at exactly k distance (hollow ring)
 * - gridDistance: Calculate grid distance between cells
 * - gridPathCells: Get path of cells between two points
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

echo "=== H3 Traversal Functions Examples (Dhaka, Bangladesh) ===\n\n";

// -----------------------------------------------------------------------------
// Example 1: Restaurant Search Radius
// Real-world scenario: Find all restaurants within a certain distance
// -----------------------------------------------------------------------------

echo "1. Restaurant Search Within Radius\n";
echo str_repeat("-", 50) . "\n";

// User's current location (Gulshan 2 Circle, Dhaka)
$userLat = 23.7925;
$userLng = 90.4078;
$resolution = 9; // ~174m edge length

$userCell = $h3->latLngToCell($userLat, $userLng, $resolution);

echo "User location: Gulshan 2 Circle, Dhaka\n";
echo "User cell: " . $h3->h3ToString($userCell) . "\n\n";

// Search within k=2 rings (roughly 350m radius at res 9)
$k = 2;
$nearbyCells = $h3->gridDisk($userCell, $k);

echo "Searching within k=$k rings (~" . ($k * 174) . "m radius):\n";
echo "Total cells to search: " . count($nearbyCells) . "\n\n";

// Simulated restaurant database indexed by H3 cell
$restaurantDb = [
    $h3->h3ToString($userCell) => ['Star Kabab', 'Nando\'s'],
];

// Add some restaurants in neighboring cells
$neighbors = $h3->gridDisk($userCell, 1);
if (count($neighbors) > 1) {
    $restaurantDb[$h3->h3ToString($neighbors[1])] = ['Pizza Hut'];
}
if (count($neighbors) > 3) {
    $restaurantDb[$h3->h3ToString($neighbors[3])] = ['KFC', 'Chillox'];
}

echo "Nearby restaurants found:\n";
foreach ($nearbyCells as $cell) {
    $cellHex = $h3->h3ToString($cell);
    if (isset($restaurantDb[$cellHex])) {
        foreach ($restaurantDb[$cellHex] as $restaurant) {
            echo "  - $restaurant\n";
        }
    }
}

// -----------------------------------------------------------------------------
// Example 2: Delivery Zone Tiers with Distance Pricing
// Real-world scenario: Calculate delivery fees based on distance tiers
// -----------------------------------------------------------------------------

echo "\n\n2. Delivery Zone Distance Pricing\n";
echo str_repeat("-", 50) . "\n";

$storeCell = $h3->latLngToCell(23.7461, 90.3742, 9); // Dhanmondi
echo "Store location (Dhanmondi) cell: " . $h3->h3ToString($storeCell) . "\n\n";

// Get cells with their distances for tiered pricing
$maxDistance = 5;
$cellsWithDistances = $h3->gridDiskDistances($storeCell, $maxDistance);

// Count cells in each tier
$tierCounts = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($cellsWithDistances as $item) {
    $tierCounts[$item['distance']]++;
}

echo "Delivery pricing tiers (in BDT):\n\n";

$pricingTiers = [
    0 => ['fee' => 0, 'label' => 'Same cell'],
    1 => ['fee' => 30, 'label' => 'Immediate neighbors'],
    2 => ['fee' => 50, 'label' => 'Walking distance'],
    3 => ['fee' => 70, 'label' => 'Short ride'],
    4 => ['fee' => 100, 'label' => 'Medium distance'],
    5 => ['fee' => 130, 'label' => 'Extended delivery'],
];

foreach ($pricingTiers as $distance => $tier) {
    echo sprintf(
        "  Distance %d (%s):\n    Cells: %d, Fee: ৳%d\n\n",
        $distance,
        $tier['label'],
        $tierCounts[$distance],
        $tier['fee']
    );
}

// Calculate fee for a specific delivery location (Mohammadpur)
$deliveryLat = 23.7662;
$deliveryLng = 90.3588;
$deliveryCell = $h3->latLngToCell($deliveryLat, $deliveryLng, 9);
$distance = $h3->gridDistance($storeCell, $deliveryCell);

echo "Sample delivery calculation (to Mohammadpur):\n";
echo "  Delivery cell: " . $h3->h3ToString($deliveryCell) . "\n";
echo "  Grid distance: $distance cells\n";
$fee = isset($pricingTiers[$distance]) ? $pricingTiers[$distance]['fee'] : 150;
echo "  Delivery fee: ৳" . $fee . "\n";

// -----------------------------------------------------------------------------
// Example 3: Geofencing with Ring Boundaries
// Real-world scenario: Alert when user enters/exits a geofenced area boundary
// -----------------------------------------------------------------------------

echo "\n\n3. Geofencing with Ring Detection\n";
echo str_repeat("-", 50) . "\n";

$geofenceCenter = $h3->latLngToCell(23.7104, 90.4074, 8); // Motijheel (Bangladesh Bank)
echo "Geofence center: Bangladesh Bank, Motijheel\n";
echo "Cell: " . $h3->h3ToString($geofenceCenter) . "\n\n";

// Create boundary ring at distance k=3
$boundaryRing = $h3->gridRing($geofenceCenter, 3);
$innerArea = $h3->gridDisk($geofenceCenter, 2);

echo "Geofence configuration:\n";
echo "  Inner zone cells: " . count($innerArea) . "\n";
echo "  Boundary ring cells: " . count($boundaryRing) . "\n\n";

// Simulate user movement approaching Motijheel
$userPositions = [
    ['lat' => 23.7150, 'lng' => 90.4100, 'time' => '10:00'],
    ['lat' => 23.7130, 'lng' => 90.4090, 'time' => '10:05'],
    ['lat' => 23.7104, 'lng' => 90.4074, 'time' => '10:10'],
    ['lat' => 23.7080, 'lng' => 90.4050, 'time' => '10:15'],
];

echo "User movement tracking:\n\n";

$previousZone = null;
foreach ($userPositions as $pos) {
    $posCell = $h3->latLngToCell($pos['lat'], $pos['lng'], 8);

    if ($posCell === $geofenceCenter) {
        $zone = 'CENTER';
    } elseif (in_array($posCell, $innerArea)) {
        $zone = 'INNER';
    } elseif (in_array($posCell, $boundaryRing)) {
        $zone = 'BOUNDARY';
    } else {
        $zone = 'OUTSIDE';
    }

    $alert = '';
    if ($previousZone !== null && $previousZone !== $zone) {
        if ($zone === 'BOUNDARY' && $previousZone === 'OUTSIDE') {
            $alert = ' [ALERT: Approaching geofence]';
        } elseif ($zone === 'INNER' || $zone === 'CENTER') {
            $alert = ' [ALERT: Entered geofence]';
        } elseif ($zone === 'OUTSIDE') {
            $alert = ' [ALERT: Left geofence]';
        }
    }

    echo sprintf("  %s: %s%s\n", $pos['time'], $zone, $alert);
    $previousZone = $zone;
}

// -----------------------------------------------------------------------------
// Example 4: Route Planning Between Two Points
// Real-world scenario: Visualize delivery route on hexagonal grid
// -----------------------------------------------------------------------------

echo "\n\n4. Delivery Route Visualization\n";
echo str_repeat("-", 50) . "\n";

// Route from warehouse (Tejgaon) to customer (Banani)
$warehouseLat = 23.7590;
$warehouseLng = 90.3926;
$customerLat = 23.7937;
$customerLng = 90.4066;
$resolution = 7;

$startCell = $h3->latLngToCell($warehouseLat, $warehouseLng, $resolution);
$endCell = $h3->latLngToCell($customerLat, $customerLng, $resolution);

echo "Route planning:\n";
echo "  Start (Tejgaon Warehouse): " . $h3->h3ToString($startCell) . "\n";
echo "  End (Banani Customer): " . $h3->h3ToString($endCell) . "\n\n";

try {
    $pathCells = $h3->gridPathCells($startCell, $endCell);

    echo "Route path (" . count($pathCells) . " cells):\n\n";

    foreach ($pathCells as $i => $cell) {
        $center = $h3->cellToLatLng($cell);
        $marker = '';
        if ($i === 0) {
            $marker = ' [START]';
        } elseif ($i === count($pathCells) - 1) {
            $marker = ' [END]';
        }

        echo sprintf(
            "  %2d. %s (%.4f, %.4f)%s\n",
            $i + 1,
            $h3->h3ToString($cell),
            $center['lat'],
            $center['lng'],
            $marker
        );
    }

    // Calculate approximate route distance
    $gridDist = $h3->gridDistance($startCell, $endCell);
    $avgEdgeLength = $h3->getHexagonEdgeLengthAvgM($resolution);
    $approxDistance = $gridDist * $avgEdgeLength;

    echo "\nRoute statistics:\n";
    echo "  Grid distance: $gridDist cells\n";
    echo "  Approximate distance: " . round($approxDistance / 1000, 2) . " km\n";
} catch (\Exception $e) {
    echo "  Could not calculate path: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Example 5: Coverage Analysis for Service Areas
// Real-world scenario: Analyze coverage overlap between service centers
// -----------------------------------------------------------------------------

echo "\n\n5. Service Center Coverage Analysis\n";
echo str_repeat("-", 50) . "\n";

$resolution = 8;

// Two service centers in Dhaka
$center1 = $h3->latLngToCell(23.7925, 90.4078, $resolution); // Gulshan
$center2 = $h3->latLngToCell(23.7937, 90.4066, $resolution); // Banani

echo "Service Centers:\n";
echo "  Gulshan Center: " . $h3->h3ToString($center1) . "\n";
echo "  Banani Center: " . $h3->h3ToString($center2) . "\n\n";

// Each center covers k=3 rings
$coverage1 = $h3->gridDisk($center1, 3);
$coverage2 = $h3->gridDisk($center2, 3);

// Find overlap
$overlap = array_intersect($coverage1, $coverage2);
$uniqueTo1 = array_diff($coverage1, $coverage2);
$uniqueTo2 = array_diff($coverage2, $coverage1);

echo "Coverage Analysis:\n";
echo "  Gulshan coverage: " . count($coverage1) . " cells\n";
echo "  Banani coverage: " . count($coverage2) . " cells\n";
echo "  Overlap: " . count($overlap) . " cells\n";
echo "  Unique to Gulshan: " . count($uniqueTo1) . " cells\n";
echo "  Unique to Banani: " . count($uniqueTo2) . " cells\n";
echo "  Total unique coverage: " . (count($uniqueTo1) + count($uniqueTo2) + count($overlap)) . " cells\n";

$overlapPercent = (count($overlap) / count($coverage1)) * 100;
echo "\n  Coverage overlap: " . round($overlapPercent, 1) . "%\n";

if ($overlapPercent > 30) {
    echo "  Recommendation: Consider relocating one center to reduce overlap\n";
} else {
    echo "  Recommendation: Good distribution, minimal overlap\n";
}

// -----------------------------------------------------------------------------
// Example 6: Expanding Search with gridDiskDistances
// Real-world scenario: Search for available drivers, expanding outward
// -----------------------------------------------------------------------------

echo "\n\n6. Expanding Driver Search (Pathao/Uber Style)\n";
echo str_repeat("-", 50) . "\n";

$pickupCell = $h3->latLngToCell(23.7925, 90.4078, 9); // Gulshan 2
echo "Pickup location: Gulshan 2 Circle\n";
echo "Cell: " . $h3->h3ToString($pickupCell) . "\n\n";

// Simulated available drivers (cells with drivers)
$driverCells = [];
$neighbors = $h3->gridDisk($pickupCell, 5);
// Place some drivers randomly
$driverCells[$neighbors[5]] = 'Rahim (Bike)';
$driverCells[$neighbors[15]] = 'Karim (Car)';
$driverCells[$neighbors[25]] = 'Jabbar (CNG)';

echo "Searching for nearest available driver:\n\n";

// Search expanding outward
$found = false;
for ($k = 0; $k <= 5 && !$found; $k++) {
    echo "  Searching at distance $k...\n";

    $ring = ($k === 0) ? [$pickupCell] : $h3->gridRing($pickupCell, $k);

    foreach ($ring as $cell) {
        if (isset($driverCells[$cell])) {
            $center = $h3->cellToLatLng($cell);
            echo "\n  FOUND: {$driverCells[$cell]}\n";
            echo "  Location: " . $h3->h3ToString($cell) . "\n";
            echo "  Grid distance: $k cells\n";
            echo "  Approx distance: " . ($k * 174) . "m\n";
            $found = true;
            break;
        }
    }
}

if (!$found) {
    echo "\n  No drivers available within search radius\n";
}

echo "\n=== End of Traversal Examples ===\n";
