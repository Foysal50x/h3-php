<?php

/**
 * H3 Measurement Functions - Real World Examples (Bangladesh/Dhaka)
 *
 * This file demonstrates measurement functions:
 * - getHexagonAreaAvgKm2 / getHexagonAreaAvgM2: Average hexagon area
 * - cellAreaKm2 / cellAreaM2 / cellAreaRads2: Exact cell area
 * - getHexagonEdgeLengthAvgKm / getHexagonEdgeLengthAvgM: Average edge length
 * - edgeLengthKm / edgeLengthM / edgeLengthRads: Exact edge length
 * - greatCircleDistanceKm / greatCircleDistanceM / greatCircleDistanceRads: Point distance
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

echo "=== H3 Measurement Functions Examples (Dhaka, Bangladesh) ===\n\n";

// -----------------------------------------------------------------------------
// Example 1: Delivery Zone Area Pricing
// Real-world scenario: Calculate delivery fees based on zone area
// -----------------------------------------------------------------------------

echo "1. Delivery Zone Area-Based Pricing\n";
echo str_repeat("-", 50) . "\n";

$zoneCenter = $h3->latLngToCell(23.7925, 90.4078, 8); // Gulshan
$zoneCells = $h3->gridDisk($zoneCenter, 2);

echo "Delivery zone configuration (Gulshan area):\n";
echo "  Center: " . $h3->h3ToString($zoneCenter) . "\n";
echo "  Cells: " . count($zoneCells) . "\n\n";

// Calculate total zone area
$totalAreaKm2 = 0;
$totalAreaM2 = 0;

foreach ($zoneCells as $cell) {
    $totalAreaKm2 += $h3->cellAreaKm2($cell);
    $totalAreaM2 += $h3->cellAreaM2($cell);
}

echo "Zone coverage:\n";
echo sprintf("  Total area: %.2f km²\n", $totalAreaKm2);
echo sprintf("  Total area: %.0f m²\n", $totalAreaM2);
echo sprintf("  Equivalent to: %.1f Bangabandhu National Stadium fields\n\n", $totalAreaM2 / 10800); // ~10800 m²

// Pricing based on area
$pricePerKm2 = 500; // BDT per km²
$zoneFee = $totalAreaKm2 * $pricePerKm2;

echo "Zone pricing:\n";
echo sprintf("  Rate: ৳%.0f per km²\n", $pricePerKm2);
echo sprintf("  Base delivery fee for this zone: ৳%.0f\n", $zoneFee);

// -----------------------------------------------------------------------------
// Example 2: Resolution Selection for Different Use Cases
// Real-world scenario: Choose appropriate resolution based on area requirements
// -----------------------------------------------------------------------------

echo "\n\n2. Resolution Selection Guide\n";
echo str_repeat("-", 50) . "\n";

echo "H3 hexagon sizes at each resolution:\n\n";

echo sprintf("  %-5s %-15s %-15s %-20s\n", "Res", "Avg Area", "Edge Length", "Use Case");
echo str_repeat("-", 60) . "\n";

$useCases = [
    0 => 'Continent-level',
    3 => 'Country-level',
    5 => 'Division-level',
    7 => 'Thana/Upazila',
    9 => 'Neighborhood',
    11 => 'Building block',
    13 => 'Single building',
    15 => 'Room-level',
];

foreach ($useCases as $res => $useCase) {
    $areaKm2 = $h3->getHexagonAreaAvgKm2($res);
    $edgeM = $h3->getHexagonEdgeLengthAvgM($res);

    // Format area appropriately
    if ($areaKm2 >= 1) {
        $areaStr = sprintf("%.2f km²", $areaKm2);
    } else {
        $areaStr = sprintf("%.0f m²", $areaKm2 * 1000000);
    }

    // Format edge length
    if ($edgeM >= 1000) {
        $edgeStr = sprintf("%.1f km", $edgeM / 1000);
    } else {
        $edgeStr = sprintf("%.1f m", $edgeM);
    }

    echo sprintf("  %-5d %-15s %-15s %-20s\n", $res, $areaStr, $edgeStr, $useCase);
}

// -----------------------------------------------------------------------------
// Example 3: Distance Calculation for Delivery Time Estimation
// Real-world scenario: Calculate delivery distances in Dhaka
// -----------------------------------------------------------------------------

echo "\n\n3. Delivery Distance Calculations\n";
echo str_repeat("-", 50) . "\n";

// Common delivery routes in Dhaka
$deliveryRoutes = [
    ['from' => 'Gulshan 2', 'fromLat' => 23.7925, 'fromLng' => 90.4078,
     'to' => 'Banani', 'toLat' => 23.7937, 'toLng' => 90.4066],
    ['from' => 'Dhanmondi', 'fromLat' => 23.7461, 'fromLng' => 90.3742,
     'to' => 'Motijheel', 'toLat' => 23.7104, 'toLng' => 90.4074],
    ['from' => 'Uttara', 'fromLat' => 23.8759, 'fromLng' => 90.3795,
     'to' => 'Mirpur', 'toLat' => 23.8223, 'toLng' => 90.3654],
    ['from' => 'Tejgaon', 'fromLat' => 23.7590, 'fromLng' => 90.3926,
     'to' => 'Bashundhara', 'toLat' => 23.8135, 'toLng' => 90.4250],
];

echo "Common delivery routes:\n\n";

foreach ($deliveryRoutes as $route) {
    $distanceKm = $h3->greatCircleDistanceKm(
        $route['fromLat'], $route['fromLng'],
        $route['toLat'], $route['toLng']
    );

    $distanceM = $h3->greatCircleDistanceM(
        $route['fromLat'], $route['fromLng'],
        $route['toLat'], $route['toLng']
    );

    // Estimate delivery time (assuming 15 km/h average in Dhaka traffic)
    $estimatedMinutes = ($distanceKm / 15) * 60;

    // Calculate delivery fee based on distance
    $baseFee = 30;  // BDT
    $perKmFee = 10; // BDT per km
    $deliveryFee = $baseFee + ($distanceKm * $perKmFee);

    echo sprintf("  %s → %s:\n", $route['from'], $route['to']);
    echo sprintf("    Distance: %.2f km (%.0f m)\n", $distanceKm, $distanceM);
    echo sprintf("    Est. time: %.0f minutes\n", $estimatedMinutes);
    echo sprintf("    Delivery fee: ৳%.0f\n\n", $deliveryFee);
}

// -----------------------------------------------------------------------------
// Example 4: Comparing Cell Areas at Different Locations
// Real-world scenario: Understand area variations across different latitudes
// -----------------------------------------------------------------------------

echo "\n4. Cell Area Variations Across Bangladesh\n";
echo str_repeat("-", 50) . "\n";

// Different cities in Bangladesh (different latitudes)
$locations = [
    ['name' => 'Teknaf (South)', 'lat' => 20.8639, 'lng' => 92.2987],
    ['name' => 'Dhaka (Central)', 'lat' => 23.8103, 'lng' => 90.4125],
    ['name' => 'Rangpur (North)', 'lat' => 25.7439, 'lng' => 89.2752],
    ['name' => 'Tetulia (Far North)', 'lat' => 26.3292, 'lng' => 88.4028],
];

$resolution = 9;

echo "Cell area comparison at resolution $resolution:\n\n";

$referenceArea = null;
foreach ($locations as $loc) {
    $cell = $h3->latLngToCell($loc['lat'], $loc['lng'], $resolution);
    $areaM2 = $h3->cellAreaM2($cell);
    $areaKm2 = $h3->cellAreaKm2($cell);

    if ($referenceArea === null) {
        $referenceArea = $areaM2;
    }

    $variation = (($areaM2 - $referenceArea) / $referenceArea) * 100;

    echo sprintf("  %s (%.2f°N):\n", $loc['name'], $loc['lat']);
    echo sprintf("    Area: %.0f m² (%.4f km²)\n", $areaM2, $areaKm2);
    echo sprintf("    Variation: %+.2f%% from Teknaf\n\n", $variation);
}

echo "Note: H3 cells are slightly smaller near the equator\n";
echo "and slightly larger toward the poles.\n";

// -----------------------------------------------------------------------------
// Example 5: Edge Length Analysis for Route Planning
// Real-world scenario: Calculate actual edge lengths for path distance
// -----------------------------------------------------------------------------

echo "\n\n5. Edge Length Analysis for Pathao/Uber Routes\n";
echo str_repeat("-", 50) . "\n";

$routeStart = $h3->latLngToCell(23.7925, 90.4078, 9); // Gulshan 2
$routeEnd = $h3->latLngToCell(23.7461, 90.3742, 9);   // Dhanmondi

echo "Route analysis (Gulshan to Dhanmondi):\n";
echo "  Start: " . $h3->h3ToString($routeStart) . "\n";
echo "  End: " . $h3->h3ToString($routeEnd) . "\n\n";

try {
    $pathCells = $h3->gridPathCells($routeStart, $routeEnd);
    echo "Path cells: " . count($pathCells) . "\n\n";

    // Calculate actual path distance using edge lengths
    $totalPathDistance = 0;
    $edgeDistances = [];

    for ($i = 0; $i < count($pathCells) - 1; $i++) {
        $cell1Center = $h3->cellToLatLng($pathCells[$i]);
        $cell2Center = $h3->cellToLatLng($pathCells[$i + 1]);

        $edgeDistance = $h3->greatCircleDistanceM(
            $cell1Center['lat'], $cell1Center['lng'],
            $cell2Center['lat'], $cell2Center['lng']
        );

        $edgeDistances[] = $edgeDistance;
        $totalPathDistance += $edgeDistance;
    }

    echo "Edge-by-edge distances:\n";
    foreach ($edgeDistances as $i => $dist) {
        echo sprintf("  Step %d: %.0f m\n", $i + 1, $dist);
    }

    echo sprintf("\nTotal path distance: %.2f km\n", $totalPathDistance / 1000);

    // Compare with direct distance
    $directDistance = $h3->greatCircleDistanceKm(
        23.7925, 90.4078,
        23.7461, 90.3742
    );

    echo sprintf("Direct distance: %.2f km\n", $directDistance);
    echo sprintf("Path overhead: %.1f%%\n", (($totalPathDistance / 1000 / $directDistance) - 1) * 100);
} catch (\Exception $e) {
    echo "Could not calculate path: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Example 6: Coverage Area Calculation for Services
// Real-world scenario: Calculate total coverage for Foodpanda/Pathao zones
// -----------------------------------------------------------------------------

echo "\n\n6. Service Coverage Area Analysis\n";
echo str_repeat("-", 50) . "\n";

// Major service areas in Dhaka
$serviceAreas = [
    ['name' => 'Gulshan Zone', 'lat' => 23.7925, 'lng' => 90.4078, 'radius' => 3],
    ['name' => 'Dhanmondi Zone', 'lat' => 23.7461, 'lng' => 90.3742, 'radius' => 2],
    ['name' => 'Uttara Zone', 'lat' => 23.8759, 'lng' => 90.3795, 'radius' => 4],
    ['name' => 'Mirpur Zone', 'lat' => 23.8223, 'lng' => 90.3654, 'radius' => 3],
];

$resolution = 8;

echo "Service zone coverage analysis:\n\n";

$totalCoverage = 0;
$totalCells = 0;

foreach ($serviceAreas as $area) {
    $centerCell = $h3->latLngToCell($area['lat'], $area['lng'], $resolution);
    $zoneCells = $h3->gridDisk($centerCell, $area['radius']);

    $zoneArea = 0;
    foreach ($zoneCells as $cell) {
        $zoneArea += $h3->cellAreaKm2($cell);
    }

    $totalCoverage += $zoneArea;
    $totalCells += count($zoneCells);

    echo sprintf("  %s:\n", $area['name']);
    echo sprintf("    Cells: %d\n", count($zoneCells));
    echo sprintf("    Coverage: %.2f km²\n", $zoneArea);
    echo sprintf("    Radius (k): %d (~%.1f km)\n\n",
        $area['radius'],
        $area['radius'] * $h3->getHexagonEdgeLengthAvgKm($resolution) * 1.5
    );
}

echo "Total service coverage:\n";
echo sprintf("  Cells: %d\n", $totalCells);
echo sprintf("  Area: %.2f km²\n", $totalCoverage);
echo sprintf("  Dhaka coverage: %.1f%%\n", ($totalCoverage / 306) * 100); // Dhaka ~306 km²

echo "\n=== End of Measurement Examples ===\n";
