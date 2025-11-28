<?php

/**
 * H3 Local Coordinate Functions - Real World Examples (Bangladesh/Dhaka)
 *
 * This file demonstrates local IJ coordinate functions:
 * - cellToLocalIj: Convert cell to local IJ coordinates
 * - localIjToCell: Convert IJ coordinates back to cell
 *
 * Local IJ coordinates provide a flat 2D coordinate system relative
 * to an origin cell, useful for local spatial operations.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

echo "=== H3 Local Coordinate Functions Examples (Dhaka, Bangladesh) ===\n\n";

// -----------------------------------------------------------------------------
// Example 1: Grid-Based Game Board
// Real-world scenario: Create a hexagonal game board with local coordinates
// -----------------------------------------------------------------------------

echo "1. Hexagonal Game Board (Carrom/Ludo Style)\n";
echo str_repeat("-", 50) . "\n";

$resolution = 9;
$origin = $h3->latLngToCell(23.8103, 90.4125, $resolution); // Dhaka center

echo "Game board origin: " . $h3->h3ToString($origin) . "\n\n";

// Create a 5x5 game board using local coordinates
$boardSize = 2;
$board = [];

echo "Creating game board with local IJ coordinates:\n\n";

for ($i = -$boardSize; $i <= $boardSize; $i++) {
    for ($j = -$boardSize; $j <= $boardSize; $j++) {
        try {
            $cell = $h3->localIjToCell($origin, $i, $j);
            $board["$i,$j"] = $cell;
        } catch (\Exception $e) {
            // Some IJ coordinates may not map to valid cells
            continue;
        }
    }
}

echo "Board layout:\n\n";

for ($i = -$boardSize; $i <= $boardSize; $i++) {
    echo "  ";
    for ($j = -$boardSize; $j <= $boardSize; $j++) {
        if (isset($board["$i,$j"])) {
            // Mark center differently
            if ($i === 0 && $j === 0) {
                echo "[O] ";
            } else {
                echo "[ ] ";
            }
        } else {
            echo "    ";
        }
    }
    echo "\n";
}

echo "\nBoard statistics:\n";
echo "  Total tiles: " . count($board) . "\n";
echo "  Origin tile: (0,0)\n";

// -----------------------------------------------------------------------------
// Example 2: Warehouse Grid Layout
// Real-world scenario: Map warehouse sections to hexagonal grid
// -----------------------------------------------------------------------------

echo "\n\n2. Warehouse Grid Management\n";
echo str_repeat("-", 50) . "\n";

$warehouseCenter = $h3->latLngToCell(23.7590, 90.3926, 10); // Tejgaon Industrial Area
echo "Warehouse center (Tejgaon): " . $h3->h3ToString($warehouseCenter) . "\n\n";

// Define warehouse sections using local coordinates
$sections = [
    'receiving' => ['i' => -2, 'j' => 0],
    'storage_a' => ['i' => -1, 'j' => -1],
    'storage_b' => ['i' => -1, 'j' => 1],
    'picking' => ['i' => 0, 'j' => 0],
    'packing' => ['i' => 1, 'j' => 0],
    'shipping' => ['i' => 2, 'j' => 0],
];

echo "Warehouse sections:\n\n";

$sectionCells = [];
foreach ($sections as $name => $coords) {
    try {
        $cell = $h3->localIjToCell($warehouseCenter, $coords['i'], $coords['j']);
        $sectionCells[$name] = $cell;
        $center = $h3->cellToLatLng($cell);

        echo sprintf("  %s:\n", ucfirst(str_replace('_', ' ', $name)));
        echo sprintf("    Local coords: (%d, %d)\n", $coords['i'], $coords['j']);
        echo sprintf("    Cell: %s\n", $h3->h3ToString($cell));
        echo sprintf("    Center: (%.6f, %.6f)\n\n", $center['lat'], $center['lng']);
    } catch (\Exception $e) {
        echo sprintf("  %s: Could not create section\n\n", $name);
    }
}

// Calculate distances between sections
if (isset($sectionCells['receiving']) && isset($sectionCells['shipping'])) {
    $receivingCenter = $h3->cellToLatLng($sectionCells['receiving']);
    $shippingCenter = $h3->cellToLatLng($sectionCells['shipping']);

    $distance = $h3->greatCircleDistanceM(
        $receivingCenter['lat'], $receivingCenter['lng'],
        $shippingCenter['lat'], $shippingCenter['lng']
    );

    echo "Receiving to Shipping distance: " . round($distance) . " meters\n";
}

// -----------------------------------------------------------------------------
// Example 3: Neighborhood Mapping for Delivery
// Real-world scenario: Create local delivery zones from a hub
// -----------------------------------------------------------------------------

echo "\n\n3. Delivery Zone Grid\n";
echo str_repeat("-", 50) . "\n";

$hubLocation = $h3->latLngToCell(23.7925, 90.4078, 8); // Gulshan hub
echo "Delivery hub (Gulshan): " . $h3->h3ToString($hubLocation) . "\n\n";

// Create delivery zones in cardinal directions
$deliveryZones = [
    'north' => ['i' => 1, 'j' => 0],
    'northeast' => ['i' => 1, 'j' => 1],
    'southeast' => ['i' => 0, 'j' => 1],
    'south' => ['i' => -1, 'j' => 0],
    'southwest' => ['i' => -1, 'j' => -1],
    'northwest' => ['i' => 0, 'j' => -1],
];

echo "Delivery zones around hub:\n\n";

foreach ($deliveryZones as $direction => $coords) {
    try {
        $zoneCell = $h3->localIjToCell($hubLocation, $coords['i'], $coords['j']);
        $zoneCenter = $h3->cellToLatLng($zoneCell);

        // Verify we can convert back
        $backToIj = $h3->cellToLocalIj($hubLocation, $zoneCell);

        echo sprintf("  %s zone:\n", ucfirst($direction));
        echo sprintf("    Cell: %s\n", $h3->h3ToString($zoneCell));
        echo sprintf("    IJ: (%d, %d)\n", $coords['i'], $coords['j']);
        echo sprintf("    Verified: %s\n\n",
            ($backToIj['i'] === $coords['i'] && $backToIj['j'] === $coords['j']) ? 'YES' : 'NO'
        );
    } catch (\Exception $e) {
        echo sprintf("  %s zone: Error - %s\n\n", $direction, $e->getMessage());
    }
}

// -----------------------------------------------------------------------------
// Example 4: Grid Distance Using Local Coordinates
// Real-world scenario: Calculate Manhattan-style grid distance
// -----------------------------------------------------------------------------

echo "\n4. Grid Distance Calculations\n";
echo str_repeat("-", 50) . "\n";

$origin = $h3->latLngToCell(23.7461, 90.3742, 9); // Dhanmondi origin
$destination = $h3->latLngToCell(23.7590, 90.3926, 9); // Tejgaon

echo "Route analysis:\n";
echo "  Origin (Dhanmondi): " . $h3->h3ToString($origin) . "\n";
echo "  Destination (Tejgaon): " . $h3->h3ToString($destination) . "\n\n";

try {
    // Get local coordinates of destination relative to origin
    $destIj = $h3->cellToLocalIj($origin, $destination);

    echo "Local coordinates (relative to Dhanmondi):\n";
    echo "  Destination IJ: ({$destIj['i']}, {$destIj['j']})\n";

    // Manhattan distance in grid coordinates
    $gridManhattan = abs($destIj['i']) + abs($destIj['j']);
    echo "  Manhattan distance: $gridManhattan cells\n";

    // H3 grid distance
    $h3GridDist = $h3->gridDistance($origin, $destination);
    echo "  H3 grid distance: $h3GridDist cells\n";

    // Actual distance
    $originCenter = $h3->cellToLatLng($origin);
    $destCenter = $h3->cellToLatLng($destination);
    $actualDist = $h3->greatCircleDistanceKm(
        $originCenter['lat'], $originCenter['lng'],
        $destCenter['lat'], $destCenter['lng']
    );
    echo sprintf("  Actual distance: %.2f km\n", $actualDist);
} catch (\Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Example 5: Scan Pattern for Search Operations
// Real-world scenario: Systematic area search pattern
// -----------------------------------------------------------------------------

echo "\n\n5. Area Search Pattern\n";
echo str_repeat("-", 50) . "\n";

$searchCenter = $h3->latLngToCell(23.8103, 90.4125, 9); // Search center
echo "Search center (Dhaka): " . $h3->h3ToString($searchCenter) . "\n\n";

// Create a spiral search pattern using local coordinates
$spiralPattern = [
    [0, 0],   // Center
    [0, 1],   // Ring 1
    [1, 0],
    [1, -1],
    [0, -1],
    [-1, 0],
    [-1, 1],
    [1, 1],   // Ring 2
    [1, 2],
    [2, 1],
    [2, 0],
    [2, -1],
    [1, -2],
];

echo "Spiral search order:\n\n";

$step = 0;
$searchArea = 0;

foreach ($spiralPattern as $coords) {
    try {
        $cell = $h3->localIjToCell($searchCenter, $coords[0], $coords[1]);
        $step++;

        if ($step <= 7) { // Show first ring details
            echo sprintf(
                "  Step %2d: IJ(%2d,%2d) -> %s\n",
                $step,
                $coords[0],
                $coords[1],
                $h3->h3ToString($cell)
            );
        }

        $searchArea += $h3->cellAreaM2($cell);
    } catch (\Exception $e) {
        continue;
    }
}

echo "  ... (pattern continues)\n\n";

echo "Search statistics:\n";
echo "  Total steps: $step\n";
echo sprintf("  Area covered: %.0f m² (%.4f km²)\n", $searchArea, $searchArea / 1000000);

// -----------------------------------------------------------------------------
// Example 6: Round-Trip Coordinate Verification
// Real-world scenario: Ensure coordinate system integrity
// -----------------------------------------------------------------------------

echo "\n\n6. Coordinate System Verification\n";
echo str_repeat("-", 50) . "\n";

$testOrigin = $h3->latLngToCell(23.7925, 90.4078, 9); // Gulshan
$testCells = $h3->gridDisk($testOrigin, 3);

echo "Testing round-trip conversion (Cell -> IJ -> Cell):\n\n";

$passed = 0;
$failed = 0;

foreach ($testCells as $cell) {
    try {
        // Convert to local IJ
        $ij = $h3->cellToLocalIj($testOrigin, $cell);

        // Convert back to cell
        $backToCell = $h3->localIjToCell($testOrigin, $ij['i'], $ij['j']);

        if ($cell === $backToCell) {
            $passed++;
        } else {
            $failed++;
        }
    } catch (\Exception $e) {
        $failed++;
    }
}

echo "  Total cells tested: " . count($testCells) . "\n";
echo "  Passed: $passed\n";
echo "  Failed: $failed\n";
echo "  Success rate: " . round(($passed / count($testCells)) * 100, 1) . "%\n";

if ($failed > 0) {
    echo "\nNote: Some conversions may fail near pentagon cells\n";
    echo "or at edges of the local coordinate system.\n";
}

echo "\n=== End of Local Coordinate Examples ===\n";
