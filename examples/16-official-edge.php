<?php

/**
 * Official Uber H3 example: edge.c (ported to PHP)
 * https://github.com/uber/h3/blob/master/examples/edge.c
 *
 * Finds the directed edge between two indexes and prints its boundary
 * vertices.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

$origin = $h3->stringToH3('8a2a1072b59ffff');
$destination = $h3->stringToH3('8a2a1072b597fff'); // north of the origin

$edge = $h3->cellsToDirectedEdge($origin, $destination);
printf("The edge is %s\n", $h3->h3ToString($edge));

$boundary = $h3->directedEdgeToBoundary($edge);
foreach ($boundary as $v => $vertex) {
    printf("Edge vertex #%d: %f, %f\n", $v, $vertex['lat'], $vertex['lng']);
}

// Output:
// The edge is 16a2a1072b59ffff
// Edge vertex #0: 40.690059, -74.044152
// Edge vertex #1: 40.689908, -74.045062
