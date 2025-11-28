<?php

/**
 * Real-World Use Case: Real Estate Analytics Platform (Bproperty/Bikroy Style - Dhaka)
 *
 * This example demonstrates how H3 can be used for real estate analytics:
 * - Property valuation by location
 * - Neighborhood analysis
 * - Market heatmaps
 * - Proximity scoring (schools, hospitals, transit)
 * - Investment opportunity identification
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

echo "=== Real Estate Analytics Platform Example (Dhaka, Bangladesh) ===\n\n";

// Configuration
$PROPERTY_RESOLUTION = 10;     // ~76m - individual properties
$NEIGHBORHOOD_RESOLUTION = 8;  // ~461m - neighborhood analysis
$MARKET_RESOLUTION = 7;        // ~1.4km - market trends

// -----------------------------------------------------------------------------
// Part 1: Property Database with Spatial Indexing
// -----------------------------------------------------------------------------

echo "PART 1: Property Database & Spatial Index\n";
echo str_repeat("=", 50) . "\n\n";

class PropertyDatabase
{
    private H3 $h3;
    private int $resolution;
    private array $properties = [];
    private array $propertiesByCell = [];

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function addProperty(array $property): void
    {
        $cell = $this->h3->latLngToCell(
            $property['lat'],
            $property['lng'],
            $this->resolution
        );

        $property['cell'] = $cell;
        $property['cell_hex'] = $this->h3->h3ToString($cell);
        $this->properties[$property['id']] = $property;

        if (!isset($this->propertiesByCell[$cell])) {
            $this->propertiesByCell[$cell] = [];
        }
        $this->propertiesByCell[$cell][] = $property['id'];
    }

    public function getPropertiesInArea(float $lat, float $lng, int $radiusK): array
    {
        $centerCell = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        $searchCells = $this->h3->gridDisk($centerCell, $radiusK);

        $results = [];
        foreach ($searchCells as $cell) {
            if (isset($this->propertiesByCell[$cell])) {
                foreach ($this->propertiesByCell[$cell] as $propId) {
                    $results[] = $this->properties[$propId];
                }
            }
        }

        return $results;
    }

    public function getProperty(string $id): ?array
    {
        return $this->properties[$id] ?? null;
    }

    public function getAllProperties(): array
    {
        return $this->properties;
    }
}

$propertyDb = new PropertyDatabase($h3, $PROPERTY_RESOLUTION);

// Sample properties in Dhaka (prices in BDT Lakh)
$sampleProperties = [
    ['id' => 'P001', 'address' => 'Gulshan 2 Apartment', 'lat' => 23.7925, 'lng' => 90.4078, 'type' => 'Apartment', 'sqft' => 1800, 'price' => 25000000, 'bedrooms' => 3],
    ['id' => 'P002', 'address' => 'Banani DOHS Villa', 'lat' => 23.7937, 'lng' => 90.4066, 'type' => 'Villa', 'sqft' => 3500, 'price' => 85000000, 'bedrooms' => 5],
    ['id' => 'P003', 'address' => 'Dhanmondi 27 Flat', 'lat' => 23.7461, 'lng' => 90.3742, 'type' => 'Apartment', 'sqft' => 1400, 'price' => 18000000, 'bedrooms' => 3],
    ['id' => 'P004', 'address' => 'Uttara Sector 7', 'lat' => 23.8759, 'lng' => 90.3795, 'type' => 'Apartment', 'sqft' => 1200, 'price' => 9500000, 'bedrooms' => 3],
    ['id' => 'P005', 'address' => 'Bashundhara R/A', 'lat' => 23.8135, 'lng' => 90.4250, 'type' => 'Duplex', 'sqft' => 2800, 'price' => 35000000, 'bedrooms' => 4],
    ['id' => 'P006', 'address' => 'Mirpur DOHS', 'lat' => 23.8350, 'lng' => 90.3680, 'type' => 'Apartment', 'sqft' => 1600, 'price' => 15000000, 'bedrooms' => 3],
    ['id' => 'P007', 'address' => 'Gulshan 1 Penthouse', 'lat' => 23.7850, 'lng' => 90.4150, 'type' => 'Penthouse', 'sqft' => 4200, 'price' => 120000000, 'bedrooms' => 5],
    ['id' => 'P008', 'address' => 'Mohammadpur Studio', 'lat' => 23.7662, 'lng' => 90.3588, 'type' => 'Studio', 'sqft' => 450, 'price' => 4500000, 'bedrooms' => 1],
];

foreach ($sampleProperties as $prop) {
    $propertyDb->addProperty($prop);
}

echo "Indexed " . count($sampleProperties) . " properties\n\n";

// Search properties
$searchLat = 23.7925;  // Gulshan area
$searchLng = 90.4078;

echo "--- Property Search ---\n";
echo "Searching near Gulshan: ($searchLat, $searchLng)\n\n";

$nearbyProperties = $propertyDb->getPropertiesInArea($searchLat, $searchLng, 3);

echo "Found " . count($nearbyProperties) . " properties:\n";
foreach ($nearbyProperties as $prop) {
    echo sprintf(
        "  %s - %s, %d bed, %d sqft - ৳%s\n",
        $prop['id'],
        $prop['type'],
        $prop['bedrooms'],
        $prop['sqft'],
        number_format($prop['price'])
    );
}

// -----------------------------------------------------------------------------
// Part 2: Neighborhood Price Analysis
// -----------------------------------------------------------------------------

echo "\n\nPART 2: Neighborhood Price Analysis\n";
echo str_repeat("=", 50) . "\n\n";

class NeighborhoodAnalyzer
{
    private H3 $h3;
    private int $resolution;

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function analyzeByNeighborhood(array $properties): array
    {
        $neighborhoods = [];

        foreach ($properties as $prop) {
            // Get neighborhood-level cell
            $neighborhoodCell = $this->h3->cellToParent($prop['cell'], $this->resolution);
            $cellHex = $this->h3->h3ToString($neighborhoodCell);

            if (!isset($neighborhoods[$cellHex])) {
                $neighborhoods[$cellHex] = [
                    'cell' => $neighborhoodCell,
                    'properties' => [],
                    'total_price' => 0,
                    'total_sqft' => 0,
                ];
            }

            $neighborhoods[$cellHex]['properties'][] = $prop;
            $neighborhoods[$cellHex]['total_price'] += $prop['price'];
            $neighborhoods[$cellHex]['total_sqft'] += $prop['sqft'];
        }

        // Calculate metrics
        foreach ($neighborhoods as $cellHex => &$hood) {
            $count = count($hood['properties']);
            $hood['property_count'] = $count;
            $hood['avg_price'] = $hood['total_price'] / $count;
            $hood['avg_sqft'] = $hood['total_sqft'] / $count;
            $hood['price_per_sqft'] = $hood['total_price'] / $hood['total_sqft'];
            $hood['center'] = $this->h3->cellToLatLng($hood['cell']);
            $hood['area_km2'] = $this->h3->cellAreaKm2($hood['cell']);
        }

        // Sort by price per sqft
        uasort($neighborhoods, fn($a, $b) => $b['price_per_sqft'] <=> $a['price_per_sqft']);

        return $neighborhoods;
    }
}

$analyzer = new NeighborhoodAnalyzer($h3, $NEIGHBORHOOD_RESOLUTION);
$neighborhoodStats = $analyzer->analyzeByNeighborhood($propertyDb->getAllProperties());

echo "Neighborhood Analysis (Price per sqft):\n\n";

foreach ($neighborhoodStats as $cellHex => $stats) {
    echo sprintf("Neighborhood: %s\n", $cellHex);
    echo sprintf("  Center: (%.4f, %.4f)\n", $stats['center']['lat'], $stats['center']['lng']);
    echo sprintf("  Properties: %d\n", $stats['property_count']);
    echo sprintf("  Avg price: ৳%s\n", number_format($stats['avg_price']));
    echo sprintf("  Avg size: %d sqft\n", round($stats['avg_sqft']));
    echo sprintf("  Price/sqft: ৳%s\n\n", number_format($stats['price_per_sqft']));
}

// -----------------------------------------------------------------------------
// Part 3: Proximity Scoring System
// -----------------------------------------------------------------------------

echo "\nPART 3: Proximity Scoring (Location Score)\n";
echo str_repeat("=", 50) . "\n\n";

class ProximityScorer
{
    private H3 $h3;
    private array $amenities = [];

    public function __construct(H3 $h3)
    {
        $this->h3 = $h3;
    }

    public function addAmenity(string $type, float $lat, float $lng, string $name): void
    {
        $this->amenities[] = [
            'type' => $type,
            'lat' => $lat,
            'lng' => $lng,
            'name' => $name,
        ];
    }

    public function calculateScore(float $lat, float $lng): array
    {
        $scores = [
            'transit' => ['weight' => 30, 'items' => [], 'score' => 0],
            'education' => ['weight' => 25, 'items' => [], 'score' => 0],
            'healthcare' => ['weight' => 20, 'items' => [], 'score' => 0],
            'shopping' => ['weight' => 15, 'items' => [], 'score' => 0],
            'parks' => ['weight' => 10, 'items' => [], 'score' => 0],
        ];

        foreach ($this->amenities as $amenity) {
            $distance = $this->h3->greatCircleDistanceM($lat, $lng, $amenity['lat'], $amenity['lng']);

            if ($distance <= 2000 && isset($scores[$amenity['type']])) {
                // Score decreases with distance (max 100 at 0m, 0 at 2000m)
                $itemScore = max(0, 100 - ($distance / 20));

                $scores[$amenity['type']]['items'][] = [
                    'name' => $amenity['name'],
                    'distance' => $distance,
                    'score' => $itemScore,
                ];
            }
        }

        // Calculate category scores (best item in each category)
        $totalScore = 0;
        foreach ($scores as $type => &$category) {
            if (count($category['items']) > 0) {
                // Take the best score from this category
                $bestScore = max(array_column($category['items'], 'score'));
                $category['score'] = $bestScore;
            }
            $totalScore += ($category['score'] * $category['weight'] / 100);
        }

        return [
            'total_score' => round($totalScore),
            'categories' => $scores,
            'grade' => $this->getGrade($totalScore),
        ];
    }

    private function getGrade(float $score): string
    {
        if ($score >= 90) return 'Premium Location';
        if ($score >= 70) return 'Excellent Location';
        if ($score >= 50) return 'Good Location';
        if ($score >= 25) return 'Developing Area';
        return 'Remote Area';
    }
}

$scorer = new ProximityScorer($h3);

// Add Dhaka amenities
$amenities = [
    // Transit
    ['transit', 23.7590, 90.3926, 'Tejgaon Rail Station'],
    ['transit', 23.7510, 90.3934, 'Farmgate Bus Stand'],
    ['transit', 23.8103, 90.4125, 'Shahbag Metro Station'],

    // Education
    ['education', 23.7284, 90.3917, 'Dhaka University'],
    ['education', 23.7940, 90.4055, 'BRAC University'],
    ['education', 23.7350, 90.3880, 'Viqarunnisa School'],

    // Healthcare
    ['healthcare', 23.7520, 90.3890, 'Square Hospital'],
    ['healthcare', 23.7935, 90.4060, 'United Hospital'],
    ['healthcare', 23.7600, 90.4100, 'Apollo Hospital'],

    // Shopping
    ['shopping', 23.7508, 90.3921, 'Bashundhara City'],
    ['shopping', 23.7790, 90.4090, 'Jamuna Future Park'],
    ['shopping', 23.7460, 90.3745, 'Dhanmondi 27 Market'],

    // Parks
    ['parks', 23.7380, 90.3800, 'Dhanmondi Lake'],
    ['parks', 23.7280, 90.3930, 'Ramna Park'],
    ['parks', 23.7820, 90.4080, 'Gulshan Lake'],
];

foreach ($amenities as $a) {
    $scorer->addAmenity($a[0], $a[1], $a[2], $a[3]);
}

// Score properties
echo "Property Location Scores:\n\n";

foreach (array_slice($sampleProperties, 0, 4) as $prop) {
    $score = $scorer->calculateScore($prop['lat'], $prop['lng']);

    echo sprintf("%s - %s\n", $prop['id'], $prop['address']);
    echo sprintf("  Overall Score: %d/100 (%s)\n", $score['total_score'], $score['grade']);
    echo "  Category breakdown:\n";

    foreach ($score['categories'] as $type => $cat) {
        $itemCount = count($cat['items']);
        echo sprintf("    %s: %d/100 (%d nearby)\n", ucfirst($type), round($cat['score']), $itemCount);
    }
    echo "\n";
}

// -----------------------------------------------------------------------------
// Part 4: Market Heatmap Generation
// -----------------------------------------------------------------------------

echo "\nPART 4: Market Heatmap Data\n";
echo str_repeat("=", 50) . "\n\n";

class MarketHeatmap
{
    private H3 $h3;
    private int $resolution;

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function generateHeatmap(array $properties): array
    {
        $cells = [];

        foreach ($properties as $prop) {
            $cell = $this->h3->latLngToCell($prop['lat'], $prop['lng'], $this->resolution);
            $cellHex = $this->h3->h3ToString($cell);

            if (!isset($cells[$cellHex])) {
                $cells[$cellHex] = [
                    'cell' => $cell,
                    'prices' => [],
                    'count' => 0,
                ];
            }

            $cells[$cellHex]['prices'][] = $prop['price'];
            $cells[$cellHex]['count']++;
        }

        // Calculate statistics and get boundaries
        $heatmapData = [];
        foreach ($cells as $cellHex => $data) {
            $avgPrice = array_sum($data['prices']) / count($data['prices']);
            $boundary = $this->h3->cellToBoundary($data['cell']);
            $center = $this->h3->cellToLatLng($data['cell']);

            // Normalize intensity based on Dhaka prices (max ~120 crore)
            $heatmapData[] = [
                'cell' => $cellHex,
                'center' => $center,
                'boundary' => $boundary,
                'avg_price' => $avgPrice,
                'property_count' => $data['count'],
                'intensity' => min(1.0, $avgPrice / 100000000),
            ];
        }

        // Sort by average price
        usort($heatmapData, fn($a, $b) => $b['avg_price'] <=> $a['avg_price']);

        return $heatmapData;
    }
}

$heatmap = new MarketHeatmap($h3, $MARKET_RESOLUTION);
$heatmapData = $heatmap->generateHeatmap($sampleProperties);

echo "Price Heatmap Data (for visualization):\n\n";

foreach ($heatmapData as $cell) {
    $color = $cell['intensity'] > 0.7 ? 'RED (Premium)' : ($cell['intensity'] > 0.4 ? 'ORANGE (Mid-range)' : 'BLUE (Affordable)');

    echo sprintf("Cell: %s\n", $cell['cell']);
    echo sprintf("  Avg Price: ৳%s\n", number_format($cell['avg_price']));
    echo sprintf("  Properties: %d\n", $cell['property_count']);
    echo sprintf("  Intensity: %.2f (%s)\n", $cell['intensity'], $color);
    echo sprintf("  Boundary vertices: %d\n\n", count($cell['boundary']));
}

// -----------------------------------------------------------------------------
// Part 5: Investment Opportunity Finder
// -----------------------------------------------------------------------------

echo "\nPART 5: Investment Opportunity Analysis\n";
echo str_repeat("=", 50) . "\n\n";

function findInvestmentOpportunities(H3 $h3, array $properties, int $resolution): array
{
    // Group by neighborhood
    $neighborhoods = [];

    foreach ($properties as $prop) {
        $neighborhoodCell = $h3->cellToParent($prop['cell'], $resolution);
        $cellHex = $h3->h3ToString($neighborhoodCell);

        if (!isset($neighborhoods[$cellHex])) {
            $neighborhoods[$cellHex] = [
                'cell' => $neighborhoodCell,
                'properties' => [],
            ];
        }
        $neighborhoods[$cellHex]['properties'][] = $prop;
    }

    $opportunities = [];

    foreach ($neighborhoods as $cellHex => $hood) {
        if (count($hood['properties']) < 2) continue;

        $prices = array_column($hood['properties'], 'price');
        $avgPrice = array_sum($prices) / count($prices);
        $minPrice = min($prices);
        $maxPrice = max($prices);

        // Find properties priced below neighborhood average
        foreach ($hood['properties'] as $prop) {
            $priceVsAvg = (($prop['price'] - $avgPrice) / $avgPrice) * 100;

            if ($priceVsAvg < -10) { // More than 10% below average
                $opportunities[] = [
                    'property' => $prop,
                    'neighborhood_avg' => $avgPrice,
                    'discount_percent' => abs($priceVsAvg),
                    'potential_value' => $avgPrice,
                    'upside' => $avgPrice - $prop['price'],
                ];
            }
        }
    }

    // Sort by upside potential
    usort($opportunities, fn($a, $b) => $b['upside'] <=> $a['upside']);

    return $opportunities;
}

$opportunities = findInvestmentOpportunities($h3, $propertyDb->getAllProperties(), $NEIGHBORHOOD_RESOLUTION);

if (count($opportunities) > 0) {
    echo "Investment Opportunities Found:\n\n";

    foreach ($opportunities as $i => $opp) {
        $prop = $opp['property'];
        echo sprintf("%d. %s - %s\n", $i + 1, $prop['id'], $prop['address']);
        echo sprintf("   Listed: ৳%s\n", number_format($prop['price']));
        echo sprintf("   Neighborhood Avg: ৳%s\n", number_format($opp['neighborhood_avg']));
        echo sprintf("   Discount: %.1f%% below average\n", $opp['discount_percent']);
        echo sprintf("   Potential Upside: ৳%s\n\n", number_format($opp['upside']));
    }
} else {
    echo "No significant investment opportunities found in current listings.\n";
    echo "All properties are priced at or above neighborhood averages.\n";
}

echo "=== End of Real Estate Analytics Example ===\n";
