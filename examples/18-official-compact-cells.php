<?php

/**
 * Official Uber H3 example: compactCells.c (ported to PHP)
 * https://github.com/uber/h3/blob/master/examples/compactCells.c
 *
 * Compacts a set of indexes, and then uncompacts back to the original set.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Foysal50x\H3\H3;

$h3 = H3::getInstance();

$input = array_map([$h3, 'stringToH3'], [
    // All with the same parent index
    '8a2a1072b587fff', '8a2a1072b5b7fff', '8a2a1072b597fff',
    '8a2a1072b59ffff', '8a2a1072b58ffff', '8a2a1072b5affff',
    '8a2a1072b5a7fff',
    // These don't have the same parent index as above.
    '8a2a1070c96ffff', '8a2a1072b4b7fff', '8a2a1072b4a7fff',
]);

printf("Starting with %d indexes.\n", count($input));

$compacted = $h3->compactCells($input);

echo "Compacted:\n";
foreach ($compacted as $cell) {
    echo $h3->h3ToString($cell) . "\n";
}
printf("Compacted to %d indexes.\n", count($compacted));

$uncompactRes = 10;
$uncompacted = $h3->uncompactCells($compacted, $uncompactRes);

echo "Uncompacted:\n";
foreach ($uncompacted as $cell) {
    echo $h3->h3ToString($cell) . "\n";
}
printf("Uncompacted to %d indexes.\n", count($uncompacted));
