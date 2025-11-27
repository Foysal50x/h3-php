<?php

declare(strict_types=1);

namespace Foysal50x\H3;

use FFI;

/**
 * PHP FFI bindings for Uber's H3 hexagonal hierarchical geospatial indexing system.
 *
 * H3 is a geospatial indexing system using a hexagonal grid that can be subdivided
 * into finer and finer hexagonal grids.
 *
 * @see https://h3geo.org/
 * @see https://github.com/uber/h3
 */
class H3
{
    private FFI $ffi;

    private static ?H3 $instance = null;

    /**
     * H3 C library version that this package is compatible with.
     * This version is used when building the bundled H3 library.
     */
    public const H3_VERSION = '4.4.1';

    /**
     * H3 C library header definitions.
     * Based on H3 v4.1.0
     */
    private const H3_HEADER = <<<'HEADER'
    typedef uint64_t H3Index;
    typedef uint32_t H3Error;

    typedef struct {
        double lat;
        double lng;
    } LatLng;

    typedef struct {
        int numVerts;
        LatLng verts[10];
    } CellBoundary;

    typedef struct {
        int i;
        int j;
    } CoordIJ;

    typedef struct {
        LatLng *verts;
        int numVerts;
    } GeoLoop;

    typedef struct {
        GeoLoop geoloop;
        int numHoles;
        GeoLoop *holes;
    } GeoPolygon;

    // Indexing functions
    H3Error latLngToCell(const LatLng *g, int res, H3Index *out);
    H3Error cellToLatLng(H3Index cell, LatLng *g);
    H3Error cellToBoundary(H3Index cell, CellBoundary *bndry);

    // Inspection functions
    int getResolution(H3Index h);
    int getBaseCellNumber(H3Index h);
    H3Error stringToH3(const char *str, H3Index *out);
    H3Error h3ToString(H3Index h, char *str, size_t sz);
    int isValidCell(H3Index h);
    int isResClassIII(H3Index h);
    int isPentagon(H3Index h);
    H3Error getIcosahedronFaces(H3Index h, int *out);
    H3Error maxFaceCount(H3Index h3, int *out);

    // Traversal functions
    H3Error gridDisk(H3Index origin, int k, H3Index *out);
    H3Error maxGridDiskSize(int k, int64_t *out);
    H3Error gridDiskDistances(H3Index origin, int k, H3Index *out, int *distances);
    H3Error gridRing(H3Index origin, int k, H3Index *out);
    H3Error gridRingUnsafe(H3Index origin, int k, H3Index *out);
    H3Error maxGridRingSize(int k, int64_t *out);
    H3Error gridPathCells(H3Index start, H3Index end, H3Index *out);
    H3Error gridPathCellsSize(H3Index start, H3Index end, int64_t *size);
    H3Error gridDistance(H3Index origin, H3Index h3, int64_t *distance);
    H3Error cellToLocalIj(H3Index origin, H3Index h3, uint32_t mode, CoordIJ *out);
    H3Error localIjToCell(H3Index origin, const CoordIJ *ij, uint32_t mode, H3Index *out);

    // Hierarchy functions
    H3Error cellToParent(H3Index cell, int parentRes, H3Index *parent);
    H3Error cellToChildren(H3Index cell, int childRes, H3Index *children);
    H3Error cellToChildrenSize(H3Index cell, int childRes, int64_t *out);
    H3Error cellToCenterChild(H3Index cell, int childRes, H3Index *child);
    H3Error cellToChildPos(H3Index child, int parentRes, int64_t *out);
    H3Error childPosToCell(int64_t childPos, H3Index parent, int childRes, H3Index *child);
    H3Error compactCells(const H3Index *cellSet, H3Index *compactedSet, const int64_t numCells);
    H3Error uncompactCells(const H3Index *compactedSet, const int64_t numCells, H3Index *cellSet, const int64_t maxCells, const int res);
    H3Error uncompactCellsSize(const H3Index *compactedSet, const int64_t numCompacted, const int res, int64_t *out);

    // Directed edge functions
    H3Error areNeighborCells(H3Index origin, H3Index destination, int *out);
    H3Error cellsToDirectedEdge(H3Index origin, H3Index destination, H3Index *out);
    int isValidDirectedEdge(H3Index edge);
    H3Error getDirectedEdgeOrigin(H3Index edge, H3Index *out);
    H3Error getDirectedEdgeDestination(H3Index edge, H3Index *out);
    H3Error directedEdgeToCells(H3Index edge, H3Index *originDestination);
    H3Error originToDirectedEdges(H3Index origin, H3Index *edges);
    H3Error directedEdgeToBoundary(H3Index edge, CellBoundary *gb);

    // Vertex functions
    H3Error cellToVertex(H3Index origin, int vertexNum, H3Index *out);
    H3Error cellToVertexes(H3Index origin, H3Index *out);
    H3Error vertexToLatLng(H3Index vertex, LatLng *point);
    int isValidVertex(H3Index vertex);

