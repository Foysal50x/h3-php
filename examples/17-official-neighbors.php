<?php

/**
 * Official Uber H3 example: neighbors.c (ported to PHP)
 * https://github.com/uber/h3/blob/master/examples/neighbors.c
 *
 * Finds neighboring indexes (within grid distance 2) of an index.
 *
 * The C example manages the output buffer manually with maxGridDiskSize()
 * and calloc(); this binding's gridDisk() handles allocation internally and
 * returns only the valid (non-zero) neighbors.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

$indexed = $h3->stringToH3('8a2a1072b59ffff');
// Distance away from the origin to find:
$k = 2;

$neighboring = $h3->gridDisk($indexed, $k);

echo "Neighbors:\n";
foreach ($neighboring as $neighbor) {
    echo $h3->h3ToString($neighbor) . "\n";
}
