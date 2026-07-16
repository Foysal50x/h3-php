<?php

/**
 * Official Uber H3 example: distance.c (ported to PHP)
 * https://github.com/uber/h3/blob/master/examples/distance.c
 *
 * Calculates the distance in hexagons (grid distance) and in kilometers
 * (great-circle / haversine distance) between two hexagon indices.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

// mean Earth radius (km)
const R = 6371.0088;

/**
 * Great-circle distance between two points on a sphere using the haversine
 * formula. Parameters are latitude/longitude of the first and second point
 * in radians. Returns the distance in kilometers.
 *
 * @see https://en.wikipedia.org/wiki/Haversine_formula
 */
function haversineDistance(float $th1, float $ph1, float $th2, float $ph2): float
{
    $ph1 -= $ph2;

    $dz = sin($th1) - sin($th2);
    $dx = cos($ph1) * cos($th1) - cos($th2);
    $dy = sin($ph1) * cos($th1);

    return asin(sqrt($dx * $dx + $dy * $dy + $dz * $dz) / 2) * 2 * R;
}

$h3 = H3::getInstance();

// 1455 Market St @ resolution 15
$h3HQ1 = $h3->stringToH3('8f2830828052d25');
// 555 Market St @ resolution 15
$h3HQ2 = $h3->stringToH3('8f283082a30e623');

$geoHQ1 = $h3->cellToLatLng($h3HQ1);
$geoHQ2 = $h3->cellToLatLng($h3HQ2);

$distance = $h3->gridDistance($h3HQ1, $h3HQ2);

// This binding returns lat/lng in degrees; haversineDistance() expects radians.
printf(
    "origin: (%f, %f)\n" .
    "destination: (%f, %f)\n" .
    "grid distance: %d\n" .
    "distance in km: %fkm\n",
    $geoHQ1['lat'],
    $geoHQ1['lng'],
    $geoHQ2['lat'],
    $geoHQ2['lng'],
    $distance,
    haversineDistance(
        deg2rad($geoHQ1['lat']),
        deg2rad($geoHQ1['lng']),
        deg2rad($geoHQ2['lat']),
        deg2rad($geoHQ2['lng'])
    )
);

// Output:
// origin: (37.775236, -122.419755)
// destination: (37.789991, -122.402121)
// grid distance: 2340
// distance in km: 2.256853km
