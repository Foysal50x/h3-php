<?php

declare(strict_types=1);

namespace Foysal50x\H3\Tests;

use Foysal50x\H3\H3;
use Foysal50x\H3\H3Exception;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for H3 PHP FFI bindings.
 */
class H3Test extends TestCase
{
    private H3 $h3;

    protected function setUp(): void
    {
        $this->h3 = new H3();
    }

    protected function tearDown(): void
    {
        H3::resetInstance();
    }

    // ===========================================
    // Indexing Tests
    // ===========================================

    public function testLatLngToCell(): void
    {
        // San Francisco coordinates
        $lat = 37.7749;
        $lng = -122.4194;
        $resolution = 9;

        $cell = $this->h3->latLngToCell($lat, $lng, $resolution);

        $this->assertIsInt($cell);
        $this->assertTrue($this->h3->isValidCell($cell));
        $this->assertEquals($resolution, $this->h3->getResolution($cell));
    }

    public function testCellToLatLng(): void
    {
        $lat = 37.7749;
        $lng = -122.4194;
        $resolution = 9;

        $cell = $this->h3->latLngToCell($lat, $lng, $resolution);
        $coords = $this->h3->cellToLatLng($cell);

        $this->assertArrayHasKey('lat', $coords);
        $this->assertArrayHasKey('lng', $coords);
        // Should be close to original coordinates (within cell)
        $this->assertEqualsWithDelta($lat, $coords['lat'], 0.01);
        $this->assertEqualsWithDelta($lng, $coords['lng'], 0.01);
    }

    public function testCellToBoundary(): void
    {
        $cell = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $boundary = $this->h3->cellToBoundary($cell);

        $this->assertIsArray($boundary);
        $this->assertGreaterThanOrEqual(5, count($boundary)); // Hexagon has 6 vertices, pentagon has 5
        $this->assertLessThanOrEqual(6, count($boundary));

        foreach ($boundary as $vertex) {
            $this->assertArrayHasKey('lat', $vertex);
            $this->assertArrayHasKey('lng', $vertex);
        }
    }

    // ===========================================
    // Inspection Tests
    // ===========================================

    public function testGetResolution(): void
    {
        for ($res = 0; $res <= 15; $res++) {
            $cell = $this->h3->latLngToCell(37.7749, -122.4194, $res);
            $this->assertEquals($res, $this->h3->getResolution($cell));
        }
    }

    public function testGetBaseCellNumber(): void
    {
        $cell = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $baseCellNumber = $this->h3->getBaseCellNumber($cell);

        $this->assertIsInt($baseCellNumber);
        $this->assertGreaterThanOrEqual(0, $baseCellNumber);
        $this->assertLessThan(122, $baseCellNumber);
    }

    public function testH3ToString(): void
    {
        $cell = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $str = $this->h3->h3ToString($cell);

        $this->assertIsString($str);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/i', $str);
    }

    public function testStringToH3(): void
    {
        $cell = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $str = $this->h3->h3ToString($cell);
        $convertedCell = $this->h3->stringToH3($str);

        $this->assertEquals($cell, $convertedCell);
    }

    public function testIsValidCell(): void
    {
        $cell = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $this->assertTrue($this->h3->isValidCell($cell));
        $this->assertFalse($this->h3->isValidCell(0));
    }

    public function testIsResClassIII(): void
    {
        // Resolution 1, 3, 5, etc. are Class III
        $cellClassIII = $this->h3->latLngToCell(37.7749, -122.4194, 1);
        $cellClassII = $this->h3->latLngToCell(37.7749, -122.4194, 2);

        $this->assertTrue($this->h3->isResClassIII($cellClassIII));
        $this->assertFalse($this->h3->isResClassIII($cellClassII));
    }

    public function testIsPentagon(): void
    {
        // Get a pentagon cell
        $pentagons = $this->h3->getPentagons(0);
        $this->assertTrue($this->h3->isPentagon($pentagons[0]));

        // Regular hexagon should not be pentagon
        $cell = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $this->assertFalse($this->h3->isPentagon($cell));
    }

    // ===========================================
    // Traversal Tests
    // ===========================================

