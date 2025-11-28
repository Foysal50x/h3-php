<?php

/**
 * H3 Directed Edge Functions - Real World Examples (Bangladesh/Dhaka)
 *
 * This file demonstrates directed edge functions:
 * - areNeighborCells: Check if two cells are adjacent
 * - cellsToDirectedEdge: Create edge between neighbors
 * - isValidDirectedEdge: Validate edge index
 * - getDirectedEdgeOrigin: Get edge origin cell
 * - getDirectedEdgeDestination: Get edge destination cell
 * - directedEdgeToCells: Get both cells of an edge
 * - originToDirectedEdges: Get all edges from a cell
 * - directedEdgeToBoundary: Get edge line coordinates
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

echo "=== H3 Directed Edge Functions Examples (Dhaka, Bangladesh) ===\n\n";

// -----------------------------------------------------------------------------
// Example 1: Traffic Flow Analysis
// Real-world scenario: Track directional traffic between hexagonal zones
// -----------------------------------------------------------------------------

echo "1. Traffic Flow Analysis\n";
echo str_repeat("-", 50) . "\n";

$resolution = 9;

// Define two adjacent traffic zones in Motijheel
$zone1Lat = 23.7104;
$zone1Lng = 90.4074;
$zone1 = $h3->latLngToCell($zone1Lat, $zone1Lng, $resolution);

// Get a neighbor cell
$neighbors = $h3->gridDisk($zone1, 1);
$zone2 = $neighbors[1]; // First neighbor

echo "Traffic zones (Motijheel area):\n";
echo "  Zone 1: " . $h3->h3ToString($zone1) . "\n";
echo "  Zone 2: " . $h3->h3ToString($zone2) . "\n\n";

// Verify they're neighbors
$areNeighbors = $h3->areNeighborCells($zone1, $zone2);
echo "Are neighbors: " . ($areNeighbors ? 'YES' : 'NO') . "\n\n";

if ($areNeighbors) {
    // Create directed edges for traffic flow
    $edgeFromZone1 = $h3->cellsToDirectedEdge($zone1, $zone2);
    $edgeFromZone2 = $h3->cellsToDirectedEdge($zone2, $zone1);

    echo "Directed edges (traffic flow):\n";
    echo "  Zone 1 -> Zone 2: " . $h3->h3ToString($edgeFromZone1) . "\n";
    echo "  Zone 2 -> Zone 1: " . $h3->h3ToString($edgeFromZone2) . "\n\n";

    // Simulate traffic counts (typical Dhaka rush hour)
    $trafficData = [
        $edgeFromZone1 => ['morning' => 2500, 'evening' => 1200],
        $edgeFromZone2 => ['morning' => 800, 'evening' => 2800],
    ];

    echo "Traffic flow analysis:\n";
    echo "  Morning rush (towards offices):\n";
    echo "    Zone 1 -> Zone 2: {$trafficData[$edgeFromZone1]['morning']} vehicles\n";
    echo "    Zone 2 -> Zone 1: {$trafficData[$edgeFromZone2]['morning']} vehicles\n";
    echo "    Net flow: " . ($trafficData[$edgeFromZone1]['morning'] - $trafficData[$edgeFromZone2]['morning']) . " toward Zone 2\n\n";

    echo "  Evening rush (towards homes):\n";
    echo "    Zone 1 -> Zone 2: {$trafficData[$edgeFromZone1]['evening']} vehicles\n";
    echo "    Zone 2 -> Zone 1: {$trafficData[$edgeFromZone2]['evening']} vehicles\n";
    echo "    Net flow: " . ($trafficData[$edgeFromZone2]['evening'] - $trafficData[$edgeFromZone1]['evening']) . " toward Zone 1\n";
}

// -----------------------------------------------------------------------------
// Example 2: Border Analysis for Delivery Zones
// Real-world scenario: Identify and map delivery zone boundaries
// -----------------------------------------------------------------------------

echo "\n\n2. Delivery Zone Border Mapping\n";
echo str_repeat("-", 50) . "\n";

$deliveryZoneCenter = $h3->latLngToCell(23.7925, 90.4078, 8); // Gulshan
echo "Delivery zone center (Gulshan): " . $h3->h3ToString($deliveryZoneCenter) . "\n\n";

// Get all directed edges (borders) from this zone
$allEdges = $h3->originToDirectedEdges($deliveryZoneCenter);

echo "Zone borders (" . count($allEdges) . " edges):\n\n";

foreach ($allEdges as $i => $edge) {
    if (!$h3->isValidDirectedEdge($edge)) {
        continue;
    }

    $destination = $h3->getDirectedEdgeDestination($edge);
    $boundary = $h3->directedEdgeToBoundary($edge);

    echo sprintf("  Edge %d:\n", $i + 1);
    echo "    To: " . $h3->h3ToString($destination) . "\n";
    echo "    Border coordinates:\n";
    foreach ($boundary as $point) {
        echo sprintf("      (%.6f, %.6f)\n", $point['lat'], $point['lng']);
    }
    echo "\n";
}

// -----------------------------------------------------------------------------
// Example 3: Network Connectivity Analysis
// Real-world scenario: Build a graph of cell connectivity
// -----------------------------------------------------------------------------

echo "\n3. Cell Connectivity Network\n";
echo str_repeat("-", 50) . "\n";

$centerCell = $h3->latLngToCell(23.7461, 90.3742, 9); // Dhanmondi
$networkCells = $h3->gridDisk($centerCell, 2);

echo "Building connectivity network (Dhanmondi area):\n";
echo "  Total cells: " . count($networkCells) . "\n\n";

// Build adjacency list
$adjacencyList = [];
$edgeCount = 0;

foreach ($networkCells as $cell) {
    $cellHex = $h3->h3ToString($cell);
    $adjacencyList[$cellHex] = [];

    $edges = $h3->originToDirectedEdges($cell);
    foreach ($edges as $edge) {
        if (!$h3->isValidDirectedEdge($edge)) {
            continue;
        }

        $dest = $h3->getDirectedEdgeDestination($edge);
        // Only include if destination is in our network
        if (in_array($dest, $networkCells)) {
            $adjacencyList[$cellHex][] = $h3->h3ToString($dest);
            $edgeCount++;
        }
    }
}

echo "Network statistics:\n";
echo "  Nodes (cells): " . count($adjacencyList) . "\n";
echo "  Edges (connections): $edgeCount\n";
echo "  Avg connections per cell: " . round($edgeCount / count($adjacencyList), 2) . "\n\n";

// Show sample adjacency
echo "Sample adjacencies (first 3 cells):\n";
$count = 0;
foreach ($adjacencyList as $cell => $neighbors) {
    if ($count >= 3) break;
    echo "  $cell -> [" . implode(', ', array_slice($neighbors, 0, 3)) . "...]\n";
    $count++;
}

// -----------------------------------------------------------------------------
// Example 4: Direction-Based Service Routing
// Real-world scenario: Route deliveries based on exit direction
// -----------------------------------------------------------------------------

echo "\n\n4. Direction-Based Service Routing\n";
echo str_repeat("-", 50) . "\n";

$hubCell = $h3->latLngToCell(23.7590, 90.3926, 8); // Tejgaon hub
echo "Distribution hub (Tejgaon): " . $h3->h3ToString($hubCell) . "\n";

$hubCenter = $h3->cellToLatLng($hubCell);
echo sprintf("Hub center: (%.4f, %.4f)\n\n", $hubCenter['lat'], $hubCenter['lng']);

// Get all exit edges from hub
$exitEdges = $h3->originToDirectedEdges($hubCell);

echo "Exit routes from hub:\n\n";

foreach ($exitEdges as $i => $edge) {
    if (!$h3->isValidDirectedEdge($edge)) {
        continue;
    }

    $destCell = $h3->getDirectedEdgeDestination($edge);
    $destCenter = $h3->cellToLatLng($destCell);

    // Calculate approximate direction
    $latDiff = $destCenter['lat'] - $hubCenter['lat'];
    $lngDiff = $destCenter['lng'] - $hubCenter['lng'];

    $direction = '';
    if ($latDiff > 0.001) $direction .= 'North';
    elseif ($latDiff < -0.001) $direction .= 'South';

    if ($lngDiff > 0.001) $direction .= 'East';
    elseif ($lngDiff < -0.001) $direction .= 'West';

    if ($direction === '') $direction = 'Adjacent';

    // Get edge boundary for route visualization
    $edgeBoundary = $h3->directedEdgeToBoundary($edge);

    echo sprintf("  Route %d: %s\n", $i + 1, $direction);
    echo "    Destination: " . $h3->h3ToString($destCell) . "\n";
    echo "    Exit point: (" . sprintf("%.6f", $edgeBoundary[0]['lat']) . ", " . sprintf("%.6f", $edgeBoundary[0]['lng']) . ")\n\n";
}

// -----------------------------------------------------------------------------
// Example 5: Edge Validation in Data Import
// Real-world scenario: Validate H3 edge data from external sources
// -----------------------------------------------------------------------------

echo "\n5. Edge Data Validation\n";
echo str_repeat("-", 50) . "\n";

// Generate some valid edges
$cell1 = $h3->latLngToCell(23.7925, 90.4078, 9); // Gulshan
$neighbors = $h3->gridDisk($cell1, 1);
$cell2 = $neighbors[1];

$validEdge = $h3->cellsToDirectedEdge($cell1, $cell2);

// Test data including valid and invalid edges
$testEdges = [
    $h3->h3ToString($validEdge),           // Valid edge
    '16928308280ffffff',                    // Made up - likely invalid
    $h3->h3ToString($cell1),               // A cell, not an edge
];

echo "Validating edge data from import:\n\n";

foreach ($testEdges as $edgeStr) {
    echo "  Edge: $edgeStr\n";

    try {
        $edge = $h3->stringToH3($edgeStr);
        $isValid = $h3->isValidDirectedEdge($edge);

        if ($isValid) {
            $cells = $h3->directedEdgeToCells($edge);
            echo "    Status: VALID\n";
            echo "    Origin: " . $h3->h3ToString($cells['origin']) . "\n";
            echo "    Destination: " . $h3->h3ToString($cells['destination']) . "\n";
        } else {
            echo "    Status: INVALID (not a directed edge)\n";
        }
    } catch (\Exception $e) {
        echo "    Status: ERROR - " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// -----------------------------------------------------------------------------
// Example 6: Boundary Fence Generation
// Real-world scenario: Generate fence coordinates for a service area
// -----------------------------------------------------------------------------

echo "\n6. Service Area Boundary Fence\n";
echo str_repeat("-", 50) . "\n";

$serviceArea = $h3->latLngToCell(23.8103, 90.4125, 7); // Dhaka center
$serviceAreaCells = $h3->gridDisk($serviceArea, 1);

echo "Service area: " . count($serviceAreaCells) . " cells\n\n";

// Find all boundary edges (edges that exit the service area)
$boundaryEdges = [];

foreach ($serviceAreaCells as $cell) {
    $edges = $h3->originToDirectedEdges($cell);

    foreach ($edges as $edge) {
        if (!$h3->isValidDirectedEdge($edge)) {
            continue;
        }

        $dest = $h3->getDirectedEdgeDestination($edge);

        // If destination is not in service area, this is a boundary edge
        if (!in_array($dest, $serviceAreaCells)) {
            $boundaryEdges[] = $edge;
        }
    }
}

echo "Boundary edges found: " . count($boundaryEdges) . "\n\n";

// Generate GeoJSON-ready boundary coordinates
echo "Boundary fence coordinates (first 5 edges):\n";

$fenceCoordinates = [];
foreach (array_slice($boundaryEdges, 0, 5) as $edge) {
    $boundary = $h3->directedEdgeToBoundary($edge);
    foreach ($boundary as $point) {
        $fenceCoordinates[] = [$point['lng'], $point['lat']];
    }

    echo sprintf(
        "  [%.6f, %.6f] -> [%.6f, %.6f]\n",
        $boundary[0]['lng'],
        $boundary[0]['lat'],
        $boundary[1]['lng'],
        $boundary[1]['lat']
    );
}

echo "\nTotal fence coordinate pairs: " . count($fenceCoordinates) . "\n";
echo "(These can be used to draw a polygon boundary on a map)\n";

echo "\n=== End of Directed Edge Examples ===\n";