    // Miscellaneous functions
    double degsToRads(double degrees);
    double radsToDegs(double radians);
    H3Error getHexagonAreaAvgKm2(int res, double *out);
    H3Error getHexagonAreaAvgM2(int res, double *out);
    H3Error cellAreaRads2(H3Index h, double *out);
    H3Error cellAreaKm2(H3Index h, double *out);
    H3Error cellAreaM2(H3Index h, double *out);
    H3Error getHexagonEdgeLengthAvgKm(int res, double *out);
    H3Error getHexagonEdgeLengthAvgM(int res, double *out);
    H3Error edgeLengthKm(H3Index edge, double *length);
    H3Error edgeLengthM(H3Index edge, double *length);
    H3Error edgeLengthRads(H3Index edge, double *length);
    H3Error getNumCells(int res, int64_t *out);
    H3Error getRes0Cells(H3Index *out);
    int res0CellCount(void);
    H3Error getPentagons(int res, H3Index *out);
    int pentagonCount(void);
    double greatCircleDistanceKm(const LatLng *a, const LatLng *b);
    double greatCircleDistanceM(const LatLng *a, const LatLng *b);
    double greatCircleDistanceRads(const LatLng *a, const LatLng *b);
    HEADER;

    /**
     * H3 Error codes
     */
    public const E_SUCCESS = 0;
    public const E_FAILED = 1;
    public const E_DOMAIN = 2;
    public const E_LATLNG_DOMAIN = 3;
    public const E_RES_DOMAIN = 4;
    public const E_CELL_INVALID = 5;
    public const E_DIR_EDGE_INVALID = 6;
    public const E_UNDIR_EDGE_INVALID = 7;
    public const E_VERTEX_INVALID = 8;
    public const E_PENTAGON = 9;
    public const E_DUPLICATE_INPUT = 10;
    public const E_NOT_NEIGHBORS = 11;
    public const E_RES_MISMATCH = 12;
    public const E_MEMORY_ALLOC = 13;
    public const E_MEMORY_BOUNDS = 14;
    public const E_OPTION_INVALID = 15;

    /**
     * Maximum H3 resolution
     */
    public const MAX_RESOLUTION = 15;

    /**
     * Number of resolution 0 cells
     */
    public const RES0_CELL_COUNT = 122;

    /**
     * Number of pentagon cells per resolution
     */
    public const PENTAGON_COUNT = 12;

    /**
     * Create a new H3 instance.
     *
     * @param string|null $libraryPath Path to the H3 shared library. If null, will attempt to auto-detect.
     * @throws H3Exception If the FFI extension is not available or H3 library cannot be loaded.
     */
    public function __construct(?string $libraryPath = null)
    {
        $this->ensureFfiAvailable();

        $libraryPath = $libraryPath ?? $this->detectLibraryPath();

        if ($libraryPath === null) {
            throw new H3Exception(
                'Could not find H3 library. Please install H3 or provide the library path. ' .
                'On macOS: brew install h3, on Linux: apt install libh3-dev or build from source.',
                self::E_FAILED
            );
        }

        $this->ffi = FFI::cdef(self::H3_HEADER, $libraryPath);
    }

    /**
     * Ensure the FFI extension is available and enabled.
     *
     * @throws H3Exception If FFI extension is not loaded or not enabled.
     */
    private function ensureFfiAvailable(): void
    {
        if (!extension_loaded('ffi')) {
            throw new H3Exception(
                'The FFI extension is not loaded. Please enable it in your php.ini by adding "extension=ffi".',
                self::E_FAILED
            );
        }

        $ffiEnable = ini_get('ffi.enable');

        // ffi.enable can be: "1", "true", "preload", "0", "false", or "" (empty)
        // We need it to be "1", "true", or "preload" (preload allows FFI in preloaded scripts only)
        if ($ffiEnable === false || $ffiEnable === '' || $ffiEnable === '0') {
            throw new H3Exception(
                'FFI is not enabled. Please set "ffi.enable=true" or "ffi.enable=preload" in your php.ini.',
                self::E_FAILED
            );
        }
    }

    /**
     * Get the singleton instance of H3.
     *
     * @param string|null $libraryPath Path to the H3 shared library.
     * @return self
     */
    public static function getInstance(?string $libraryPath = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($libraryPath);
        }