    public function testGridDisk(): void
    {
        $origin = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $k = 1;

        $cells = $this->h3->gridDisk($origin, $k);

        $this->assertIsArray($cells);
        $this->assertGreaterThan(0, count($cells));
        $this->assertContains($origin, $cells); // Origin should be in the disk

        foreach ($cells as $cell) {
            $this->assertTrue($this->h3->isValidCell($cell));
        }
    }

    public function testGridDiskDistances(): void
    {
        $origin = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $k = 2;

        $result = $this->h3->gridDiskDistances($origin, $k);

        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($result));

        $originFound = false;
        foreach ($result as $item) {
            $this->assertArrayHasKey('cell', $item);
            $this->assertArrayHasKey('distance', $item);
            $this->assertGreaterThanOrEqual(0, $item['distance']);
            $this->assertLessThanOrEqual($k, $item['distance']);

            if ($item['cell'] === $origin) {
                $originFound = true;
                $this->assertEquals(0, $item['distance']);
            }
        }

        $this->assertTrue($originFound);
    }

    public function testGridRing(): void
    {
        $origin = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $k = 1;

        $cells = $this->h3->gridRing($origin, $k);

        $this->assertIsArray($cells);
        $this->assertGreaterThan(0, count($cells));
        $this->assertNotContains($origin, $cells); // Origin should NOT be in the ring

        foreach ($cells as $cell) {
            $this->assertTrue($this->h3->isValidCell($cell));
        }
    }

    public function testGridDistance(): void
    {
        $origin = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $cells = $this->h3->gridDisk($origin, 3);

        foreach ($cells as $cell) {
            $distance = $this->h3->gridDistance($origin, $cell);
            $this->assertGreaterThanOrEqual(0, $distance);
            $this->assertLessThanOrEqual(3, $distance);
        }
    }

    public function testGridPathCells(): void
    {
        $start = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $end = $this->h3->latLngToCell(37.7849, -122.4094, 9);

        $path = $this->h3->gridPathCells($start, $end);

        $this->assertIsArray($path);
        $this->assertGreaterThan(0, count($path));
        $this->assertEquals($start, $path[0]);
        $this->assertEquals($end, $path[count($path) - 1]);

        foreach ($path as $cell) {
            $this->assertTrue($this->h3->isValidCell($cell));
        }
    }

    // ===========================================
    // Hierarchy Tests
    // ===========================================

    public function testCellToParent(): void
    {
        $cell = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $parent = $this->h3->cellToParent($cell, 5);

        $this->assertTrue($this->h3->isValidCell($parent));
        $this->assertEquals(5, $this->h3->getResolution($parent));
    }

    public function testCellToChildren(): void
    {
        $cell = $this->h3->latLngToCell(37.7749, -122.4194, 5);
        $children = $this->h3->cellToChildren($cell, 6);

        $this->assertIsArray($children);
        $this->assertCount(7, $children); // Hexagon has 7 children

        foreach ($children as $child) {
            $this->assertTrue($this->h3->isValidCell($child));
            $this->assertEquals(6, $this->h3->getResolution($child));
        }
    }

    public function testCellToCenterChild(): void
    {
        $cell = $this->h3->latLngToCell(37.7749, -122.4194, 5);
        $centerChild = $this->h3->cellToCenterChild($cell, 7);

        $this->assertTrue($this->h3->isValidCell($centerChild));
        $this->assertEquals(7, $this->h3->getResolution($centerChild));
    }

    public function testCompactAndUncompactCells(): void
    {
        $origin = $this->h3->latLngToCell(37.7749, -122.4194, 5);
        $children = $this->h3->cellToChildren($origin, 6);

        // Compacting all children should give us the parent
        $compacted = $this->h3->compactCells($children);
        $this->assertCount(1, $compacted);
        $this->assertEquals($origin, $compacted[0]);

        // Uncompacting should give us back the children
        $uncompacted = $this->h3->uncompactCells($compacted, 6);
        $this->assertCount(7, $uncompacted);
    }

    // ===========================================
    // Directed Edge Tests
    // ===========================================

    public function testAreNeighborCells(): void
    {
        $origin = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $disk = $this->h3->gridDisk($origin, 1);

        foreach ($disk as $cell) {
            if ($cell !== $origin) {
                $this->assertTrue($this->h3->areNeighborCells($origin, $cell));
            }
        }
    }

    public function testCellsToDirectedEdge(): void
    {
        $origin = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $ring = $this->h3->gridRing($origin, 1);
        $neighbor = $ring[0];

        $edge = $this->h3->cellsToDirectedEdge($origin, $neighbor);

        $this->assertTrue($this->h3->isValidDirectedEdge($edge));
    }

    public function testDirectedEdgeToCells(): void
    {
        $origin = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $ring = $this->h3->gridRing($origin, 1);
        $neighbor = $ring[0];

        $edge = $this->h3->cellsToDirectedEdge($origin, $neighbor);
        $cells = $this->h3->directedEdgeToCells($edge);

        $this->assertEquals($origin, $cells['origin']);
        $this->assertEquals($neighbor, $cells['destination']);
    }

    public function testOriginToDirectedEdges(): void
    {
        $origin = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $edges = $this->h3->originToDirectedEdges($origin);

        $this->assertIsArray($edges);
        $this->assertGreaterThanOrEqual(5, count($edges)); // 5 for pentagon, 6 for hexagon
        $this->assertLessThanOrEqual(6, count($edges));

        foreach ($edges as $edge) {
            $this->assertTrue($this->h3->isValidDirectedEdge($edge));
            $this->assertEquals($origin, $this->h3->getDirectedEdgeOrigin($edge));
        }
    }

    public function testDirectedEdgeToBoundary(): void
    {
        $origin = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $edges = $this->h3->originToDirectedEdges($origin);

        $boundary = $this->h3->directedEdgeToBoundary($edges[0]);

        $this->assertIsArray($boundary);
        $this->assertEquals(2, count($boundary)); // Edge has 2 vertices

        foreach ($boundary as $vertex) {
            $this->assertArrayHasKey('lat', $vertex);
            $this->assertArrayHasKey('lng', $vertex);
        }
    }

    // ===========================================
    // Vertex Tests
    // ===========================================

    public function testCellToVertexes(): void
    {
        $cell = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $vertices = $this->h3->cellToVertexes($cell);

        $this->assertIsArray($vertices);
        $this->assertGreaterThanOrEqual(5, count($vertices));
        $this->assertLessThanOrEqual(6, count($vertices));

        foreach ($vertices as $vertex) {
            $this->assertTrue($this->h3->isValidVertex($vertex));
        }
    }

    public function testVertexToLatLng(): void
    {
        $cell = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $vertices = $this->h3->cellToVertexes($cell);

        $coords = $this->h3->vertexToLatLng($vertices[0]);

        $this->assertArrayHasKey('lat', $coords);
        $this->assertArrayHasKey('lng', $coords);
        $this->assertIsFloat($coords['lat']);
        $this->assertIsFloat($coords['lng']);
    }

    // ===========================================
    // Miscellaneous Tests
    // ===========================================

    public function testDegsToRadsAndBack(): void
    {
        $degrees = 45.0;
        $radians = $this->h3->degsToRads($degrees);
        $back = $this->h3->radsToDegs($radians);

        $this->assertEqualsWithDelta($degrees, $back, 0.0001);
    }

    public function testGetHexagonAreaAvgKm2(): void
    {
        $area = $this->h3->getHexagonAreaAvgKm2(0);
        $this->assertGreaterThan(0, $area);

        // Higher resolution should have smaller area
        $areaHighRes = $this->h3->getHexagonAreaAvgKm2(15);
        $this->assertLessThan($area, $areaHighRes);
    }

    public function testGetHexagonAreaAvgM2(): void
    {
        $areaKm = $this->h3->getHexagonAreaAvgKm2(9);
        $areaM = $this->h3->getHexagonAreaAvgM2(9);

        // m² should be km² * 1,000,000
        $this->assertEqualsWithDelta($areaKm * 1_000_000, $areaM, $areaM * 0.0001);
    }

    public function testCellAreaKm2(): void
    {
        $cell = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $area = $this->h3->cellAreaKm2($cell);

        $this->assertGreaterThan(0, $area);
    }

    public function testGetHexagonEdgeLengthAvgKm(): void
    {
        $length = $this->h3->getHexagonEdgeLengthAvgKm(0);
        $this->assertGreaterThan(0, $length);

        // Higher resolution should have shorter edges
        $lengthHighRes = $this->h3->getHexagonEdgeLengthAvgKm(15);
        $this->assertLessThan($length, $lengthHighRes);
    }

    public function testEdgeLengthKm(): void
    {
        $origin = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $edges = $this->h3->originToDirectedEdges($origin);

        $length = $this->h3->edgeLengthKm($edges[0]);
        $this->assertGreaterThan(0, $length);
    }

    public function testGetNumCells(): void
    {
        $numCells = $this->h3->getNumCells(0);
        $this->assertEquals(122, $numCells);

        // Higher resolution has more cells
        $numCellsHighRes = $this->h3->getNumCells(1);
        $this->assertGreaterThan($numCells, $numCellsHighRes);
    }

    public function testGetRes0Cells(): void
    {
        $cells = $this->h3->getRes0Cells();

        $this->assertCount(122, $cells);

        foreach ($cells as $cell) {
            $this->assertTrue($this->h3->isValidCell($cell));
            $this->assertEquals(0, $this->h3->getResolution($cell));
        }
    }

    public function testGetPentagons(): void
    {
        $pentagons = $this->h3->getPentagons(0);

        $this->assertCount(12, $pentagons);

        foreach ($pentagons as $pentagon) {
            $this->assertTrue($this->h3->isValidCell($pentagon));
            $this->assertTrue($this->h3->isPentagon($pentagon));
        }
    }

    public function testGreatCircleDistanceKm(): void
    {
        // New York to Los Angeles
        $nyLat = 40.7128;
        $nyLng = -74.0060;
        $laLat = 34.0522;
        $laLng = -118.2437;

        $distance = $this->h3->greatCircleDistanceKm($nyLat, $nyLng, $laLat, $laLng);

        // Should be approximately 3940 km
        $this->assertEqualsWithDelta(3940, $distance, 50);
    }

    public function testGreatCircleDistanceM(): void
    {
        $distanceKm = $this->h3->greatCircleDistanceKm(40.7128, -74.0060, 34.0522, -118.2437);
        $distanceM = $this->h3->greatCircleDistanceM(40.7128, -74.0060, 34.0522, -118.2437);

        $this->assertEqualsWithDelta($distanceKm * 1000, $distanceM, 1);
    }

    public function testCellToLocalIj(): void
    {
        $origin = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        $ij = $this->h3->cellToLocalIj($origin, $origin);

        $this->assertArrayHasKey('i', $ij);
        $this->assertArrayHasKey('j', $ij);
        $this->assertIsInt($ij['i']);
        $this->assertIsInt($ij['j']);
    }

    public function testLocalIjToCell(): void
    {
        $origin = $this->h3->latLngToCell(37.7749, -122.4194, 9);
        // Get the IJ coordinates for the origin cell
        $ij = $this->h3->cellToLocalIj($origin, $origin);
        // Converting back should give us the same cell
        $cell = $this->h3->localIjToCell($origin, $ij['i'], $ij['j']);

        $this->assertEquals($origin, $cell);
    }

    public function testGetIcosahedronFaces(): void
    {
        $cell = $this->h3->latLngToCell(37.7749, -122.4194, 0);
        $faces = $this->h3->getIcosahedronFaces($cell);

        $this->assertIsArray($faces);
        $this->assertGreaterThan(0, count($faces));

        foreach ($faces as $face) {
            $this->assertGreaterThanOrEqual(0, $face);
            $this->assertLessThan(20, $face); // Icosahedron has 20 faces
        }
    }

    // ===========================================
    // Singleton Tests
    // ===========================================

    public function testGetInstance(): void
    {
        $instance1 = H3::getInstance();
        $instance2 = H3::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    // ===========================================
    // Exception Tests
    // ===========================================

    public function testInvalidResolutionThrowsException(): void
    {
        $this->expectException(H3Exception::class);
        $this->h3->latLngToCell(37.7749, -122.4194, 16);
    }

    public function testNegativeResolutionThrowsException(): void
    {
        $this->expectException(H3Exception::class);
        $this->h3->latLngToCell(37.7749, -122.4194, -1);
    }
}
