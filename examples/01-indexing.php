<?php

/**
 * H3 Indexing Functions - Real World Examples (Bangladesh/Dhaka)
 *
 * This file demonstrates the core indexing functions of H3:
 * - latLngToCell: Convert coordinates to H3 cell
 * - cellToLatLng: Get center point of a cell
 * - cellToBoundary: Get the hexagon boundary vertices
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

echo "=== H3 Indexing Functions Examples (Dhaka, Bangladesh) ===\n\n";

// -----------------------------------------------------------------------------
// Example 1: Restaurant Location Indexing
// Real-world scenario: A food delivery app needs to index restaurant locations
// -----------------------------------------------------------------------------

echo "1. Restaurant Location Indexing\n";
echo str_repeat("-", 50) . "\n";

// Real restaurant locations in Dhaka
$restaurants = [
    ['name' => 'Star Kabab, Gulshan', 'lat' => 23.7925, 'lng' => 90.4078],        // Gulshan 2
    ['name' => 'Nando\'s, Banani', 'lat' => 23.7937, 'lng' => 90.4066],           // Banani
    ['name' => 'Pizza Hut, Dhanmondi', 'lat' => 23.7461, 'lng' => 90.3742],       // Dhanmondi
    ['name' => 'Madchef, Uttara', 'lat' => 23.8759, 'lng' => 90.3795],            // Uttara
];

// Using resolution 9 (~174m edge length) - good for neighborhood-level grouping
$resolution = 9;

echo "Indexing restaurants at resolution $resolution:\n\n";

foreach ($restaurants as $restaurant) {
    $cell = $h3->latLngToCell($restaurant['lat'], $restaurant['lng'], $resolution);
    $cellHex = $h3->h3ToString($cell);

    echo sprintf(
        "  %s\n    Coordinates: (%.4f, %.4f)\n    H3 Cell: %s\n\n",
        $restaurant['name'],
        $restaurant['lat'],
        $restaurant['lng'],
        $cellHex
    );
}

// -----------------------------------------------------------------------------
// Example 2: Finding Cell Center Points for Map Markers
// Real-world scenario: Display aggregated data at cell centers on a map
// -----------------------------------------------------------------------------

echo "\n2. Cell Center Points for Map Aggregation\n";
echo str_repeat("-", 50) . "\n";

// Simulate multiple delivery orders in Gulshan area
$deliveryOrders = [
    ['lat' => 23.7925, 'lng' => 90.4078],  // Gulshan 2 Circle
    ['lat' => 23.7930, 'lng' => 90.4080],
    ['lat' => 23.7920, 'lng' => 90.4075],
    ['lat' => 23.7928, 'lng' => 90.4082],
];

// Group orders by H3 cell and count
$ordersByCell = [];
foreach ($deliveryOrders as $order) {
    $cell = $h3->latLngToCell($order['lat'], $order['lng'], 10);
    if (!isset($ordersByCell[$cell])) {
        $ordersByCell[$cell] = 0;
    }
    $ordersByCell[$cell]++;
}

echo "Aggregated orders by H3 cell (resolution 10):\n\n";

foreach ($ordersByCell as $cell => $count) {
    $center = $h3->cellToLatLng($cell);
    echo sprintf(
        "  Cell: %s\n  Center: (%.6f, %.6f)\n  Order Count: %d\n\n",
        $h3->h3ToString($cell),
        $center['lat'],
        $center['lng'],
        $count
    );
}

// -----------------------------------------------------------------------------
// Example 3: Drawing Hexagon Boundaries on a Map
// Real-world scenario: Visualize coverage areas or delivery zones
// -----------------------------------------------------------------------------

echo "\n3. Hexagon Boundary for Zone Visualization\n";
echo str_repeat("-", 50) . "\n";

// Get boundary for a delivery zone in Dhanmondi
$zoneLat = 23.7461;  // Dhanmondi Lake area
$zoneLng = 90.3742;
$zoneRes = 7; // Larger hexagons for zone display (~1.4km edge)

$zoneCell = $h3->latLngToCell($zoneLat, $zoneLng, $zoneRes);
$boundary = $h3->cellToBoundary($zoneCell);

echo "Delivery Zone Boundary (Resolution $zoneRes):\n";
echo "Cell: " . $h3->h3ToString($zoneCell) . "\n\n";

echo "GeoJSON Polygon coordinates:\n";
echo "[\n";
foreach ($boundary as $i => $vertex) {
    $comma = ($i < count($boundary) - 1) ? ',' : '';
    echo sprintf("  [%.6f, %.6f]%s\n", $vertex['lng'], $vertex['lat'], $comma);
}
// Close the polygon
echo sprintf("  [%.6f, %.6f]\n", $boundary[0]['lng'], $boundary[0]['lat']);
echo "]\n";

// -----------------------------------------------------------------------------
// Example 4: Multi-Resolution Indexing for Different Use Cases
// Real-world scenario: Store locations at different precision levels
// -----------------------------------------------------------------------------

echo "\n4. Multi-Resolution Location Indexing\n";
echo str_repeat("-", 50) . "\n";

$location = ['lat' => 23.7104, 'lng' => 90.4074]; // Motijheel (Bangladesh Bank area)

echo "Motijheel (Bangladesh Bank) indexed at different resolutions:\n\n";

$resolutions = [
    4 => 'Region (~21km edge)',
    7 => 'District (~1.4km edge)',
    9 => 'Neighborhood (~174m edge)',
    11 => 'Block (~24m edge)',
    13 => 'Building (~3m edge)',
];

foreach ($resolutions as $res => $description) {
    $cell = $h3->latLngToCell($location['lat'], $location['lng'], $res);
    $center = $h3->cellToLatLng($cell);

    echo sprintf(
        "  Resolution %2d - %s\n    Cell: %s\n    Center offset: %.6f°\n\n",
        $res,
        $description,
        $h3->h3ToString($cell),
        abs($location['lat'] - $center['lat']) + abs($location['lng'] - $center['lng'])
    );
}

// -----------------------------------------------------------------------------
// Example 5: Batch Location Processing
// Real-world scenario: Process GPS traces from vehicles for fleet management
// -----------------------------------------------------------------------------

echo "\n5. GPS Trace Processing for Fleet Management\n";
echo str_repeat("-", 50) . "\n";

// Simulated GPS trace from a delivery vehicle in Dhaka (Gulshan to Banani route)
$gpsTrace = [
    ['lat' => 23.7925, 'lng' => 90.4078, 'timestamp' => '2024-01-15 10:00:00'],  // Gulshan 2
    ['lat' => 23.7935, 'lng' => 90.4070, 'timestamp' => '2024-01-15 10:01:00'],
    ['lat' => 23.7945, 'lng' => 90.4065, 'timestamp' => '2024-01-15 10:02:00'],
    ['lat' => 23.7950, 'lng' => 90.4060, 'timestamp' => '2024-01-15 10:03:00'],
    ['lat' => 23.7960, 'lng' => 90.4055, 'timestamp' => '2024-01-15 10:04:00'],  // Towards Banani
];

echo "Vehicle GPS trace converted to H3 cells:\n\n";

$visitedCells = [];
$resolution = 10;

foreach ($gpsTrace as $point) {
    $cell = $h3->latLngToCell($point['lat'], $point['lng'], $resolution);
    $cellHex = $h3->h3ToString($cell);

    $isNewCell = !in_array($cell, $visitedCells);
    if ($isNewCell) {
        $visitedCells[] = $cell;
    }

    echo sprintf(
        "  %s: Cell %s %s\n",
        $point['timestamp'],
        $cellHex,
        $isNewCell ? '(NEW)' : '(same)'
    );
}

echo sprintf("\nUnique cells visited: %d\n", count($visitedCells));

echo "\n=== End of Indexing Examples ===\n";
