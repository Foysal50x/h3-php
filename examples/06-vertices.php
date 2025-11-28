<?php

/**
 * H3 Vertex Functions - Real World Examples (Bangladesh/Dhaka)
 *
 * This file demonstrates vertex functions:
 * - cellToVertex: Get a specific vertex of a cell
 * - cellToVertexes: Get all vertices of a cell
 * - vertexToLatLng: Convert vertex to coordinates
 * - isValidVertex: Validate vertex index
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

echo "=== H3 Vertex Functions Examples (Dhaka, Bangladesh) ===\n\n";

// -----------------------------------------------------------------------------
// Example 1: Infrastructure Placement at Hexagon Corners
// Real-world scenario: Place cell towers at hexagon vertices for coverage
// -----------------------------------------------------------------------------

echo "1. Cell Tower Placement at Vertices\n";
echo str_repeat("-", 50) . "\n";

// Coverage area in Gulshan for Grameenphone/Robi tower placement
$coverageArea = $h3->latLngToCell(23.7925, 90.4078, 7);
echo "Coverage cell (Gulshan area): " . $h3->h3ToString($coverageArea) . "\n\n";

// Get all vertices of this cell
$vertices = $h3->cellToVertexes($coverageArea);

echo "Potential tower locations (vertices):\n\n";

foreach ($vertices as $i => $vertex) {
    if (!$h3->isValidVertex($vertex)) {
        continue;
    }

    $coords = $h3->vertexToLatLng($vertex);

    echo sprintf(
        "  Tower Site %d:\n    Vertex: %s\n    Coordinates: (%.6f, %.6f)\n\n",
        $i + 1,
        $h3->h3ToString($vertex),
        $coords['lat'],
        $coords['lng']
    );
}

// Calculate optimal coverage
echo "Coverage Analysis:\n";
echo "  A tower at each vertex would provide overlapping coverage\n";
echo "  for 3 adjacent hexagons, maximizing signal strength.\n";

// -----------------------------------------------------------------------------
// Example 2: Intersection Point Detection
// Real-world scenario: Find where delivery zones meet (for handoff points)
// -----------------------------------------------------------------------------

echo "\n\n2. Delivery Zone Handoff Points\n";
echo str_repeat("-", 50) . "\n";

// Three adjacent delivery zones in Dhanmondi area
$zone1 = $h3->latLngToCell(23.7461, 90.3742, 8); // Dhanmondi
$neighbors = $h3->gridDisk($zone1, 1);
$zone2 = $neighbors[1];
$zone3 = $neighbors[2];

echo "Delivery zones:\n";
echo "  Zone 1 (Dhanmondi Central): " . $h3->h3ToString($zone1) . "\n";
echo "  Zone 2: " . $h3->h3ToString($zone2) . "\n";
echo "  Zone 3: " . $h3->h3ToString($zone3) . "\n\n";

// Get vertices for zone 1
$zone1Vertices = $h3->cellToVertexes($zone1);
$zone2Vertices = $h3->cellToVertexes($zone2);
$zone3Vertices = $h3->cellToVertexes($zone3);

// Find shared vertices (intersection points)
$sharedVertices = array_intersect($zone1Vertices, $zone2Vertices);

echo "Shared vertices between Zone 1 and Zone 2: " . count($sharedVertices) . "\n\n";

if (count($sharedVertices) > 0) {
    echo "Optimal handoff points:\n";
    foreach (array_values($sharedVertices) as $i => $vertex) {
        if (!$h3->isValidVertex($vertex)) {
            continue;
        }
        $coords = $h3->vertexToLatLng($vertex);
        echo sprintf(
            "  Point %d: (%.6f, %.6f)\n",
            $i + 1,
            $coords['lat'],
            $coords['lng']
        );
    }
    echo "\n  These points are ideal for delivery handoffs between zones.\n";
}

// -----------------------------------------------------------------------------
// Example 3: Polygon Rendering for Maps
// Real-world scenario: Generate precise hexagon polygons for map visualization
// -----------------------------------------------------------------------------

echo "\n\n3. Map Polygon Generation\n";
echo str_repeat("-", 50) . "\n";

$mapCell = $h3->latLngToCell(23.8103, 90.4125, 9); // Dhaka center
echo "Map cell: " . $h3->h3ToString($mapCell) . "\n\n";

$cellVertices = $h3->cellToVertexes($mapCell);

echo "Generating SVG-ready polygon coordinates:\n\n";

$svgPoints = [];
foreach ($cellVertices as $vertex) {
    if (!$h3->isValidVertex($vertex)) {
        continue;
    }
    $coords = $h3->vertexToLatLng($vertex);
    $svgPoints[] = sprintf("%.6f,%.6f", $coords['lng'], $coords['lat']);
}

echo "SVG polygon points:\n";
echo "  <polygon points=\"" . implode(" ", $svgPoints) . "\" />\n\n";

echo "GeoJSON coordinates:\n";
echo "  [\n";
foreach ($cellVertices as $i => $vertex) {
    if (!$h3->isValidVertex($vertex)) {
        continue;
    }
    $coords = $h3->vertexToLatLng($vertex);
    $comma = ($i < count($cellVertices) - 1) ? ',' : '';
    echo sprintf("    [%.6f, %.6f]%s\n", $coords['lng'], $coords['lat'], $comma);
}
echo "  ]\n";

// -----------------------------------------------------------------------------
// Example 4: Vertex-Based Sensor Placement
// Real-world scenario: Place environmental sensors at optimal hexagon vertices
// -----------------------------------------------------------------------------

echo "\n\n4. Environmental Sensor Network\n";
echo str_repeat("-", 50) . "\n";

// Create a sensor network covering Mirpur area
$networkCenter = $h3->latLngToCell(23.8223, 90.3654, 8); // Mirpur
$networkCells = $h3->gridDisk($networkCenter, 2);

echo "Sensor network (Mirpur area):\n";
echo "  Coverage cells: " . count($networkCells) . "\n\n";

// Collect all unique vertices in the network
$allVertices = [];
foreach ($networkCells as $cell) {
    $vertices = $h3->cellToVertexes($cell);
    foreach ($vertices as $vertex) {
        if ($h3->isValidVertex($vertex)) {
            $allVertices[$vertex] = true;
        }
    }
}

$uniqueVertices = array_keys($allVertices);

echo "Sensor placement analysis:\n";
echo "  Total unique vertices: " . count($uniqueVertices) . "\n";
echo "  Sensors needed (1 per vertex): " . count($uniqueVertices) . "\n\n";

// Show first 5 sensor locations
echo "First 5 sensor locations:\n";
foreach (array_slice($uniqueVertices, 0, 5) as $i => $vertex) {
    $coords = $h3->vertexToLatLng($vertex);
    echo sprintf(
        "  Sensor %d: (%.6f, %.6f)\n",
        $i + 1,
        $coords['lat'],
        $coords['lng']
    );
}

// Calculate coverage efficiency
$avgArea = $h3->getHexagonAreaAvgM2(8);
$totalCoverage = count($networkCells) * $avgArea;
echo sprintf("\nTotal network coverage: %.2f km²\n", $totalCoverage / 1000000);
echo sprintf("Coverage per sensor: %.0f m²\n", $totalCoverage / count($uniqueVertices));

// -----------------------------------------------------------------------------
// Example 5: Vertex Validation and Error Handling
// Real-world scenario: Robust handling of vertex operations
// -----------------------------------------------------------------------------

echo "\n\n5. Vertex Validation Pipeline\n";
echo str_repeat("-", 50) . "\n";

$testCell = $h3->latLngToCell(23.7925, 90.4078, 9);
echo "Test cell (Gulshan): " . $h3->h3ToString($testCell) . "\n\n";

// Get individual vertices with validation
echo "Individual vertex access:\n\n";

for ($vertexNum = 0; $vertexNum < 6; $vertexNum++) {
    try {
        $vertex = $h3->cellToVertex($testCell, $vertexNum);
        $isValid = $h3->isValidVertex($vertex);

        echo sprintf("  Vertex %d: ", $vertexNum);

        if ($isValid) {
            $coords = $h3->vertexToLatLng($vertex);
            echo sprintf(
                "VALID - (%.6f, %.6f)\n",
                $coords['lat'],
                $coords['lng']
            );
        } else {
            echo "INVALID\n";
        }
    } catch (\Exception $e) {
        echo sprintf("  Vertex %d: ERROR - %s\n", $vertexNum, $e->getMessage());
    }
}

// Test with invalid vertex number
echo "\nTesting invalid vertex number (10):\n";
try {
    $invalidVertex = $h3->cellToVertex($testCell, 10);
    echo "  Result: Unexpected success\n";
} catch (\Exception $e) {
    echo "  Result: Correctly caught error\n";
    echo "  Error: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// Example 6: Vertex-Based Distance Calculations
// Real-world scenario: Calculate distances between hexagon corners
// -----------------------------------------------------------------------------

echo "\n\n6. Vertex Distance Analysis\n";
echo str_repeat("-", 50) . "\n";

$cell = $h3->latLngToCell(23.7590, 90.3926, 8); // Tejgaon
echo "Analysis cell (Tejgaon): " . $h3->h3ToString($cell) . "\n\n";

$vertices = $h3->cellToVertexes($cell);
$vertexCoords = [];

foreach ($vertices as $vertex) {
    if ($h3->isValidVertex($vertex)) {
        $vertexCoords[] = $h3->vertexToLatLng($vertex);
    }
}

// Calculate edge lengths (distances between consecutive vertices)
echo "Edge lengths (vertex to vertex):\n\n";

for ($i = 0; $i < count($vertexCoords); $i++) {
    $next = ($i + 1) % count($vertexCoords);

    $distance = $h3->greatCircleDistanceM(
        $vertexCoords[$i]['lat'],
        $vertexCoords[$i]['lng'],
        $vertexCoords[$next]['lat'],
        $vertexCoords[$next]['lng']
    );

    echo sprintf("  Edge %d-%d: %.2f meters\n", $i + 1, $next + 1, $distance);
}

// Calculate diameter (opposite vertices)
if (count($vertexCoords) >= 4) {
    $diameter = $h3->greatCircleDistanceM(
        $vertexCoords[0]['lat'],
        $vertexCoords[0]['lng'],
        $vertexCoords[3]['lat'],
        $vertexCoords[3]['lng']
    );
    echo sprintf("\nHexagon diameter: %.2f meters\n", $diameter);
}

echo "\n=== End of Vertex Examples ===\n";
