<?php

/**
 * Official Uber H3 example: index.c (ported to PHP)
 * https://github.com/uber/h3/blob/master/examples/index.c
 *
 * Converts coordinates to an H3 index (hexagon), and then finds the
 * vertices and center coordinates of the index.
 *
 * Note: the C example calls latLngToCell() with radians and converts the
 * boundary/center back to degrees. This PHP binding works in degrees
 * directly, so no manual degs<->rads conversion is needed here.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

// Get the H3 index of some location and print it.
$lat = 40.689167;
$lng = -74.044444;
$resolution = 10;

$indexed = $h3->latLngToCell($lat, $lng, $resolution);
printf("The index is: %s\n", $h3->h3ToString($indexed));

// Get the vertices of the H3 index.
// Indexes can have a different number of vertices in some cases, which is
// why we iterate over exactly what cellToBoundary() returns.
$boundary = $h3->cellToBoundary($indexed);
foreach ($boundary as $v => $vertex) {
    printf("Boundary vertex #%d: %lf, %lf\n", $v, $vertex['lat'], $vertex['lng']);
}

// Get the center coordinates.
$center = $h3->cellToLatLng($indexed);
printf("Center coordinates: %lf, %lf\n", $center['lat'], $center['lng']);
