<?php

/**
 * H3 Utility Functions - Real World Examples (Bangladesh/Dhaka)
 *
 * This file demonstrates utility functions:
 * - degsToRads / radsToDegs: Angle unit conversion
 * - getNumCells: Count cells at a resolution
 * - getRes0Cells: Get all base resolution cells
 * - getPentagons: Get pentagon cells at a resolution
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

echo "=== H3 Utility Functions Examples (Dhaka, Bangladesh) ===\n\n";

// -----------------------------------------------------------------------------
// Example 1: Coordinate Conversion for GPS Integration
// Real-world scenario: Convert GPS coordinates between different formats
// -----------------------------------------------------------------------------

echo "1. GPS Coordinate Format Conversion\n";
echo str_repeat("-", 50) . "\n";

// Famous landmarks in Bangladesh
$locations = [
    ['name' => 'National Parliament House', 'lat' => 23.7626, 'lng' => 90.3780],
    ['name' => 'Shaheed Minar', 'lat' => 23.7268, 'lng' => 90.3965],
    ['name' => 'Ahsan Manzil', 'lat' => 23.7087, 'lng' => 90.4063],
    ['name' => 'Lalbagh Fort', 'lat' => 23.7188, 'lng' => 90.3887],
];

echo "Coordinate conversions:\n\n";

foreach ($locations as $loc) {
    $latRads = $h3->degsToRads($loc['lat']);
    $lngRads = $h3->degsToRads($loc['lng']);

    // Verify round-trip conversion
    $latDegs = $h3->radsToDegs($latRads);
    $lngDegs = $h3->radsToDegs($lngRads);

    echo sprintf("  %s:\n", $loc['name']);
    echo sprintf("    Degrees: (%.4f°, %.4f°)\n", $loc['lat'], $loc['lng']);
    echo sprintf("    Radians: (%.6f, %.6f)\n", $latRads, $lngRads);
    echo sprintf("    Round-trip: %s\n\n",
        abs($latDegs - $loc['lat']) < 0.0001 ? 'PASS' : 'FAIL'
    );
}

// -----------------------------------------------------------------------------
// Example 2: Understanding H3 Global Scale
// Real-world scenario: Capacity planning for a global delivery service
// -----------------------------------------------------------------------------

echo "\n2. H3 Global Capacity Analysis\n";
echo str_repeat("-", 50) . "\n";

echo "Total H3 cells available at each resolution:\n\n";

$resolutionsToShow = [0, 4, 7, 9, 11, 13, 15];

foreach ($resolutionsToShow as $res) {
    $numCells = $h3->getNumCells($res);
    $avgArea = $h3->getHexagonAreaAvgKm2($res);

    // Format large numbers
    if ($numCells > 1e12) {
        $numStr = sprintf("%.1f trillion", $numCells / 1e12);
    } elseif ($numCells > 1e9) {
        $numStr = sprintf("%.1f billion", $numCells / 1e9);
    } elseif ($numCells > 1e6) {
        $numStr = sprintf("%.1f million", $numCells / 1e6);
    } else {
        $numStr = number_format($numCells);
    }

    // Format area
    if ($avgArea >= 1) {
        $areaStr = sprintf("%.1f km²", $avgArea);
    } else {
        $areaStr = sprintf("%.1f m²", $avgArea * 1e6);
    }

    echo sprintf("  Resolution %2d: %s cells (avg %s each)\n", $res, $numStr, $areaStr);
}

// Calculate cells needed for Bangladesh
echo "\n\nBangladesh coverage estimate:\n";
$bangladeshAreaKm2 = 147570; // Approximate area

foreach ([7, 9, 11] as $res) {
    $avgArea = $h3->getHexagonAreaAvgKm2($res);
    $estimatedCells = ceil($bangladeshAreaKm2 / $avgArea);
    echo sprintf("  Resolution %d: ~%s cells needed\n", $res, number_format($estimatedCells));
}

// -----------------------------------------------------------------------------
// Example 3: Base Cells (Resolution 0) Analysis
// Real-world scenario: Understanding H3's fundamental structure
// -----------------------------------------------------------------------------

echo "\n\n3. Base Cell (Resolution 0) Structure\n";
echo str_repeat("-", 50) . "\n";

$baseCells = $h3->getRes0Cells();

echo "H3 base cells (resolution 0):\n";
echo "  Total base cells: " . count($baseCells) . "\n\n";

// Find which base cell covers Bangladesh
$dhakaCellRes0 = $h3->cellToParent($h3->latLngToCell(23.8103, 90.4125, 5), 0);
$chattogramCellRes0 = $h3->cellToParent($h3->latLngToCell(22.3569, 91.7832, 5), 0);

echo "Bangladesh's base cells:\n";
echo "  Dhaka region: " . $h3->h3ToString($dhakaCellRes0) . "\n";
echo "  Chattogram region: " . $h3->h3ToString($chattogramCellRes0) . "\n";

$sameBaseCell = ($dhakaCellRes0 === $chattogramCellRes0);
echo "  Same base cell: " . ($sameBaseCell ? 'YES' : 'NO') . "\n\n";

// Show some base cell statistics
echo "Base cell statistics:\n";
$pentagonCount = 0;
$hexagonCount = 0;

foreach ($baseCells as $cell) {
    if ($h3->isPentagon($cell)) {
        $pentagonCount++;
    } else {
        $hexagonCount++;
    }
}

echo "  Pentagons: $pentagonCount\n";
echo "  Hexagons: $hexagonCount\n";

// Get area of a base cell
$sampleBaseCell = $baseCells[0];
$baseArea = $h3->cellAreaKm2($sampleBaseCell);
echo sprintf("  Average base cell area: ~%.0f km²\n", $baseArea);
echo sprintf("  Earth coverage per cell: ~%.2f%%\n", ($baseArea / 510100000) * 100);

// -----------------------------------------------------------------------------
// Example 4: Pentagon Cells - Special Handling
// Real-world scenario: Identify pentagon cells for algorithm optimization
// -----------------------------------------------------------------------------

echo "\n\n4. Pentagon Cells at Various Resolutions\n";
echo str_repeat("-", 50) . "\n";

echo "Pentagon distribution (12 pentagons at each resolution):\n\n";

foreach ([0, 4, 8] as $res) {
    $pentagons = $h3->getPentagons($res);
    echo "Resolution $res:\n";

    // Check if any pentagon is near Bangladesh
    $nearBangladesh = false;
    foreach ($pentagons as $pentagon) {
        $center = $h3->cellToLatLng($pentagon);
        // Bangladesh roughly between lat 20-27, lng 88-93
        if ($center['lat'] >= 15 && $center['lat'] <= 30 &&
            $center['lng'] >= 85 && $center['lng'] <= 95) {
            $nearBangladesh = true;
            echo sprintf("  Pentagon near Bangladesh: (%.2f, %.2f)\n",
                $center['lat'], $center['lng']);
        }
    }

    if (!$nearBangladesh) {
        echo "  No pentagons near Bangladesh\n";
    }

    // Show closest pentagon
    $dhakaLat = 23.8103;
    $dhakaLng = 90.4125;
    $minDist = PHP_FLOAT_MAX;
    $closestPentagon = null;

    foreach ($pentagons as $pentagon) {
        $center = $h3->cellToLatLng($pentagon);
        $dist = $h3->greatCircleDistanceKm($dhakaLat, $dhakaLng, $center['lat'], $center['lng']);
        if ($dist < $minDist) {
            $minDist = $dist;
            $closestPentagon = $pentagon;
        }
    }

    echo sprintf("  Closest pentagon to Dhaka: %.0f km away\n\n", $minDist);
}

// -----------------------------------------------------------------------------
// Example 5: Angle Calculations for Bearing/Direction
// Real-world scenario: Calculate delivery direction from restaurant to customer
// -----------------------------------------------------------------------------

echo "\n5. Bearing Calculations for Delivery Navigation\n";
echo str_repeat("-", 50) . "\n";

// Calculate bearing between two points
function calculateBearing(H3 $h3, float $lat1, float $lng1, float $lat2, float $lng2): float {
    $lat1Rad = $h3->degsToRads($lat1);
    $lat2Rad = $h3->degsToRads($lat2);
    $dLngRad = $h3->degsToRads($lng2 - $lng1);

    $x = sin($dLngRad) * cos($lat2Rad);
    $y = cos($lat1Rad) * sin($lat2Rad) - sin($lat1Rad) * cos($lat2Rad) * cos($dLngRad);

    $bearingRad = atan2($x, $y);
    $bearingDeg = $h3->radsToDegs($bearingRad);

    return fmod($bearingDeg + 360, 360);
}

function getCardinalDirection(float $bearing): string {
    $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
    $index = round($bearing / 45) % 8;
    return $directions[$index];
}

$deliveries = [
    ['from' => 'Gulshan', 'fromLat' => 23.7925, 'fromLng' => 90.4078,
     'to' => 'Banani', 'toLat' => 23.7937, 'toLng' => 90.4066],
    ['from' => 'Dhanmondi', 'fromLat' => 23.7461, 'fromLng' => 90.3742,
     'to' => 'Mohammadpur', 'toLat' => 23.7662, 'toLng' => 90.3588],
    ['from' => 'Mirpur', 'fromLat' => 23.8223, 'fromLng' => 90.3654,
     'to' => 'Uttara', 'toLat' => 23.8759, 'toLng' => 90.3795],
];

echo "Delivery route bearings:\n\n";

foreach ($deliveries as $d) {
    $bearing = calculateBearing($h3, $d['fromLat'], $d['fromLng'], $d['toLat'], $d['toLng']);
    $direction = getCardinalDirection($bearing);
    $distance = $h3->greatCircleDistanceKm($d['fromLat'], $d['fromLng'], $d['toLat'], $d['toLng']);

    echo sprintf("  %s → %s:\n", $d['from'], $d['to']);
    echo sprintf("    Bearing: %.1f°\n", $bearing);
    echo sprintf("    Direction: %s\n", $direction);
    echo sprintf("    Distance: %.2f km\n\n", $distance);
}

// -----------------------------------------------------------------------------
// Example 6: System Capacity Planning
// Real-world scenario: Plan database storage for location data
// -----------------------------------------------------------------------------

echo "\n6. System Capacity Planning\n";
echo str_repeat("-", 50) . "\n";

echo "Storage requirements for Bangladesh location index:\n\n";

$bangladeshAreaKm2 = 147570;
$bytesPerCell = 8; // H3 index is 64-bit integer

foreach ([7, 8, 9, 10, 11] as $res) {
    $avgAreaKm2 = $h3->getHexagonAreaAvgKm2($res);
    $cellsNeeded = ceil($bangladeshAreaKm2 / $avgAreaKm2);

    $storageBytes = $cellsNeeded * $bytesPerCell;

    if ($storageBytes >= 1e9) {
        $storageStr = sprintf("%.2f GB", $storageBytes / 1e9);
    } elseif ($storageBytes >= 1e6) {
        $storageStr = sprintf("%.2f MB", $storageBytes / 1e6);
    } else {
        $storageStr = sprintf("%.2f KB", $storageBytes / 1e3);
    }

    echo sprintf("  Resolution %d:\n", $res);
    echo sprintf("    Cells: %s\n", number_format($cellsNeeded));
    echo sprintf("    Storage: %s\n", $storageStr);

    // Estimate cell area
    if ($avgAreaKm2 >= 1) {
        echo sprintf("    Cell size: ~%.2f km²\n\n", $avgAreaKm2);
    } else {
        echo sprintf("    Cell size: ~%.0f m²\n\n", $avgAreaKm2 * 1e6);
    }
}

echo "\nRecommendation:\n";
echo "  - Resolution 9: Best for delivery zone indexing (neighborhood level)\n";
echo "  - Resolution 10: Good for vehicle tracking (block level)\n";
echo "  - Resolution 11: Precise for building-level indexing\n";

echo "\n=== End of Utility Examples ===\n";
