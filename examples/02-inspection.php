<?php

/**
 * H3 Inspection Functions - Real World Examples (Bangladesh/Dhaka)
 *
 * This file demonstrates cell inspection functions:
 * - getResolution: Get the resolution level of a cell
 * - getBaseCellNumber: Get the base cell (0-121)
 * - h3ToString / stringToH3: Convert between integer and string formats
 * - isValidCell: Validate H3 cell indices
 * - isResClassIII: Check resolution class
 * - isPentagon: Check if cell is a pentagon
 * - getIcosahedronFaces: Get intersecting icosahedron faces
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

echo "=== H3 Inspection Functions Examples (Bangladesh) ===\n\n";

// -----------------------------------------------------------------------------
// Example 1: Data Validation in API Endpoints
// Real-world scenario: Validate H3 cells received from client applications
// -----------------------------------------------------------------------------

echo "1. API Input Validation\n";
echo str_repeat("-", 50) . "\n";

// Simulated API inputs (some valid, some invalid)
$apiInputs = [
    '8928308280fffff',  // Valid cell
    '892830828ffffff',  // Invalid cell
    '8a2830828007fff',  // Valid cell (different resolution)
    'invalid_string',   // Invalid format
    '8028308280fffff',  // Invalid cell
];

echo "Validating H3 cell inputs from API:\n\n";

foreach ($apiInputs as $input) {
    echo "  Input: '$input'\n";

    try {
        $cell = $h3->stringToH3($input);
        $isValid = $h3->isValidCell($cell);

        if ($isValid) {
            $resolution = $h3->getResolution($cell);
            echo "    Status: VALID\n";
            echo "    Resolution: $resolution\n";
            echo "    Integer: $cell\n";
        } else {
            echo "    Status: INVALID (not a valid H3 cell)\n";
        }
    } catch (\Exception $e) {
        echo "    Status: ERROR - " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// -----------------------------------------------------------------------------
// Example 2: Resolution Detection for Data Processing
// Real-world scenario: Auto-detect resolution when importing external H3 data
// -----------------------------------------------------------------------------

echo "\n2. Auto-Detecting Resolution from External Data\n";
echo str_repeat("-", 50) . "\n";

// Simulated imported data from different delivery partners in Bangladesh
$externalData = [
    ['source' => 'Foodpanda BD', 'cell' => '872830828ffffff'],
    ['source' => 'Pathao Food', 'cell' => '8928308280fffff'],
    ['source' => 'Shohoz Food', 'cell' => '8a2830828007fff'],
    ['source' => 'HungryNaki', 'cell' => '8c2830828001dff'],
];

echo "Detecting resolutions from imported data:\n\n";

$resolutionCounts = [];
foreach ($externalData as $data) {
    $cell = $h3->stringToH3($data['cell']);
    $resolution = $h3->getResolution($cell);

    if (!isset($resolutionCounts[$resolution])) {
        $resolutionCounts[$resolution] = 0;
    }
    $resolutionCounts[$resolution]++;

    echo sprintf(
        "  %s: Resolution %d\n",
        $data['source'],
        $resolution
    );
}

echo "\nResolution distribution:\n";
foreach ($resolutionCounts as $res => $count) {
    echo "  Resolution $res: $count records\n";
}

// -----------------------------------------------------------------------------
// Example 3: Base Cell Analysis for Global Distribution
// Real-world scenario: Analyze geographic distribution of data across base cells
// -----------------------------------------------------------------------------

echo "\n\n3. Regional Data Distribution Analysis\n";
echo str_repeat("-", 50) . "\n";

// Major cities in Bangladesh
$regionalLocations = [
    ['name' => 'Dhaka', 'lat' => 23.8103, 'lng' => 90.4125],
    ['name' => 'Chittagong', 'lat' => 22.3569, 'lng' => 91.7832],
    ['name' => 'Sylhet', 'lat' => 24.8949, 'lng' => 91.8687],
    ['name' => 'Khulna', 'lat' => 22.8456, 'lng' => 89.5403],
    ['name' => 'Rajshahi', 'lat' => 24.3745, 'lng' => 88.6042],
    ['name' => 'Rangpur', 'lat' => 25.7439, 'lng' => 89.2752],
];

echo "Base cell distribution for Bangladesh divisions:\n\n";

foreach ($regionalLocations as $location) {
    $cell = $h3->latLngToCell($location['lat'], $location['lng'], 5);
    $baseCell = $h3->getBaseCellNumber($cell);
    $faces = $h3->getIcosahedronFaces($cell);

    echo sprintf(
        "  %s\n    Base Cell: %d (of 122)\n    Icosahedron Faces: [%s]\n\n",
        $location['name'],
        $baseCell,
        implode(', ', $faces)
    );
}

// -----------------------------------------------------------------------------
// Example 4: Pentagon Detection for Special Handling
// Real-world scenario: Handle pentagon cells differently in spatial algorithms
// -----------------------------------------------------------------------------

echo "\n4. Pentagon Detection for Algorithm Optimization\n";
echo str_repeat("-", 50) . "\n";

echo "Checking for pentagons (12 exist at each resolution):\n\n";

// Get all pentagons at resolution 4
$pentagons = $h3->getPentagons(4);

echo "Pentagons at resolution 4:\n";
foreach (array_slice($pentagons, 0, 5) as $i => $pentagon) {
    $center = $h3->cellToLatLng($pentagon);
    $boundary = $h3->cellToBoundary($pentagon);

    echo sprintf(
        "  Pentagon %d: %s\n    Center: (%.4f, %.4f)\n    Vertices: %d (not 6!)\n\n",
        $i + 1,
        $h3->h3ToString($pentagon),
        $center['lat'],
        $center['lng'],
        count($boundary)
    );
}
echo "  ... and " . (count($pentagons) - 5) . " more\n";

// Check a regular location in Dhaka
$regularCell = $h3->latLngToCell(23.8103, 90.4125, 4);
echo "\nRegular cell (Dhaka): " . ($h3->isPentagon($regularCell) ? 'Pentagon' : 'Hexagon') . "\n";

// -----------------------------------------------------------------------------
// Example 5: Resolution Class Analysis
// Real-world scenario: Ensure consistent resolution classes when combining data
// -----------------------------------------------------------------------------

echo "\n\n5. Resolution Class Compatibility Check\n";
echo str_repeat("-", 50) . "\n";

echo "H3 has two resolution classes (Class II and Class III):\n";
echo "- Class II (even resolutions): 0, 2, 4, 6, 8, 10, 12, 14\n";
echo "- Class III (odd resolutions): 1, 3, 5, 7, 9, 11, 13, 15\n\n";

$testCell = $h3->latLngToCell(23.8103, 90.4125, 9); // Dhaka

echo "Checking resolution class for different cells:\n\n";

for ($res = 7; $res <= 12; $res++) {
    $cell = $h3->latLngToCell(23.8103, 90.4125, $res);
    $isClassIII = $h3->isResClassIII($cell);

    echo sprintf(
        "  Resolution %2d: Class %s (%s)\n",
        $res,
        $isClassIII ? 'III' : 'II',
        $isClassIII ? 'odd' : 'even'
    );
}

// -----------------------------------------------------------------------------
// Example 6: H3 Index Format Conversion for Storage
// Real-world scenario: Convert between formats for database storage and APIs
// -----------------------------------------------------------------------------

echo "\n\n6. Format Conversion for Storage and APIs\n";
echo str_repeat("-", 50) . "\n";

$lat = 23.7925;  // Gulshan 2
$lng = 90.4078;
$resolution = 9;

$cellInt = $h3->latLngToCell($lat, $lng, $resolution);
$cellStr = $h3->h3ToString($cellInt);

echo "Converting H3 cell between formats:\n\n";
echo "  Original coordinates: ($lat, $lng) at resolution $resolution\n";
echo "  Location: Gulshan 2, Dhaka\n\n";

echo "  Integer format (for efficient storage/computation):\n";
echo "    Value: $cellInt\n";
echo "    Use case: Database indexes, binary protocols\n\n";

echo "  String format (for APIs and readability):\n";
echo "    Value: $cellStr\n";
echo "    Use case: REST APIs, JSON responses, logging\n\n";

// Round-trip verification
$backToInt = $h3->stringToH3($cellStr);
$backToStr = $h3->h3ToString($backToInt);

echo "  Round-trip verification:\n";
echo "    Int -> String -> Int: " . ($cellInt === $backToInt ? 'PASS' : 'FAIL') . "\n";
echo "    String -> Int -> String: " . ($cellStr === $backToStr ? 'PASS' : 'FAIL') . "\n";

// -----------------------------------------------------------------------------
// Example 7: Bulk Cell Validation for Data Import
// Real-world scenario: Validate large dataset before processing
// -----------------------------------------------------------------------------

echo "\n\n7. Bulk Data Validation Report\n";
echo str_repeat("-", 50) . "\n";

// Simulated bulk import data from Dhaka area
$bulkData = [];
for ($i = 0; $i < 100; $i++) {
    // Generate cells around Dhaka
    $lat = 23.7 + (mt_rand(0, 1000) / 10000);
    $lng = 90.3 + (mt_rand(0, 1000) / 10000);
    $cell = $h3->latLngToCell($lat, $lng, 9);
    $bulkData[] = $h3->h3ToString($cell);
}

// Add some intentionally bad data
$bulkData[] = 'invalid1';
$bulkData[] = 'invalid2';
$bulkData[] = '0000000000000000';

$validCount = 0;
$invalidCount = 0;
$resolutions = [];

foreach ($bulkData as $cellStr) {
    try {
        $cell = $h3->stringToH3($cellStr);
        if ($h3->isValidCell($cell)) {
            $validCount++;
            $res = $h3->getResolution($cell);
            if (!isset($resolutions[$res])) {
                $resolutions[$res] = 0;
            }
            $resolutions[$res]++;
        } else {
            $invalidCount++;
        }
    } catch (\Exception $e) {
        $invalidCount++;
    }
}

echo "Validation Report:\n";
echo "  Total records: " . count($bulkData) . "\n";
echo "  Valid cells: $validCount\n";
echo "  Invalid cells: $invalidCount\n";
echo "  Validation rate: " . round(($validCount / count($bulkData)) * 100, 1) . "%\n\n";

echo "Resolution breakdown (valid cells):\n";
ksort($resolutions);
foreach ($resolutions as $res => $count) {
    echo "  Resolution $res: $count cells\n";
}

echo "\n=== End of Inspection Examples ===\n";
