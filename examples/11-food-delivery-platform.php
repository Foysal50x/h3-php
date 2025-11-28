<?php

/**
 * Real-World Use Case: Food Delivery Platform (Foodpanda Style - Dhaka)
 *
 * This example demonstrates how H3 can be used to build a food delivery
 * platform like Foodpanda, Pathao Food in Bangladesh:
 * - Restaurant indexing and search
 * - Delivery zone management
 * - Delivery time estimation
 * - Dynamic delivery fees
 * - Order batching optimization
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

echo "=== Food Delivery Platform Example (Dhaka, Bangladesh) ===\n\n";

// Configuration
$RESTAURANT_RESOLUTION = 9;   // ~174m - restaurant locations
$DELIVERY_ZONE_RESOLUTION = 8; // ~461m - delivery zones
$FEE_ZONE_RESOLUTION = 7;     // ~1.4km - fee calculation zones

// -----------------------------------------------------------------------------
// Part 1: Restaurant Index and Search
// -----------------------------------------------------------------------------

echo "PART 1: Restaurant Index & Search\n";
echo str_repeat("=", 50) . "\n\n";

class RestaurantIndex
{
    private H3 $h3;
    private int $resolution;
    private array $restaurantsByCell = [];
    private array $restaurants = [];

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function addRestaurant(array $restaurant): void
    {
        $cell = $this->h3->latLngToCell(
            $restaurant['lat'],
            $restaurant['lng'],
            $this->resolution
        );

        $restaurant['cell'] = $cell;
        $this->restaurants[$restaurant['id']] = $restaurant;

        if (!isset($this->restaurantsByCell[$cell])) {
            $this->restaurantsByCell[$cell] = [];
        }
        $this->restaurantsByCell[$cell][] = $restaurant['id'];
    }

    public function searchNearby(float $lat, float $lng, int $radiusK = 3, ?string $cuisine = null): array
    {
        $userCell = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        $searchCells = $this->h3->gridDisk($userCell, $radiusK);

        $results = [];
        foreach ($searchCells as $cell) {
            if (!isset($this->restaurantsByCell[$cell])) {
                continue;
            }

            foreach ($this->restaurantsByCell[$cell] as $restaurantId) {
                $restaurant = $this->restaurants[$restaurantId];

                if ($cuisine !== null && $restaurant['cuisine'] !== $cuisine) {
                    continue;
                }

                $distance = $this->h3->greatCircleDistanceM(
                    $lat, $lng,
                    $restaurant['lat'], $restaurant['lng']
                );

                $results[] = array_merge($restaurant, [
                    'distance_m' => $distance,
                    'distance_km' => $distance / 1000,
                ]);
            }
        }

        usort($results, fn($a, $b) => $a['distance_m'] <=> $b['distance_m']);
        return $results;
    }

    public function getRestaurant(string $id): ?array
    {
        return $this->restaurants[$id] ?? null;
    }
}

$restaurantIndex = new RestaurantIndex($h3, $RESTAURANT_RESOLUTION);

//restaurants (coordinates are approximate for demonstration)
$sampleRestaurants = [
    ['id' => 'R001', 'name' => 'Star Kabab', 'cuisine' => 'Bengali', 'lat' => 23.7925, 'lng' => 90.4078, 'prep_time' => 20],
    ['id' => 'R002', 'name' => 'Pizza Hut Gulshan', 'cuisine' => 'Italian', 'lat' => 23.7930, 'lng' => 90.4080, 'prep_time' => 25],
    ['id' => 'R003', 'name' => 'Nando\'s Banani', 'cuisine' => 'Portuguese', 'lat' => 23.7937, 'lng' => 90.4066, 'prep_time' => 20],
    ['id' => 'R004', 'name' => 'Chillox', 'cuisine' => 'Fast Food', 'lat' => 23.7920, 'lng' => 90.4085, 'prep_time' => 15],
    ['id' => 'R005', 'name' => 'Kacchi Bhai', 'cuisine' => 'Bengali', 'lat' => 23.7461, 'lng' => 90.3742, 'prep_time' => 30],
    ['id' => 'R006', 'name' => 'Madchef', 'cuisine' => 'Continental', 'lat' => 23.7935, 'lng' => 90.4070, 'prep_time' => 25],
    ['id' => 'R007', 'name' => 'Takeout', 'cuisine' => 'Thai', 'lat' => 23.7922, 'lng' => 90.4082, 'prep_time' => 22],
    ['id' => 'R008', 'name' => 'Café Mango', 'cuisine' => 'Fusion', 'lat' => 23.7940, 'lng' => 90.4075, 'prep_time' => 18],
];

foreach ($sampleRestaurants as $restaurant) {
    $restaurantIndex->addRestaurant($restaurant);
}

echo "Indexed " . count($sampleRestaurants) . " restaurants\n\n";

// Customer search from Gulshan
$customerLat = 23.7928;
$customerLng = 90.4079;

echo "--- Customer Search ---\n";
echo "Customer location (Gulshan 2): ($customerLat, $customerLng)\n\n";

$nearbyRestaurants = $restaurantIndex->searchNearby($customerLat, $customerLng, 2);

echo "Nearby restaurants:\n";
foreach (array_slice($nearbyRestaurants, 0, 5) as $i => $r) {
    echo sprintf(
        "  %d. %s (%s) - %.0f m\n",
        $i + 1,
        $r['name'],
        $r['cuisine'],
        $r['distance_m']
    );
}

// Cuisine filter
echo "\nFiltered by cuisine (Bengali):\n";
$bengaliRestaurants = $restaurantIndex->searchNearby($customerLat, $customerLng, 5, 'Bengali');
foreach ($bengaliRestaurants as $r) {
    echo sprintf("  - %s - %.0f m\n", $r['name'], $r['distance_m']);
}

// -----------------------------------------------------------------------------
// Part 2: Delivery Zone Management
// -----------------------------------------------------------------------------

echo "\n\nPART 2: Delivery Zone Management\n";
echo str_repeat("=", 50) . "\n\n";

class DeliveryZoneManager
{
    private H3 $h3;
    private int $resolution;
    private array $restaurantZones = [];

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function setDeliveryRadius(string $restaurantId, float $lat, float $lng, int $radiusK): void
    {
        $center = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        $zoneCells = $this->h3->gridDisk($center, $radiusK);

        // Compact for efficient storage
        $compacted = $this->h3->compactCells($zoneCells);

        $this->restaurantZones[$restaurantId] = [
            'center' => $center,
            'radius_k' => $radiusK,
            'cells' => $zoneCells,
            'compacted' => $compacted,
        ];
    }

    public function canDeliver(string $restaurantId, float $lat, float $lng): bool
    {
        if (!isset($this->restaurantZones[$restaurantId])) {
            return false;
        }

        $customerCell = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        return in_array($customerCell, $this->restaurantZones[$restaurantId]['cells']);
    }

    public function getZoneStats(string $restaurantId): array
    {
        if (!isset($this->restaurantZones[$restaurantId])) {
            return [];
        }

        $zone = $this->restaurantZones[$restaurantId];
        $totalArea = 0;
        foreach ($zone['cells'] as $cell) {
            $totalArea += $this->h3->cellAreaKm2($cell);
        }

        return [
            'cell_count' => count($zone['cells']),
            'compacted_count' => count($zone['compacted']),
            'compression_ratio' => 1 - (count($zone['compacted']) / count($zone['cells'])),
            'area_km2' => $totalArea,
        ];
    }
}

$zoneManager = new DeliveryZoneManager($h3, $DELIVERY_ZONE_RESOLUTION);

// Set delivery zones for restaurants
foreach ($sampleRestaurants as $r) {
    // Radius based on restaurant type (some deliver farther)
    $radius = match ($r['cuisine']) {
        'Italian', 'Fast Food' => 4,    // Pizza and fast food deliver far
        'Bengali' => 5,                  // Kacchi/Biryani popular for delivery
        'Thai', 'Portuguese' => 2,       // Shorter distance for freshness
        default => 3,
    };

    $zoneManager->setDeliveryRadius($r['id'], $r['lat'], $r['lng'], $radius);
}

echo "Delivery zone statistics:\n\n";

foreach (['R001', 'R002', 'R005'] as $rid) {
    $r = $restaurantIndex->getRestaurant($rid);
    $stats = $zoneManager->getZoneStats($rid);

    echo sprintf("  %s:\n", $r['name']);
    echo sprintf("    Coverage: %d cells (%.2f km²)\n", $stats['cell_count'], $stats['area_km2']);
    echo sprintf("    Storage: %d compacted cells (%.0f%% compression)\n\n",
        $stats['compacted_count'],
        $stats['compression_ratio'] * 100
    );
}

// Check delivery availability
echo "--- Delivery Availability Check ---\n";
$testAddresses = [
    ['name' => 'Gulshan customer', 'lat' => 23.7930, 'lng' => 90.4082],
    ['name' => 'Uttara customer', 'lat' => 23.8759, 'lng' => 90.3795],
];

foreach ($testAddresses as $addr) {
    echo "\n{$addr['name']}:\n";
    foreach (['R001', 'R005'] as $rid) {
        $r = $restaurantIndex->getRestaurant($rid);
        $canDeliver = $zoneManager->canDeliver($rid, $addr['lat'], $addr['lng']);
        echo sprintf("  %s: %s\n", $r['name'], $canDeliver ? 'CAN DELIVER' : 'Out of range');
    }
}

// -----------------------------------------------------------------------------
// Part 3: Dynamic Delivery Fee Calculation
// -----------------------------------------------------------------------------

echo "\n\nPART 3: Dynamic Delivery Fee Calculation\n";
echo str_repeat("=", 50) . "\n\n";

class DeliveryFeeCalculator
{
    private H3 $h3;
    private int $resolution;
    private float $baseFee = 30;      // BDT base fee
    private float $perKmFee = 8;      // BDT per km
    private array $demandMultipliers = [];

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function setDemandMultiplier(float $lat, float $lng, float $multiplier): void
    {
        $cell = $this->h3->latLngToCell($lat, $lng, $this->resolution);
        $this->demandMultipliers[$cell] = $multiplier;
    }

    public function calculateFee(float $restaurantLat, float $restaurantLng, float $customerLat, float $customerLng): array
    {
        // Calculate distance
        $distanceKm = $this->h3->greatCircleDistanceKm(
            $restaurantLat, $restaurantLng,
            $customerLat, $customerLng
        );

        // Road distance estimate (1.4x direct in Dhaka due to traffic)
        $roadDistanceKm = $distanceKm * 1.4;

        // Get grid distance for zone calculation
        $restaurantCell = $this->h3->latLngToCell($restaurantLat, $restaurantLng, $this->resolution);
        $customerCell = $this->h3->latLngToCell($customerLat, $customerLng, $this->resolution);

        // Get demand multiplier at customer location (lunch/dinner rush)
        $demandMultiplier = $this->demandMultipliers[$customerCell] ?? 1.0;

        // Calculate fees
        $distanceFee = $roadDistanceKm * $this->perKmFee;
        $subtotal = $this->baseFee + $distanceFee;
        $finalFee = $subtotal * $demandMultiplier;

        return [
            'base_fee' => $this->baseFee,
            'distance_km' => $distanceKm,
            'road_distance_km' => $roadDistanceKm,
            'distance_fee' => $distanceFee,
            'demand_multiplier' => $demandMultiplier,
            'subtotal' => $subtotal,
            'final_fee' => round($finalFee),
        ];
    }
}

$feeCalculator = new DeliveryFeeCalculator($h3, $FEE_ZONE_RESOLUTION);

// Set high demand areas (lunch rush in Gulshan office district)
$feeCalculator->setDemandMultiplier(23.7925, 90.4078, 1.5);

echo "Delivery fee calculations:\n\n";

$restaurant = $restaurantIndex->getRestaurant('R001');
$testCustomers = [
    ['name' => 'Close customer (Gulshan)', 'lat' => 23.7930, 'lng' => 90.4082],
    ['name' => 'Medium distance (Banani)', 'lat' => 23.7937, 'lng' => 90.4066],
    ['name' => 'In demand zone (Gulshan office)', 'lat' => 23.7925, 'lng' => 90.4078],
];

foreach ($testCustomers as $customer) {
    $fee = $feeCalculator->calculateFee(
        $restaurant['lat'], $restaurant['lng'],
        $customer['lat'], $customer['lng']
    );

    echo sprintf("  %s:\n", $customer['name']);
    echo sprintf("    Distance: %.2f km (road: %.2f km)\n", $fee['distance_km'], $fee['road_distance_km']);
    echo sprintf("    Base fee: ৳%.0f + Distance: ৳%.0f\n", $fee['base_fee'], $fee['distance_fee']);
    if ($fee['demand_multiplier'] > 1) {
        echo sprintf("    Rush hour surge: %.1fx\n", $fee['demand_multiplier']);
    }
    echo sprintf("    Total delivery fee: ৳%.0f\n\n", $fee['final_fee']);
}

// -----------------------------------------------------------------------------
// Part 4: Delivery Time Estimation
// -----------------------------------------------------------------------------

echo "\nPART 4: Delivery Time Estimation\n";
echo str_repeat("=", 50) . "\n\n";

function estimateDeliveryTime(H3 $h3, array $restaurant, float $customerLat, float $customerLng): array
{
    $distanceKm = $h3->greatCircleDistanceKm(
        $restaurant['lat'], $restaurant['lng'],
        $customerLat, $customerLng
    );

    // Road distance
    $roadDistanceKm = $distanceKm * 1.4;

    // Travel time (average 12 km/h in Dhaka traffic)
    $travelMinutes = ($roadDistanceKm / 12) * 60;

    // Prep time from restaurant
    $prepTime = $restaurant['prep_time'];

    // Buffer time for pickup
    $pickupBuffer = 5;

    $totalMin = $prepTime + $pickupBuffer + $travelMinutes;
    $totalMax = $totalMin + 15; // +15 min buffer for Dhaka traffic

    return [
        'prep_time' => $prepTime,
        'travel_time' => round($travelMinutes),
        'estimated_min' => ceil($totalMin),
        'estimated_max' => ceil($totalMax),
        'distance_km' => round($roadDistanceKm, 1),
    ];
}

echo "--- Order Time Estimate ---\n\n";

$selectedRestaurant = $restaurantIndex->getRestaurant('R001');
$orderCustomer = ['lat' => 23.7937, 'lng' => 90.4066]; // Banani

$estimate = estimateDeliveryTime($h3, $selectedRestaurant, $orderCustomer['lat'], $orderCustomer['lng']);

echo "Restaurant: {$selectedRestaurant['name']}\n";
echo sprintf("Distance: %.1f km\n", $estimate['distance_km']);
echo "Breakdown:\n";
echo sprintf("  Food preparation: %d min\n", $estimate['prep_time']);
echo sprintf("  Delivery travel: %d min (Dhaka traffic)\n", $estimate['travel_time']);
echo sprintf("\nEstimated delivery: %d-%d minutes\n", $estimate['estimated_min'], $estimate['estimated_max']);

// -----------------------------------------------------------------------------
// Part 5: Order Batching Optimization
// -----------------------------------------------------------------------------

echo "\n\nPART 5: Order Batching Optimization\n";
echo str_repeat("=", 50) . "\n\n";

class OrderBatcher
{
    private H3 $h3;
    private int $resolution;
    private array $pendingOrders = [];

    public function __construct(H3 $h3, int $resolution)
    {
        $this->h3 = $h3;
        $this->resolution = $resolution;
    }

    public function addOrder(array $order): void
    {
        $cell = $this->h3->latLngToCell(
            $order['customer_lat'],
            $order['customer_lng'],
            $this->resolution
        );
        $order['cell'] = $cell;
        $this->pendingOrders[] = $order;
    }

    public function findBatchableOrders(string $orderId): array
    {
        $targetOrder = null;
        foreach ($this->pendingOrders as $order) {
            if ($order['id'] === $orderId) {
                $targetOrder = $order;
                break;
            }
        }

        if (!$targetOrder) {
            return [];
        }

        // Find orders in nearby cells (within k=2 distance)
        $nearbyCells = $this->h3->gridDisk($targetOrder['cell'], 2);
        $batchable = [];

        foreach ($this->pendingOrders as $order) {
            if ($order['id'] === $orderId) continue;
            if ($order['restaurant_id'] !== $targetOrder['restaurant_id']) continue;
            if (!in_array($order['cell'], $nearbyCells)) continue;

            $distance = $this->h3->greatCircleDistanceM(
                $targetOrder['customer_lat'], $targetOrder['customer_lng'],
                $order['customer_lat'], $order['customer_lng']
            );

            $batchable[] = array_merge($order, ['distance_to_target' => $distance]);
        }

        usort($batchable, fn($a, $b) => $a['distance_to_target'] <=> $b['distance_to_target']);
        return $batchable;
    }
}

$batcher = new OrderBatcher($h3, $RESTAURANT_RESOLUTION);

// Add pending orders (Gulshan area)
$pendingOrders = [
    ['id' => 'ORD001', 'restaurant_id' => 'R001', 'customer_lat' => 23.7925, 'customer_lng' => 90.4078],
    ['id' => 'ORD002', 'restaurant_id' => 'R001', 'customer_lat' => 23.7928, 'customer_lng' => 90.4080],
    ['id' => 'ORD003', 'restaurant_id' => 'R001', 'customer_lat' => 23.7937, 'customer_lng' => 90.4066], // Banani (Far)
    ['id' => 'ORD004', 'restaurant_id' => 'R002', 'customer_lat' => 23.7930, 'customer_lng' => 90.4082], // Different restaurant
    ['id' => 'ORD005', 'restaurant_id' => 'R001', 'customer_lat' => 23.7922, 'customer_lng' => 90.4076],
];

foreach ($pendingOrders as $order) {
    $batcher->addOrder($order);
}

echo "Finding batchable orders for ORD001 (Star Kabab):\n\n";

$batchable = $batcher->findBatchableOrders('ORD001');

if (count($batchable) > 0) {
    echo "Orders that can be batched:\n";
    foreach ($batchable as $order) {
        echo sprintf(
            "  %s - %.0f m away (same restaurant)\n",
            $order['id'],
            $order['distance_to_target']
        );
    }

    echo "\nRecommendation: Batch ORD001 with {$batchable[0]['id']} (closest)\n";
    echo "Estimated savings: ~5-8 min delivery time, reduced rider cost\n";
} else {
    echo "No batchable orders found\n";
}

echo "\n=== End of Food Delivery Platform Example ===\n";