        return self::$instance;
    }

    /**
     * Reset the singleton instance.
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Detect the H3 library path based on the operating system.
     *
     * The detection order is:
     * 1. Bundled library in the package's bin/ directory (recommended for version consistency)
     * 2. System-wide installed library paths
     *
     * @return string|null The library path or null if not found.
     */
    private function detectLibraryPath(): ?string
    {
        // First, try bundled library in the package's bin/ directory
        $bundledPath = $this->getBundledLibraryPath();
        if ($bundledPath !== null && file_exists($bundledPath)) {
            return $bundledPath;
        }

        // Fallback to system-wide paths
        $paths = $this->getSystemLibraryPaths();

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Get the path to the bundled H3 library in the package's bin/ directory.
     *
     * Libraries are organized by platform and architecture:
     * - bin/darwin-arm64/libh3.dylib (macOS Apple Silicon)
     * - bin/darwin-x64/libh3.dylib (macOS Intel)
     * - bin/linux-x64/libh3.so (Linux x64)
     * - bin/linux-arm64/libh3.so (Linux ARM64)
     * - bin/windows-x64/h3.dll (Windows x64)
     *
     * @return string|null The bundled library path or null if not applicable.
     */
    private function getBundledLibraryPath(): ?string
    {
        $packageRoot = dirname(__DIR__);
        $binDir = $packageRoot . DIRECTORY_SEPARATOR . 'bin';
        $platformDir = $this->getPlatformDirectory();

        if ($platformDir === null) {
            return null;
        }

        $libName = $this->getLibraryFileName();
        if ($libName === null) {
            return null;
        }

        return $binDir . DIRECTORY_SEPARATOR . $platformDir . DIRECTORY_SEPARATOR . $libName;
    }

    /**
     * Get the platform-specific directory name.
     *
     * @return string|null The platform directory name or null if unknown.
     */
    private function getPlatformDirectory(): ?string
    {
        $arch = php_uname('m');

        if (PHP_OS_FAMILY === 'Darwin') {
            return $arch === 'arm64' ? 'darwin-arm64' : 'darwin-x64';
        } elseif (PHP_OS_FAMILY === 'Linux') {
            return in_array($arch, ['aarch64', 'arm64']) ? 'linux-arm64' : 'linux-x64';
        } elseif (PHP_OS_FAMILY === 'Windows') {
            return 'windows-x64';
        }

        return null;
    }

    /**
     * Get the library file name for the current OS.
     *
     * @return string|null The library file name or null if unknown OS.
     */
    private function getLibraryFileName(): ?string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => 'libh3.dylib',
            'Linux' => 'libh3.so',
            'Windows' => 'h3.dll',
            default => null,
        };
    }

    /**
     * Get system-wide library paths for the H3 library.
     *
     * @return string[] Array of possible system library paths.
     */
    private function getSystemLibraryPaths(): array
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            // macOS - Homebrew paths
            return [
                '/opt/homebrew/lib/libh3.dylib',           // Apple Silicon
                '/usr/local/lib/libh3.dylib',              // Intel
                '/opt/homebrew/Cellar/h3/4.1.0/lib/libh3.dylib',
            ];
        } elseif (PHP_OS_FAMILY === 'Linux') {
            // Linux paths
            return [
                '/usr/lib/libh3.so',
                '/usr/lib/x86_64-linux-gnu/libh3.so',
                '/usr/local/lib/libh3.so',
                '/usr/lib64/libh3.so',
            ];
        } elseif (PHP_OS_FAMILY === 'Windows') {
            // Windows paths
            return [
                'C:\\Program Files\\H3\\bin\\h3.dll',
                'h3.dll',
            ];
        }

        return [];
    }

    /**
     * Convert latitude/longitude to H3 cell index.
     *
     * @param float $lat Latitude in degrees.
     * @param float $lng Longitude in degrees.
     * @param int $resolution Resolution (0-15).
     * @return int H3 cell index.
     * @throws H3Exception If the operation fails.
     */
    public function latLngToCell(float $lat, float $lng, int $resolution): int
    {
        $this->validateResolution($resolution);

        $latLng = $this->ffi->new('LatLng');
        $latLng->lat = deg2rad($lat);
        $latLng->lng = deg2rad($lng);

        $out = $this->ffi->new('H3Index');
        $error = $this->ffi->latLngToCell(FFI::addr($latLng), $resolution, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to convert lat/lng to cell", $error);
        }

        return $out->cdata;
    }

    /**
     * Convert H3 cell index to latitude/longitude (center of the cell).
     *
     * @param int $cell H3 cell index.
     * @return array{lat: float, lng: float} Latitude and longitude in degrees.
     * @throws H3Exception If the operation fails.
     */
    public function cellToLatLng(int $cell): array
    {
        $latLng = $this->ffi->new('LatLng');
        $error = $this->ffi->cellToLatLng($cell, FFI::addr($latLng));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to convert cell to lat/lng", $error);
        }

        return [
            'lat' => rad2deg($latLng->lat),
            'lng' => rad2deg($latLng->lng),
        ];
    }

    /**
     * Get the boundary vertices of an H3 cell.
     *
     * @param int $cell H3 cell index.
     * @return array<array{lat: float, lng: float}> Array of lat/lng coordinates.
     * @throws H3Exception If the operation fails.
     */
    public function cellToBoundary(int $cell): array
    {
        $boundary = $this->ffi->new('CellBoundary');
        $error = $this->ffi->cellToBoundary($cell, FFI::addr($boundary));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get cell boundary", $error);
        }

        $vertices = [];
        for ($i = 0; $i < $boundary->numVerts; $i++) {
            $vertices[] = [
                'lat' => rad2deg($boundary->verts[$i]->lat),
                'lng' => rad2deg($boundary->verts[$i]->lng),
            ];
        }

        return $vertices;
    }

    /**
     * Get the resolution of an H3 cell.
     *
     * @param int $cell H3 cell index.
     * @return int Resolution (0-15).
     */
    public function getResolution(int $cell): int
    {
        return $this->ffi->getResolution($cell);
    }

    /**
     * Get the base cell number of an H3 index.
     *
     * @param int $cell H3 cell index.
     * @return int Base cell number (0-121).
     */
    public function getBaseCellNumber(int $cell): int
    {
        return $this->ffi->getBaseCellNumber($cell);
    }

    /**
     * Convert H3 index to string representation.
     *
     * @param int $cell H3 cell index.
     * @return string Hexadecimal string representation.
     * @throws H3Exception If the operation fails.
     */
    public function h3ToString(int $cell): string
    {
        $str = $this->ffi->new('char[17]');
        $error = $this->ffi->h3ToString($cell, $str, 17);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to convert H3 index to string", $error);
        }

        return FFI::string($str);
    }

    /**
     * Convert string representation to H3 index.
     *
     * @param string $str Hexadecimal string representation.
     * @return int H3 cell index.
     * @throws H3Exception If the operation fails.
     */
    public function stringToH3(string $str): int
    {
        $out = $this->ffi->new('H3Index');
        $error = $this->ffi->stringToH3($str, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to convert string to H3 index", $error);
        }

        return $out->cdata;
    }

    /**
     * Check if an H3 index is a valid cell.
     *
     * @param int $cell H3 cell index.
     * @return bool True if valid.
     */
    public function isValidCell(int $cell): bool
    {
        return $this->ffi->isValidCell($cell) !== 0;
    }

    /**
     * Check if an H3 cell has Class III orientation.
     *
     * @param int $cell H3 cell index.
     * @return bool True if Class III.
     */
    public function isResClassIII(int $cell): bool
    {
        return $this->ffi->isResClassIII($cell) !== 0;
    }

    /**
     * Check if an H3 cell is a pentagon.
     *
     * @param int $cell H3 cell index.
     * @return bool True if pentagon.
     */
    public function isPentagon(int $cell): bool
    {
        return $this->ffi->isPentagon($cell) !== 0;
    }

    /**
     * Get all cells within k distance of the origin cell (filled disk).
     *
     * @param int $origin Origin H3 cell index.
     * @param int $k Grid distance.
     * @return int[] Array of H3 cell indices.
     * @throws H3Exception If the operation fails.
     */
    public function gridDisk(int $origin, int $k): array
    {
        $size = $this->ffi->new('int64_t');
        $error = $this->ffi->maxGridDiskSize($k, FFI::addr($size));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get grid disk size", $error);
        }

        $maxSize = $size->cdata;
        $out = $this->ffi->new("H3Index[$maxSize]");
        $error = $this->ffi->gridDisk($origin, $k, $out);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get grid disk", $error);
        }

        $cells = [];
        for ($i = 0; $i < $maxSize; $i++) {
            if ($out[$i] !== 0) {
                $cells[] = $out[$i];
            }
        }

        return $cells;
    }

    /**
     * Get all cells within k distance with their distances from origin.
     *
     * @param int $origin Origin H3 cell index.
     * @param int $k Grid distance.
     * @return array<array{cell: int, distance: int}> Array of cells with distances.
     * @throws H3Exception If the operation fails.
     */
    public function gridDiskDistances(int $origin, int $k): array
    {
        $size = $this->ffi->new('int64_t');
        $error = $this->ffi->maxGridDiskSize($k, FFI::addr($size));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get grid disk size", $error);
        }

        $maxSize = $size->cdata;
        $out = $this->ffi->new("H3Index[$maxSize]");
        $distances = $this->ffi->new("int[$maxSize]");
        $error = $this->ffi->gridDiskDistances($origin, $k, $out, $distances);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get grid disk distances", $error);
        }

        $result = [];
        for ($i = 0; $i < $maxSize; $i++) {
            if ($out[$i] !== 0) {
                $result[] = [
                    'cell' => $out[$i],
                    'distance' => $distances[$i],
                ];
            }
        }

        return $result;
    }

    /**
     * Get all cells in a hollow ring at exactly k distance from origin.
     *
     * @param int $origin Origin H3 cell index.
     * @param int $k Grid distance.
     * @return int[] Array of H3 cell indices.
     * @throws H3Exception If the operation fails.
     */
    public function gridRing(int $origin, int $k): array
    {
        $size = $this->ffi->new('int64_t');
        $error = $this->ffi->maxGridRingSize($k, FFI::addr($size));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get grid ring size", $error);
        }

        $maxSize = $size->cdata;
        $out = $this->ffi->new("H3Index[$maxSize]");
        $error = $this->ffi->gridRing($origin, $k, $out);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get grid ring", $error);
        }

        $cells = [];
        for ($i = 0; $i < $maxSize; $i++) {
            if ($out[$i] !== 0) {
                $cells[] = $out[$i];
            }
        }

        return $cells;
    }

    /**
     * Get the grid distance between two cells.
     *
     * @param int $origin Origin H3 cell index.
     * @param int $destination Destination H3 cell index.
     * @return int Grid distance.
     * @throws H3Exception If the operation fails.
     */
    public function gridDistance(int $origin, int $destination): int
    {
        $distance = $this->ffi->new('int64_t');
        $error = $this->ffi->gridDistance($origin, $destination, FFI::addr($distance));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get grid distance", $error);
        }

        return (int) $distance->cdata;
    }

    /**
     * Get the path of cells from start to end.
     *
     * @param int $start Start H3 cell index.
     * @param int $end End H3 cell index.
     * @return int[] Array of H3 cell indices along the path.
     * @throws H3Exception If the operation fails.
     */
    public function gridPathCells(int $start, int $end): array
    {
        $size = $this->ffi->new('int64_t');
        $error = $this->ffi->gridPathCellsSize($start, $end, FFI::addr($size));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get grid path size", $error);
        }

        $pathSize = $size->cdata;
        $out = $this->ffi->new("H3Index[$pathSize]");
        $error = $this->ffi->gridPathCells($start, $end, $out);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get grid path cells", $error);
        }

        $cells = [];
        for ($i = 0; $i < $pathSize; $i++) {
            $cells[] = $out[$i];
        }

        return $cells;
    }

    /**
     * Get the parent cell at a coarser resolution.
     *
     * @param int $cell H3 cell index.
     * @param int $parentRes Parent resolution (must be less than cell's resolution).
     * @return int Parent H3 cell index.
     * @throws H3Exception If the operation fails.
     */
    public function cellToParent(int $cell, int $parentRes): int
    {
        $this->validateResolution($parentRes);

        $parent = $this->ffi->new('H3Index');
        $error = $this->ffi->cellToParent($cell, $parentRes, FFI::addr($parent));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get parent cell", $error);
        }

        return $parent->cdata;
    }

    /**
     * Get all children cells at a finer resolution.
     *
     * @param int $cell H3 cell index.
     * @param int $childRes Child resolution (must be greater than cell's resolution).
     * @return int[] Array of child H3 cell indices.
     * @throws H3Exception If the operation fails.
     */
    public function cellToChildren(int $cell, int $childRes): array
    {
        $this->validateResolution($childRes);

        $size = $this->ffi->new('int64_t');
        $error = $this->ffi->cellToChildrenSize($cell, $childRes, FFI::addr($size));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get children size", $error);
        }

        $childrenCount = $size->cdata;
        $children = $this->ffi->new("H3Index[$childrenCount]");
        $error = $this->ffi->cellToChildren($cell, $childRes, $children);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get children cells", $error);
        }

        $result = [];
        for ($i = 0; $i < $childrenCount; $i++) {
            $result[] = $children[$i];
        }

        return $result;
    }

    /**
     * Get the center child cell at a finer resolution.
     *
     * @param int $cell H3 cell index.
     * @param int $childRes Child resolution.
     * @return int Center child H3 cell index.
     * @throws H3Exception If the operation fails.
     */
    public function cellToCenterChild(int $cell, int $childRes): int
    {
        $this->validateResolution($childRes);

        $child = $this->ffi->new('H3Index');
        $error = $this->ffi->cellToCenterChild($cell, $childRes, FFI::addr($child));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get center child", $error);
        }

        return $child->cdata;
    }

    /**
     * Compact a set of cells to their minimal representation.
     *
     * @param int[] $cells Array of H3 cell indices.
     * @return int[] Compacted array of H3 cell indices.
     * @throws H3Exception If the operation fails.
     */
    public function compactCells(array $cells): array
    {
        $numCells = count($cells);
        if ($numCells === 0) {
            return [];
        }

        $cellSet = $this->ffi->new("H3Index[$numCells]");
        $compactedSet = $this->ffi->new("H3Index[$numCells]");

        foreach ($cells as $i => $cell) {
            $cellSet[$i] = $cell;
        }

        $error = $this->ffi->compactCells($cellSet, $compactedSet, $numCells);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to compact cells", $error);
        }

        $result = [];
        for ($i = 0; $i < $numCells; $i++) {
            if ($compactedSet[$i] !== 0) {
                $result[] = $compactedSet[$i];
            }
        }

        return $result;
    }

    /**
     * Uncompact a set of cells to a specific resolution.
     *
     * @param int[] $cells Array of compacted H3 cell indices.
     * @param int $res Target resolution.
     * @return int[] Uncompacted array of H3 cell indices.
     * @throws H3Exception If the operation fails.
     */
    public function uncompactCells(array $cells, int $res): array
    {
        $this->validateResolution($res);

        $numCells = count($cells);
        if ($numCells === 0) {
            return [];
        }

        $compactedSet = $this->ffi->new("H3Index[$numCells]");
        foreach ($cells as $i => $cell) {
            $compactedSet[$i] = $cell;
        }

        $outSize = $this->ffi->new('int64_t');
        $error = $this->ffi->uncompactCellsSize($compactedSet, $numCells, $res, FFI::addr($outSize));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get uncompact size", $error);
        }

        $maxCells = $outSize->cdata;
        $cellSet = $this->ffi->new("H3Index[$maxCells]");
        $error = $this->ffi->uncompactCells($compactedSet, $numCells, $cellSet, $maxCells, $res);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to uncompact cells", $error);
        }

        $result = [];
        for ($i = 0; $i < $maxCells; $i++) {
            if ($cellSet[$i] !== 0) {
                $result[] = $cellSet[$i];
            }
        }

        return $result;
    }

    /**
     * Check if two cells are neighbors.
     *
     * @param int $origin First H3 cell index.
     * @param int $destination Second H3 cell index.
     * @return bool True if cells are neighbors.
     * @throws H3Exception If the operation fails.
     */
    public function areNeighborCells(int $origin, int $destination): bool
    {
        $out = $this->ffi->new('int');
        $error = $this->ffi->areNeighborCells($origin, $destination, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to check neighbor cells", $error);
        }

        return $out->cdata !== 0;
    }

    /**
     * Get directed edge between two neighboring cells.
     *
     * @param int $origin Origin H3 cell index.
     * @param int $destination Destination H3 cell index.
     * @return int Directed edge H3 index.
     * @throws H3Exception If the operation fails.
     */
    public function cellsToDirectedEdge(int $origin, int $destination): int
    {
        $out = $this->ffi->new('H3Index');
        $error = $this->ffi->cellsToDirectedEdge($origin, $destination, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get directed edge", $error);
        }

        return $out->cdata;
    }

    /**
     * Check if an H3 index is a valid directed edge.
     *
     * @param int $edge H3 directed edge index.
     * @return bool True if valid directed edge.
     */
    public function isValidDirectedEdge(int $edge): bool
    {
        return $this->ffi->isValidDirectedEdge($edge) !== 0;
    }

    /**
     * Get the origin cell of a directed edge.
     *
     * @param int $edge H3 directed edge index.
     * @return int Origin H3 cell index.
     * @throws H3Exception If the operation fails.
     */
    public function getDirectedEdgeOrigin(int $edge): int
    {
        $out = $this->ffi->new('H3Index');
        $error = $this->ffi->getDirectedEdgeOrigin($edge, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get edge origin", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the destination cell of a directed edge.
     *
     * @param int $edge H3 directed edge index.
     * @return int Destination H3 cell index.
     * @throws H3Exception If the operation fails.
     */
    public function getDirectedEdgeDestination(int $edge): int
    {
        $out = $this->ffi->new('H3Index');
        $error = $this->ffi->getDirectedEdgeDestination($edge, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get edge destination", $error);
        }

        return $out->cdata;
    }

    /**
     * Get both origin and destination cells of a directed edge.
     *
     * @param int $edge H3 directed edge index.
     * @return array{origin: int, destination: int} Origin and destination cells.
     * @throws H3Exception If the operation fails.
     */
    public function directedEdgeToCells(int $edge): array
    {
        $cells = $this->ffi->new('H3Index[2]');
        $error = $this->ffi->directedEdgeToCells($edge, $cells);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get edge cells", $error);
        }

        return [
            'origin' => $cells[0],
            'destination' => $cells[1],
        ];
    }

    /**
     * Get all directed edges from a cell.
     *
     * @param int $origin H3 cell index.
     * @return int[] Array of directed edge H3 indices.
     * @throws H3Exception If the operation fails.
     */
    public function originToDirectedEdges(int $origin): array
    {
        $edges = $this->ffi->new('H3Index[6]');
        $error = $this->ffi->originToDirectedEdges($origin, $edges);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get directed edges", $error);
        }

        $result = [];
        for ($i = 0; $i < 6; $i++) {
            if ($edges[$i] !== 0) {
                $result[] = $edges[$i];
            }
        }

        return $result;
    }

    /**
     * Get the boundary of a directed edge.
     *
     * @param int $edge H3 directed edge index.
     * @return array<array{lat: float, lng: float}> Array of lat/lng coordinates.
     * @throws H3Exception If the operation fails.
     */
    public function directedEdgeToBoundary(int $edge): array
    {
        $boundary = $this->ffi->new('CellBoundary');
        $error = $this->ffi->directedEdgeToBoundary($edge, FFI::addr($boundary));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get edge boundary", $error);
        }

        $vertices = [];
        for ($i = 0; $i < $boundary->numVerts; $i++) {
            $vertices[] = [
                'lat' => rad2deg($boundary->verts[$i]->lat),
                'lng' => rad2deg($boundary->verts[$i]->lng),
            ];
        }

        return $vertices;
    }

    /**
     * Get a specific vertex of a cell.
     *
     * @param int $cell H3 cell index.
     * @param int $vertexNum Vertex number (0-5 for hexagons, 0-4 for pentagons).
     * @return int H3 vertex index.
     * @throws H3Exception If the operation fails.
     */
    public function cellToVertex(int $cell, int $vertexNum): int
    {
        $out = $this->ffi->new('H3Index');
        $error = $this->ffi->cellToVertex($cell, $vertexNum, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get cell vertex", $error);
        }

        return $out->cdata;
    }

    /**
     * Get all vertices of a cell.
     *
     * @param int $cell H3 cell index.
     * @return int[] Array of H3 vertex indices.
     * @throws H3Exception If the operation fails.
     */
    public function cellToVertexes(int $cell): array
    {
        $vertices = $this->ffi->new('H3Index[6]');
        $error = $this->ffi->cellToVertexes($cell, $vertices);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get cell vertices", $error);
        }

        $result = [];
        for ($i = 0; $i < 6; $i++) {
            if ($vertices[$i] !== 0) {
                $result[] = $vertices[$i];
            }
        }

        return $result;
    }

    /**
     * Get the lat/lng coordinates of a vertex.
     *
     * @param int $vertex H3 vertex index.
     * @return array{lat: float, lng: float} Latitude and longitude in degrees.
     * @throws H3Exception If the operation fails.
     */
    public function vertexToLatLng(int $vertex): array
    {
        $latLng = $this->ffi->new('LatLng');
        $error = $this->ffi->vertexToLatLng($vertex, FFI::addr($latLng));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get vertex lat/lng", $error);
        }

        return [
            'lat' => rad2deg($latLng->lat),
            'lng' => rad2deg($latLng->lng),
        ];
    }

    /**
     * Check if an H3 index is a valid vertex.
     *
     * @param int $vertex H3 vertex index.
     * @return bool True if valid vertex.
     */
    public function isValidVertex(int $vertex): bool
    {
        return $this->ffi->isValidVertex($vertex) !== 0;
    }

    /**
     * Convert degrees to radians.
     *
     * @param float $degrees Angle in degrees.
     * @return float Angle in radians.
     */
    public function degsToRads(float $degrees): float
    {
        return $this->ffi->degsToRads($degrees);
    }

    /**
     * Convert radians to degrees.
     *
     * @param float $radians Angle in radians.
     * @return float Angle in degrees.
     */
    public function radsToDegs(float $radians): float
    {
        return $this->ffi->radsToDegs($radians);
    }

    /**
     * Get the average hexagon area in square kilometers at a resolution.
     *
     * @param int $res Resolution (0-15).
     * @return float Average area in km².
     * @throws H3Exception If the operation fails.
     */
    public function getHexagonAreaAvgKm2(int $res): float
    {
        $this->validateResolution($res);

        $out = $this->ffi->new('double');
        $error = $this->ffi->getHexagonAreaAvgKm2($res, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get hexagon area", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the average hexagon area in square meters at a resolution.
     *
     * @param int $res Resolution (0-15).
     * @return float Average area in m².
     * @throws H3Exception If the operation fails.
     */
    public function getHexagonAreaAvgM2(int $res): float
    {
        $this->validateResolution($res);

        $out = $this->ffi->new('double');
        $error = $this->ffi->getHexagonAreaAvgM2($res, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get hexagon area", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the exact area of a specific cell in square kilometers.
     *
     * @param int $cell H3 cell index.
     * @return float Area in km².
     * @throws H3Exception If the operation fails.
     */
    public function cellAreaKm2(int $cell): float
    {
        $out = $this->ffi->new('double');
        $error = $this->ffi->cellAreaKm2($cell, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get cell area", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the exact area of a specific cell in square meters.
     *
     * @param int $cell H3 cell index.
     * @return float Area in m².
     * @throws H3Exception If the operation fails.
     */
    public function cellAreaM2(int $cell): float
    {
        $out = $this->ffi->new('double');
        $error = $this->ffi->cellAreaM2($cell, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get cell area", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the exact area of a specific cell in square radians.
     *
     * @param int $cell H3 cell index.
     * @return float Area in radians².
     * @throws H3Exception If the operation fails.
     */
    public function cellAreaRads2(int $cell): float
    {
        $out = $this->ffi->new('double');
        $error = $this->ffi->cellAreaRads2($cell, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get cell area", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the average hexagon edge length in kilometers at a resolution.
     *
     * @param int $res Resolution (0-15).
     * @return float Average edge length in km.
     * @throws H3Exception If the operation fails.
     */
    public function getHexagonEdgeLengthAvgKm(int $res): float
    {
        $this->validateResolution($res);

        $out = $this->ffi->new('double');
        $error = $this->ffi->getHexagonEdgeLengthAvgKm($res, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get edge length", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the average hexagon edge length in meters at a resolution.
     *
     * @param int $res Resolution (0-15).
     * @return float Average edge length in m.
     * @throws H3Exception If the operation fails.
     */
    public function getHexagonEdgeLengthAvgM(int $res): float
    {
        $this->validateResolution($res);

        $out = $this->ffi->new('double');
        $error = $this->ffi->getHexagonEdgeLengthAvgM($res, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get edge length", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the exact length of a directed edge in kilometers.
     *
     * @param int $edge H3 directed edge index.
     * @return float Edge length in km.
     * @throws H3Exception If the operation fails.
     */
    public function edgeLengthKm(int $edge): float
    {
        $out = $this->ffi->new('double');
        $error = $this->ffi->edgeLengthKm($edge, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get edge length", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the exact length of a directed edge in meters.
     *
     * @param int $edge H3 directed edge index.
     * @return float Edge length in m.
     * @throws H3Exception If the operation fails.
     */
    public function edgeLengthM(int $edge): float
    {
        $out = $this->ffi->new('double');
        $error = $this->ffi->edgeLengthM($edge, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get edge length", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the exact length of a directed edge in radians.
     *
     * @param int $edge H3 directed edge index.
     * @return float Edge length in radians.
     * @throws H3Exception If the operation fails.
     */
    public function edgeLengthRads(int $edge): float
    {
        $out = $this->ffi->new('double');
        $error = $this->ffi->edgeLengthRads($edge, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get edge length", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the number of unique H3 cells at a resolution.
     *
     * @param int $res Resolution (0-15).
     * @return int Number of cells.
     * @throws H3Exception If the operation fails.
     */
    public function getNumCells(int $res): int
    {
        $this->validateResolution($res);

        $out = $this->ffi->new('int64_t');
        $error = $this->ffi->getNumCells($res, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get number of cells", $error);
        }

        return (int) $out->cdata;
    }

    /**
     * Get all resolution 0 cells.
     *
     * @return int[] Array of H3 cell indices.
     * @throws H3Exception If the operation fails.
     */
    public function getRes0Cells(): array
    {
        $count = self::RES0_CELL_COUNT;
        $out = $this->ffi->new("H3Index[$count]");
        $error = $this->ffi->getRes0Cells($out);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get res 0 cells", $error);
        }

        $cells = [];
        for ($i = 0; $i < $count; $i++) {
            $cells[] = $out[$i];
        }

        return $cells;
    }

    /**
     * Get all pentagon cells at a resolution.
     *
     * @param int $res Resolution (0-15).
     * @return int[] Array of H3 cell indices.
     * @throws H3Exception If the operation fails.
     */
    public function getPentagons(int $res): array
    {
        $this->validateResolution($res);

        $count = self::PENTAGON_COUNT;
        $out = $this->ffi->new("H3Index[$count]");
        $error = $this->ffi->getPentagons($res, $out);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get pentagons", $error);
        }

        $cells = [];
        for ($i = 0; $i < $count; $i++) {
            $cells[] = $out[$i];
        }

        return $cells;
    }

    /**
     * Get the great circle distance between two coordinates in kilometers.
     *
     * @param float $lat1 Latitude of first point in degrees.
     * @param float $lng1 Longitude of first point in degrees.
     * @param float $lat2 Latitude of second point in degrees.
     * @param float $lng2 Longitude of second point in degrees.
     * @return float Distance in km.
     */
    public function greatCircleDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = $this->ffi->new('LatLng');
        $a->lat = deg2rad($lat1);
        $a->lng = deg2rad($lng1);

        $b = $this->ffi->new('LatLng');
        $b->lat = deg2rad($lat2);
        $b->lng = deg2rad($lng2);

        return $this->ffi->greatCircleDistanceKm(FFI::addr($a), FFI::addr($b));
    }

    /**
     * Get the great circle distance between two coordinates in meters.
     *
     * @param float $lat1 Latitude of first point in degrees.
     * @param float $lng1 Longitude of first point in degrees.
     * @param float $lat2 Latitude of second point in degrees.
     * @param float $lng2 Longitude of second point in degrees.
     * @return float Distance in m.
     */
    public function greatCircleDistanceM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = $this->ffi->new('LatLng');
        $a->lat = deg2rad($lat1);
        $a->lng = deg2rad($lng1);

        $b = $this->ffi->new('LatLng');
        $b->lat = deg2rad($lat2);
        $b->lng = deg2rad($lng2);

        return $this->ffi->greatCircleDistanceM(FFI::addr($a), FFI::addr($b));
    }

    /**
     * Get the great circle distance between two coordinates in radians.
     *
     * @param float $lat1 Latitude of first point in degrees.
     * @param float $lng1 Longitude of first point in degrees.
     * @param float $lat2 Latitude of second point in degrees.
     * @param float $lng2 Longitude of second point in degrees.
     * @return float Distance in radians.
     */
    public function greatCircleDistanceRads(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = $this->ffi->new('LatLng');
        $a->lat = deg2rad($lat1);
        $a->lng = deg2rad($lng1);

        $b = $this->ffi->new('LatLng');
        $b->lat = deg2rad($lat2);
        $b->lng = deg2rad($lng2);

        return $this->ffi->greatCircleDistanceRads(FFI::addr($a), FFI::addr($b));
    }

    /**
     * Convert cell to local IJ coordinates.
     *
     * @param int $origin Origin H3 cell index.
     * @param int $cell Target H3 cell index.
     * @param int $mode Mode flags (0 for default).
     * @return array{i: int, j: int} IJ coordinates.
     * @throws H3Exception If the operation fails.
     */
    public function cellToLocalIj(int $origin, int $cell, int $mode = 0): array
    {
        $ij = $this->ffi->new('CoordIJ');
        $error = $this->ffi->cellToLocalIj($origin, $cell, $mode, FFI::addr($ij));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to convert cell to local IJ", $error);
        }

        return [
            'i' => $ij->i,
            'j' => $ij->j,
        ];
    }

    /**
     * Convert local IJ coordinates to cell.
     *
     * @param int $origin Origin H3 cell index.
     * @param int $i I coordinate.
     * @param int $j J coordinate.
     * @param int $mode Mode flags (0 for default).
     * @return int H3 cell index.
     * @throws H3Exception If the operation fails.
     */
    public function localIjToCell(int $origin, int $i, int $j, int $mode = 0): int
    {
        $ij = $this->ffi->new('CoordIJ');
        $ij->i = $i;
        $ij->j = $j;

        $out = $this->ffi->new('H3Index');
        $error = $this->ffi->localIjToCell($origin, FFI::addr($ij), $mode, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to convert local IJ to cell", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the icosahedron faces that a cell intersects.
     *
     * @param int $cell H3 cell index.
     * @return int[] Array of face indices.
     * @throws H3Exception If the operation fails.
     */
    public function getIcosahedronFaces(int $cell): array
    {
        $maxFaces = $this->ffi->new('int');
        $error = $this->ffi->maxFaceCount($cell, FFI::addr($maxFaces));

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get max face count", $error);
        }

        $count = $maxFaces->cdata;
        $faces = $this->ffi->new("int[$count]");

        // Initialize to -1 (unused face indicator)
        for ($i = 0; $i < $count; $i++) {
            $faces[$i] = -1;
        }

        $error = $this->ffi->getIcosahedronFaces($cell, $faces);

        if ($error !== self::E_SUCCESS) {
            throw new H3Exception("Failed to get icosahedron faces", $error);
        }

        $result = [];
        for ($i = 0; $i < $count; $i++) {
            if ($faces[$i] >= 0) {
                $result[] = $faces[$i];
            }
        }

        return $result;
    }

    /**
     * Validate that a resolution is within the valid range.
     *
     * @param int $resolution Resolution to validate.
     * @throws H3Exception If resolution is invalid.
     */
    private function validateResolution(int $resolution): void
    {
        if ($resolution < 0 || $resolution > self::MAX_RESOLUTION) {
            throw new H3Exception(
                "Invalid resolution: $resolution. Must be between 0 and " . self::MAX_RESOLUTION,
                self::E_RES_DOMAIN
            );
        }
    }

    /**
     * Get the underlying FFI instance.
     *
     * @return FFI The FFI instance.
     */
    public function getFFI(): FFI
    {
        return $this->ffi;
    }
}
