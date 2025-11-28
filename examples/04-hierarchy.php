<?php

/**
 * H3 Hierarchy Functions - Real World Examples (Bangladesh/Dhaka)
 *
 * This file demonstrates hierarchical cell functions:
 * - cellToParent: Get parent cell at coarser resolution
 * - cellToChildren: Get all children at finer resolution
 * - cellToCenterChild: Get center child cell
 * - compactCells: Compress cell set to minimal representation
 * - uncompactCells: Expand compressed cells to target resolution
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

echo "=== H3 Hierarchy Functions Examples (Dhaka, Bangladesh) ===\n\n";

// -----------------------------------------------------------------------------
// Example 1: Multi-Level Location Hierarchy
// Real-world scenario: Store user location at multiple resolutions for privacy
// -----------------------------------------------------------------------------

echo "1. Privacy-Aware Location Hierarchy\n";
echo str_repeat("-", 50) . "\n";

// User's exact location - Bashundhara City Shopping Mall
$exactLat = 23.7508;
$exactLng = 90.3921;
$preciseRes = 12; // ~10m precision

$preciseCell = $h3->latLngToCell($exactLat, $exactLng, $preciseRes);

echo "User location: Bashundhara City Shopping Mall\n";
echo "Exact coordinates: ($exactLat, $exactLng)\n\n";

echo "Location hierarchy (for different sharing levels):\n\n";

$privacyLevels = [
    12 => 'Exact location (internal use only)',
    10 => 'Building level (trusted contacts)',
    8 => 'Block level (friends)',
    6 => 'Neighborhood (acquaintances)',
    4 => 'District (public profile)',
];

$currentCell = $preciseCell;
foreach ($privacyLevels as $res => $description) {
    $parentCell = $h3->cellToParent($currentCell, $res);
    $center = $h3->cellToLatLng($parentCell);

    echo sprintf(
        "  Resolution %2d - %s\n    Cell: %s\n    Center: (%.4f, %.4f)\n\n",
        $res,
        $description,
        $h3->h3ToString($parentCell),
        $center['lat'],
        $center['lng']
    );
}

// -----------------------------------------------------------------------------
// Example 2: Drill-Down Analytics
// Real-world scenario: Analytics dashboard with zoom levels
// -----------------------------------------------------------------------------

echo "\n2. Analytics Drill-Down System\n";
echo str_repeat("-", 50) . "\n";

// Start with a regional cell covering Dhaka
$regionCell = $h3->latLngToCell(23.8103, 90.4125, 5);
echo "Regional view (Resolution 5): " . $h3->h3ToString($regionCell) . "\n\n";

// Simulate drill-down through zoom levels
$levels = [
    5 => 'Dhaka Region',
    6 => 'District',
    7 => 'Sub-district',
];

$currentCell = $regionCell;

foreach ($levels as $res => $label) {
    if ($res > 5) {
        $children = $h3->cellToChildren($currentCell, $res);
        $currentCell = $children[0]; // Select first child for demo
    }

    $childCount = 0;
    if ($res < 7) {
        $nextChildren = $h3->cellToChildren($currentCell, $res + 1);
        $childCount = count($nextChildren);
    }

    echo "$label level (Resolution $res):\n";
    echo "  Cell: " . $h3->h3ToString($currentCell) . "\n";
    if ($childCount > 0) {
        echo "  Contains $childCount sub-regions at next level\n";
    }
    echo "\n";
}

// Show full children expansion
echo "Expanding district to neighborhoods:\n";
$districtCell = $h3->cellToParent($h3->latLngToCell(23.8103, 90.4125, 9), 6);
$neighborhoods = $h3->cellToChildren($districtCell, 8);

echo "  District: " . $h3->h3ToString($districtCell) . "\n";
echo "  Contains " . count($neighborhoods) . " neighborhoods at resolution 8\n\n";

echo "  First 5 neighborhoods:\n";
foreach (array_slice($neighborhoods, 0, 5) as $hood) {
    $center = $h3->cellToLatLng($hood);
    echo sprintf("    %s (%.4f, %.4f)\n", $h3->h3ToString($hood), $center['lat'], $center['lng']);
}

// -----------------------------------------------------------------------------
// Example 3: Efficient Data Storage with Compaction
// Real-world scenario: Store coverage areas efficiently
// -----------------------------------------------------------------------------

echo "\n\n3. Efficient Coverage Storage with Compaction\n";
echo str_repeat("-", 50) . "\n";

// Simulate a delivery zone around Gulshan
$zoneCenterLat = 23.7925;
$zoneCenterLng = 90.4078;
$zoneCenter = $h3->latLngToCell($zoneCenterLat, $zoneCenterLng, 9);

// Get all cells in a k=10 radius (large zone)
$zoneCells = $h3->gridDisk($zoneCenter, 10);

echo "Delivery zone coverage (Gulshan area):\n";
echo "  Zone center: " . $h3->h3ToString($zoneCenter) . "\n";
echo "  Original cells (res 9): " . count($zoneCells) . "\n";

// Compact the cells
$compacted = $h3->compactCells($zoneCells);

echo "  Compacted cells: " . count($compacted) . "\n";

$compressionRatio = (1 - count($compacted) / count($zoneCells)) * 100;
echo "  Compression: " . round($compressionRatio, 1) . "% reduction\n\n";

// Show what resolutions are in the compacted set
$resolutionBreakdown = [];
foreach ($compacted as $cell) {
    $res = $h3->getResolution($cell);
    if (!isset($resolutionBreakdown[$res])) {
        $resolutionBreakdown[$res] = 0;
    }
    $resolutionBreakdown[$res]++;
}

echo "Compacted cell resolution breakdown:\n";
ksort($resolutionBreakdown);
foreach ($resolutionBreakdown as $res => $count) {
    echo "  Resolution $res: $count cells\n";
}

// Verify uncompaction restores original
$uncompacted = $h3->uncompactCells($compacted, 9);
echo "\nVerification:\n";
echo "  Uncompacted back to res 9: " . count($uncompacted) . " cells\n";
echo "  Matches original: " . (count($uncompacted) === count($zoneCells) ? 'YES' : 'NO') . "\n";

// -----------------------------------------------------------------------------
// Example 4: Center Child for Consistent Aggregation
// Real-world scenario: Create consistent aggregation points
// -----------------------------------------------------------------------------

echo "\n\n4. Consistent Aggregation Points\n";
echo str_repeat("-", 50) . "\n";

$regionCell = $h3->latLngToCell(23.8103, 90.4125, 6);
echo "Region cell (res 6): " . $h3->h3ToString($regionCell) . "\n\n";

echo "Finding center children at each finer resolution:\n\n";

$currentCell = $regionCell;
for ($res = 7; $res <= 10; $res++) {
    $centerChild = $h3->cellToCenterChild($currentCell, $res);
    $center = $h3->cellToLatLng($centerChild);

    echo sprintf(
        "  Resolution %2d: %s\n    Center: (%.6f, %.6f)\n",
        $res,
        $h3->h3ToString($centerChild),
        $center['lat'],
        $center['lng']
    );

    $currentCell = $centerChild;
}

echo "\nUse case: Place aggregation markers at center children\n";
echo "for consistent visualization across zoom levels.\n";

// -----------------------------------------------------------------------------
// Example 5: Multi-Resolution Data Aggregation
// Real-world scenario: Aggregate sensor data at different granularities
// -----------------------------------------------------------------------------

echo "\n\n5. Multi-Resolution Sensor Data Aggregation\n";
echo str_repeat("-", 50) . "\n";

// Simulate IoT sensor readings (air quality monitors) at resolution 10
echo "Simulating air quality sensors across Dhaka:\n\n";

$baseLat = 23.75;
$baseLng = 90.38;
$sensorData = [];

// Generate random sensor data (AQI readings)
for ($i = 0; $i < 50; $i++) {
    $lat = $baseLat + (mt_rand(0, 500) / 10000);
    $lng = $baseLng + (mt_rand(0, 500) / 10000);
    $cell = $h3->latLngToCell($lat, $lng, 10);
    $aqi = 50 + mt_rand(0, 200); // AQI value

    $sensorData[$cell] = $aqi;
}

echo "Raw sensor data: " . count($sensorData) . " readings at resolution 10\n\n";

// Aggregate to coarser resolutions
foreach ([9, 8, 7] as $targetRes) {
    $aggregated = [];

    foreach ($sensorData as $cell => $aqi) {
        $parentCell = $h3->cellToParent($cell, $targetRes);
        if (!isset($aggregated[$parentCell])) {
            $aggregated[$parentCell] = ['sum' => 0, 'count' => 0];
        }
        $aggregated[$parentCell]['sum'] += $aqi;
        $aggregated[$parentCell]['count']++;
    }

    // Calculate averages
    $avgAqi = [];
    foreach ($aggregated as $cell => $data) {
        $avgAqi[$cell] = $data['sum'] / $data['count'];
    }

    echo "Resolution $targetRes aggregation:\n";
    echo "  Groups: " . count($avgAqi) . "\n";

    if (count($avgAqi) > 0) {
        echo "  Sample averages:\n";
        $i = 0;
        foreach (array_slice($avgAqi, 0, 3, true) as $cell => $avg) {
            $status = $avg < 100 ? 'Good' : ($avg < 150 ? 'Moderate' : 'Unhealthy');
            echo sprintf("    %s: AQI %.0f (%s)\n", $h3->h3ToString($cell), $avg, $status);
        }
    }
    echo "\n";
}

// -----------------------------------------------------------------------------
// Example 6: Hierarchical Geofence System
// Real-world scenario: Multi-level geofencing for fleet management
// -----------------------------------------------------------------------------

echo "\n6. Hierarchical Geofence System\n";
echo str_repeat("-", 50) . "\n";

// Define a multi-level geofence centered on Dhaka
$geofences = [
    'country' => ['res' => 2, 'lat' => 23.8103, 'lng' => 90.4125],
    'region' => ['res' => 4, 'lat' => 23.8103, 'lng' => 90.4125],
    'city' => ['res' => 6, 'lat' => 23.8103, 'lng' => 90.4125],
    'zone' => ['res' => 8, 'lat' => 23.8103, 'lng' => 90.4125],
];

echo "Geofence hierarchy:\n\n";

$geofenceCells = [];
foreach ($geofences as $name => $config) {
    $cell = $h3->latLngToCell($config['lat'], $config['lng'], $config['res']);
    $geofenceCells[$name] = $cell;

    $area = $h3->cellAreaKm2($cell);
    echo sprintf(
        "  %s (res %d): %s\n    Area: %.2f km²\n\n",
        ucfirst($name),
        $config['res'],
        $h3->h3ToString($cell),
        $area
    );
}

// Check if vehicle location is within each geofence level
$vehicleLat = 23.7925;  // Vehicle in Gulshan
$vehicleLng = 90.4078;

echo "Vehicle location check (Gulshan: $vehicleLat, $vehicleLng):\n\n";

foreach ($geofences as $name => $config) {
    $vehicleCell = $h3->latLngToCell($vehicleLat, $vehicleLng, $config['res']);
    $isInside = ($vehicleCell === $geofenceCells[$name]);

    // Also check if it's a child of the geofence
    if (!$isInside && $config['res'] < 10) {
        $vehiclePrecise = $h3->latLngToCell($vehicleLat, $vehicleLng, 10);
        $vehicleParent = $h3->cellToParent($vehiclePrecise, $config['res']);
        $isInside = ($vehicleParent === $geofenceCells[$name]);
    }

    echo sprintf(
        "  %s geofence: %s\n",
        ucfirst($name),
        $isInside ? 'INSIDE' : 'OUTSIDE'
    );
}

echo "\n=== End of Hierarchy Examples ===\n";
