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

    private static ?string $instanceLibraryPath = null;

    /**
     * H3 C library version that this package is compatible with.
     * This version is used when building the bundled H3 library.
     */
    public const H3_VERSION = '4.4.1';

    /**
     * Maximum allowed k value for grid operations to prevent memory exhaustion.
     * k=500 results in ~751,501 cells which is reasonable for most use cases.
     * Adjust via setMaxGridK() if needed for specific applications.
     */
    private static int $maxGridK = 500;

    /**
     * Maximum length for H3 string representation (15 hex chars + null terminator).
     */
    private const MAX_H3_STRING_LENGTH = 16;

    /**
     * Error code descriptions for better exception messages.
     */
    private const ERROR_MESSAGES = [
        self::E_SUCCESS => 'Success',
        self::E_FAILED => 'Operation failed',
        self::E_DOMAIN => 'Argument out of domain',
        self::E_LATLNG_DOMAIN => 'Latitude/longitude out of range',
        self::E_RES_DOMAIN => 'Resolution out of range (must be 0-15)',
        self::E_CELL_INVALID => 'Invalid H3 cell index',
        self::E_DIR_EDGE_INVALID => 'Invalid directed edge index',
        self::E_UNDIR_EDGE_INVALID => 'Invalid undirected edge index',
        self::E_VERTEX_INVALID => 'Invalid vertex index',
        self::E_PENTAGON => 'Pentagon distortion encountered',
        self::E_DUPLICATE_INPUT => 'Duplicate input detected',
        self::E_NOT_NEIGHBORS => 'Cells are not neighbors',
        self::E_RES_MISMATCH => 'Resolution mismatch',
        self::E_MEMORY_ALLOC => 'Memory allocation failed',
        self::E_MEMORY_BOUNDS => 'Memory bounds exceeded',
        self::E_OPTION_INVALID => 'Invalid option',
    ];

    /**
     * H3 C library header definitions.
     * Based on H3 v4.4.1
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

    // Polygon functions (v4.x)
    H3Error maxPolygonToCellsSize(const GeoPolygon *geoPolygon, int res, uint32_t flags, int64_t *out);
    H3Error polygonToCells(const GeoPolygon *geoPolygon, int res, uint32_t flags, H3Index *out);
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
     * Example:
     * ```php
     * // Auto-detect library path
     * $h3 = new H3();
     *
     * // Or specify a custom library path
     * $h3 = new H3('/usr/local/lib/libh3.so');
     * ```
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
     * Use this method when you want to reuse the same H3 instance across your application
     * to avoid the overhead of creating multiple FFI connections.
     *
     * Example:
     * ```php
     * // Get or create singleton instance
     * $h3 = H3::getInstance();
     *
     * // Use in another part of your application
     * $sameInstance = H3::getInstance();
     * var_dump($h3 === $sameInstance); // true
     * ```
     *
     * @param string|null $libraryPath Path to the H3 shared library.
     * @return self
     * @throws H3Exception If a different library path is requested than the existing instance.
     */
    public static function getInstance(?string $libraryPath = null): self
    {
        if (self::$instance !== null) {
            // Check if a different library path is being requested
            if ($libraryPath !== null && self::$instanceLibraryPath !== $libraryPath) {
                throw new H3Exception(
                    sprintf(
                        'H3 singleton already initialized with library path "%s". ' .
                        'Cannot reinitialize with different path "%s". ' .
                        'Call resetInstance() first if you need to change the library.',
                        self::$instanceLibraryPath ?? 'auto-detected',
                        $libraryPath
                    ),
                    self::E_FAILED
                );
            }
            return self::$instance;
        }

        self::$instance = new self($libraryPath);
        self::$instanceLibraryPath = $libraryPath;

        return self::$instance;
    }

    /**
     * Reset the singleton instance.
     *
     * This allows reinitializing with a different library path. Useful for testing
     * or when you need to switch between different H3 library versions.
     *
     * Example:
     * ```php
     * $h3 = H3::getInstance();
     * H3::resetInstance();
     * // Now you can create a new instance with a different library path
     * $h3New = H3::getInstance('/path/to/other/libh3.so');
     * ```
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
        self::$instanceLibraryPath = null;
    }

    /**
     * Set the maximum allowed k value for grid operations.
     *
     * This is a safety limit to prevent memory exhaustion. The default is 500,
     * which results in approximately 751,501 cells for gridDisk operations.
     *
     * Example:
     * ```php
     * // Increase limit for large-scale operations
     * H3::setMaxGridK(1000);
     *
     * $h3 = new H3();
     * $cells = $h3->gridDisk($origin, 800); // Now allowed
     * ```
     *
     * @param int $maxK Maximum k value (must be positive).
     * @throws H3Exception If maxK is not positive.
     */
    public static function setMaxGridK(int $maxK): void
    {
        if ($maxK <= 0) {
            throw new H3Exception(
                'Maximum grid k value must be positive',
                self::E_DOMAIN
            );
        }
        self::$maxGridK = $maxK;
    }

    /**
     * Get the current maximum allowed k value for grid operations.
     *
     * Example:
     * ```php
     * $maxK = H3::getMaxGridK();
     * echo "Maximum k value: $maxK"; // Default: 500
     * ```
     *
     * @return int Current maximum k value.
     */
    public static function getMaxGridK(): int
    {
        return self::$maxGridK;
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
        $paths = [];

        if (PHP_OS_FAMILY === 'Darwin') {
            // macOS - Homebrew paths (version-agnostic first)
            $paths = [
                '/opt/homebrew/lib/libh3.dylib',           // Apple Silicon (symlink, preferred)
                '/usr/local/lib/libh3.dylib',              // Intel (symlink, preferred)
            ];

            // Also check Homebrew Cellar for versioned installations
            $cellarPaths = [
                '/opt/homebrew/Cellar/h3',  // Apple Silicon
                '/usr/local/Cellar/h3',     // Intel
            ];

            foreach ($cellarPaths as $cellarPath) {
                if (is_dir($cellarPath)) {
                    $versions = @scandir($cellarPath, SCANDIR_SORT_DESCENDING);
                    if ($versions !== false) {
                        foreach ($versions as $version) {
                            if ($version !== '.' && $version !== '..') {
                                $libPath = "$cellarPath/$version/lib/libh3.dylib";
                                if (file_exists($libPath)) {
                                    $paths[] = $libPath;
                                }
                            }
                        }
                    }
                }
            }
        } elseif (PHP_OS_FAMILY === 'Linux') {
            // Linux paths
            $paths = [
                '/usr/lib/libh3.so',
                '/usr/lib/x86_64-linux-gnu/libh3.so',
                '/usr/lib/aarch64-linux-gnu/libh3.so',
                '/usr/local/lib/libh3.so',
                '/usr/lib64/libh3.so',
            ];
        } elseif (PHP_OS_FAMILY === 'Windows') {
            // Windows paths
            $paths = [
                'C:\\Program Files\\H3\\bin\\h3.dll',
                'h3.dll',
            ];
        }

        return $paths;
    }

    /**
     * Convert latitude/longitude to H3 cell index.
     *
     * This is the primary method for indexing a geographic point into the H3 system.
     * Higher resolutions provide more precision but smaller cell sizes.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * // Index San Francisco at resolution 9 (~174m edge length)
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     * echo $h3->h3ToString($cell); // "89283082803ffff"
     *
     * // Index at different resolutions
     * $coarse = $h3->latLngToCell(37.7749, -122.4194, 5);  // ~8km edge
     * $fine = $h3->latLngToCell(37.7749, -122.4194, 12);   // ~9m edge
     * ```
     *
     * @param float $lat Latitude in degrees (-90 to 90).
     * @param float $lng Longitude in degrees (-180 to 180).
     * @param int $resolution Resolution (0-15). Higher values = smaller cells.
     * @return int H3 cell index.
     * @throws H3Exception If the operation fails or coordinates are invalid.
     */
    public function latLngToCell(float $lat, float $lng, int $resolution): int
    {
        $this->validateResolution($resolution);
        $this->validateCoordinates($lat, $lng);

        $latLng = $this->ffi->new('LatLng');
        $latLng->lat = deg2rad($lat);
        $latLng->lng = deg2rad($lng);

        $out = $this->ffi->new('H3Index');
        $error = $this->ffi->latLngToCell(FFI::addr($latLng), $resolution, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to convert lat/lng to cell", $error);
        }

        return $out->cdata;
    }

    /**
     * Convert H3 cell index to latitude/longitude (center of the cell).
     *
     * Returns the centroid (geographic center) of the hexagonal cell.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * $center = $h3->cellToLatLng($cell);
     * echo "Lat: {$center['lat']}, Lng: {$center['lng']}";
     * // Note: May differ slightly from input due to cell center alignment
     * ```
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
            $this->throwH3Exception("Failed to convert cell to lat/lng", $error);
        }

        return [
            'lat' => rad2deg($latLng->lat),
            'lng' => rad2deg($latLng->lng),
        ];
    }

    /**
     * Get the boundary vertices of an H3 cell.
     *
     * Returns the vertices that define the hexagon (or pentagon) boundary.
     * Useful for rendering cells on a map or performing geometric operations.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * $boundary = $h3->cellToBoundary($cell);
     * // Returns 6 vertices for hexagons, 5 for pentagons
     * foreach ($boundary as $vertex) {
     *     echo "({$vertex['lat']}, {$vertex['lng']})\n";
     * }
     *
     * // Convert to GeoJSON polygon
     * $coordinates = array_map(fn($v) => [$v['lng'], $v['lat']], $boundary);
     * $coordinates[] = $coordinates[0]; // Close the polygon
     * ```
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
            $this->throwH3Exception("Failed to get cell boundary", $error);
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
     * The resolution determines the size of the cell, from 0 (largest, ~4,357 km²)
     * to 15 (smallest, ~0.9 m²).
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * $res = $h3->getResolution($cell);
     * echo "Resolution: $res"; // 9
     * ```
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
     * H3 has 122 base cells (resolution 0 cells) that tile the globe.
     * Each cell at higher resolutions belongs to exactly one base cell.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * $baseCell = $h3->getBaseCellNumber($cell);
     * echo "Base cell: $baseCell"; // 0-121
     * ```
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
     * Returns a 15-character hexadecimal string that uniquely identifies the cell.
     * This format is commonly used for storage, display, and interoperability.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * $hexString = $h3->h3ToString($cell);
     * echo $hexString; // "89283082803ffff"
     *
     * // Useful for database storage or API responses
     * $data = ['h3_index' => $h3->h3ToString($cell)];
     * ```
     *
     * @param int $cell H3 cell index.
     * @return string Hexadecimal string representation.
     * @throws H3Exception If the operation fails.
     */
    public function h3ToString(int $cell): string
    {
        $str = $this->ffi->new('char[17]');
        FFI::memset($str, 0, 17);
        $error = $this->ffi->h3ToString($cell, $str, 17);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to convert H3 index to string", $error);
        }

        return FFI::string($str);
    }

    /**
     * Convert string representation to H3 index.
     *
     * Parses a hexadecimal H3 string back into a numeric cell index.
     * Useful when reading H3 indices from databases, APIs, or user input.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * // Parse an H3 string from user input or database
     * $cell = $h3->stringToH3('89283082803ffff');
     *
     * // Now use it with other H3 operations
     * $center = $h3->cellToLatLng($cell);
     * $neighbors = $h3->gridDisk($cell, 1);
     * ```
     *
     * @param string $str Hexadecimal string representation (up to 16 hex characters).
     * @return int H3 cell index.
     * @throws H3Exception If the string is invalid or the operation fails.
     */
    public function stringToH3(string $str): int
    {
        $this->validateH3String($str);

        $out = $this->ffi->new('H3Index');
        $error = $this->ffi->stringToH3($str, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to convert string to H3 index", $error);
        }

        return $out->cdata;
    }

    /**
     * Check if an H3 index is a valid cell.
     *
     * Validates that the given index represents a legitimate H3 cell.
     * Useful for validating user input or data from external sources.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * $validCell = $h3->latLngToCell(37.7749, -122.4194, 9);
     * var_dump($h3->isValidCell($validCell)); // true
     *
     * // Invalid index
     * var_dump($h3->isValidCell(0)); // false
     * var_dump($h3->isValidCell(12345)); // false
     * ```
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
     * H3 alternates between Class II and Class III orientations at each resolution.
     * Class III cells are rotated ~19.1° relative to Class II cells.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * if ($h3->isResClassIII($cell)) {
     *     echo "This is a Class III cell (rotated orientation)";
     * } else {
     *     echo "This is a Class II cell";
     * }
     * ```
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
     * The H3 grid contains exactly 12 pentagons at each resolution (one for each
     * vertex of the icosahedron). Pentagons have only 5 neighbors instead of 6.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * // Most cells are hexagons
     * $hexCell = $h3->latLngToCell(37.7749, -122.4194, 9);
     * var_dump($h3->isPentagon($hexCell)); // false
     *
     * // Get actual pentagons at resolution 5
     * $pentagons = $h3->getPentagons(5);
     * var_dump($h3->isPentagon($pentagons[0])); // true
     * ```
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
     * Returns all cells that are at most k steps away from the origin,
     * forming a filled hexagonal disk. The number of cells is approximately 3k² + 3k + 1.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $origin = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * // Get all cells within 2 steps (includes origin)
     * $cells = $h3->gridDisk($origin, 2);
     * echo count($cells); // 19 cells (1 + 6 + 12)
     *
     * // k=0 returns just the origin
     * $single = $h3->gridDisk($origin, 0);
     * echo count($single); // 1
     *
     * // Useful for geofencing or area coverage
     * $coverageArea = $h3->gridDisk($origin, 5);
     * ```
     *
     * @param int $origin Origin H3 cell index.
     * @param int $k Grid distance (must be non-negative and within configured limit).
     * @return int[] Array of H3 cell indices.
     * @throws H3Exception If the operation fails or k is invalid.
     */
    public function gridDisk(int $origin, int $k): array
    {
        $this->validateGridK($k);

        $size = $this->ffi->new('int64_t');
        $error = $this->ffi->maxGridDiskSize($k, FFI::addr($size));

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get grid disk size", $error);
        }

        $maxSize = $size->cdata;
        $out = $this->createZeroedArray('H3Index', $maxSize);
        $error = $this->ffi->gridDisk($origin, $k, $out);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get grid disk", $error);
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
     * Similar to gridDisk(), but also returns the distance of each cell from the origin.
     * Useful for distance-based weighting or concentric ring analysis.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $origin = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * $results = $h3->gridDiskDistances($origin, 2);
     * foreach ($results as $item) {
     *     echo "Cell: {$item['cell']}, Distance: {$item['distance']}\n";
     * }
     * // Distance 0: origin cell
     * // Distance 1: 6 immediate neighbors
     * // Distance 2: 12 cells in outer ring
     *
     * // Filter by distance
     * $ring1 = array_filter($results, fn($r) => $r['distance'] === 1);
     * ```
     *
     * @param int $origin Origin H3 cell index.
     * @param int $k Grid distance (must be non-negative and within configured limit).
     * @return array<array{cell: int, distance: int}> Array of cells with distances.
     * @throws H3Exception If the operation fails or k is invalid.
     */
    public function gridDiskDistances(int $origin, int $k): array
    {
        $this->validateGridK($k);

        $size = $this->ffi->new('int64_t');
        $error = $this->ffi->maxGridDiskSize($k, FFI::addr($size));

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get grid disk size", $error);
        }

        $maxSize = $size->cdata;
        $out = $this->createZeroedArray('H3Index', $maxSize);
        $distances = $this->createZeroedArray('int', $maxSize);
        $error = $this->ffi->gridDiskDistances($origin, $k, $out, $distances);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get grid disk distances", $error);
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
     * Unlike gridDisk(), this returns only cells that are exactly k steps away,
     * forming a hollow ring around the origin. Returns 6*k cells for k > 0.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $origin = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * // Get cells exactly 2 steps away
     * $ring = $h3->gridRing($origin, 2);
     * echo count($ring); // 12 cells
     *
     * // k=1 gives immediate neighbors only
     * $neighbors = $h3->gridRing($origin, 1);
     * echo count($neighbors); // 6 cells
     *
     * // k=0 returns just the origin
     * $center = $h3->gridRing($origin, 0);
     * echo count($center); // 1 cell
     * ```
     *
     * @param int $origin Origin H3 cell index.
     * @param int $k Grid distance (must be non-negative and within configured limit).
     * @return int[] Array of H3 cell indices.
     * @throws H3Exception If the operation fails or k is invalid.
     */
    public function gridRing(int $origin, int $k): array
    {
        $this->validateGridK($k);

        $size = $this->ffi->new('int64_t');
        $error = $this->ffi->maxGridRingSize($k, FFI::addr($size));

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get grid ring size", $error);
        }

        $maxSize = $size->cdata;
        $out = $this->createZeroedArray('H3Index', $maxSize);
        $error = $this->ffi->gridRing($origin, $k, $out);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get grid ring", $error);
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
     * Returns the minimum number of grid steps required to move from the origin
     * cell to the destination cell. Both cells must be at the same resolution.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $sf = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $oakland = $h3->latLngToCell(37.8044, -122.2712, 9);
     *
     * $distance = $h3->gridDistance($sf, $oakland);
     * echo "Grid steps between SF and Oakland: $distance";
     *
     * // Distance to self is 0
     * echo $h3->gridDistance($sf, $sf); // 0
     * ```
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
            $this->throwH3Exception("Failed to get grid distance", $error);
        }

        return (int) $distance->cdata;
    }

    /**
     * Get the path of cells from start to end.
     *
     * Returns an ordered sequence of cells that forms a path between the start
     * and end cells, including both endpoints. Useful for routing and line-of-sight.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $start = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $end = $h3->latLngToCell(37.7849, -122.4094, 9);
     *
     * $path = $h3->gridPathCells($start, $end);
     * echo "Path length: " . count($path) . " cells";
     *
     * // Visualize the path
     * foreach ($path as $cell) {
     *     $coords = $h3->cellToLatLng($cell);
     *     echo "({$coords['lat']}, {$coords['lng']})\n";
     * }
     * ```
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
            $this->throwH3Exception("Failed to get grid path size", $error);
        }

        $pathSize = $size->cdata;
        $out = $this->createZeroedArray('H3Index', $pathSize);
        $error = $this->ffi->gridPathCells($start, $end, $out);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get grid path cells", $error);
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
     * Navigates up the H3 hierarchy to find the containing cell at a lower resolution.
     * Every cell has exactly one parent at each coarser resolution.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);  // Resolution 9
     *
     * // Get parent at resolution 7
     * $parent = $h3->cellToParent($cell, 7);
     * echo $h3->getResolution($parent); // 7
     *
     * // Get the base cell (resolution 0)
     * $baseCell = $h3->cellToParent($cell, 0);
     * echo $h3->getResolution($baseCell); // 0
     * ```
     *
     * @param int $cell H3 cell index.
     * @param int $parentRes Parent resolution (must be less than cell's resolution).
     * @return int Parent H3 cell index.
     * @throws H3Exception If the operation fails or resolution is invalid.
     */
    public function cellToParent(int $cell, int $parentRes): int
    {
        $this->validateResolution($parentRes);
        $this->validateParentResolution($cell, $parentRes);

        $parent = $this->ffi->new('H3Index');
        $error = $this->ffi->cellToParent($cell, $parentRes, FFI::addr($parent));

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get parent cell", $error);
        }

        return $parent->cdata;
    }

    /**
     * Get all children cells at a finer resolution.
     *
     * Returns all cells at a finer resolution that are contained within this cell.
     * A hexagon has 7 children per resolution step (center + 6 surrounding).
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 5);  // Resolution 5
     *
     * // Get all children at resolution 6
     * $children = $h3->cellToChildren($cell, 6);
     * echo count($children); // 7 children
     *
     * // Get grandchildren at resolution 7
     * $grandchildren = $h3->cellToChildren($cell, 7);
     * echo count($grandchildren); // 49 cells (7 * 7)
     *
     * // Warning: large resolution jumps create many cells!
     * // Resolution 5 -> 10 = 7^5 = 16,807 cells
     * ```
     *
     * @param int $cell H3 cell index.
     * @param int $childRes Child resolution (must be greater than cell's resolution).
     * @return int[] Array of child H3 cell indices.
     * @throws H3Exception If the operation fails or resolution is invalid.
     */
    public function cellToChildren(int $cell, int $childRes): array
    {
        $this->validateResolution($childRes);
        $this->validateChildResolution($cell, $childRes);

        $size = $this->ffi->new('int64_t');
        $error = $this->ffi->cellToChildrenSize($cell, $childRes, FFI::addr($size));

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get children size", $error);
        }

        $childrenCount = $size->cdata;
        $children = $this->createZeroedArray('H3Index', $childrenCount);
        $error = $this->ffi->cellToChildren($cell, $childRes, $children);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get children cells", $error);
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
     * Returns the single child cell that is geometrically centered within the parent.
     * More efficient than cellToChildren() when you only need the center.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 5);
     *
     * // Get the center child at resolution 8
     * $centerChild = $h3->cellToCenterChild($cell, 8);
     *
     * // The center child contains the same center point
     * $parentCenter = $h3->cellToLatLng($cell);
     * $childCenter = $h3->cellToLatLng($centerChild);
     * // Both coordinates will be very close
     * ```
     *
     * @param int $cell H3 cell index.
     * @param int $childRes Child resolution (must be greater than cell's resolution).
     * @return int Center child H3 cell index.
     * @throws H3Exception If the operation fails or resolution is invalid.
     */
    public function cellToCenterChild(int $cell, int $childRes): int
    {
        $this->validateResolution($childRes);
        $this->validateChildResolution($cell, $childRes);

        $child = $this->ffi->new('H3Index');
        $error = $this->ffi->cellToCenterChild($cell, $childRes, FFI::addr($child));

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get center child", $error);
        }

        return $child->cdata;
    }

    /**
     * Compact a set of cells to their minimal representation.
     *
     * Replaces groups of 7 sibling cells with their parent cell where possible.
     * Reduces storage requirements while preserving the covered area.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $center = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * // Get a large disk of cells
     * $cells = $h3->gridDisk($center, 10);
     * echo "Original: " . count($cells) . " cells";  // 331 cells
     *
     * // Compact to minimal representation
     * $compacted = $h3->compactCells($cells);
     * echo "Compacted: " . count($compacted) . " cells";  // Fewer cells
     *
     * // Store compacted cells in database for efficiency
     * $forStorage = array_map(fn($c) => $h3->h3ToString($c), $compacted);
     * ```
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

        $cellSet = $this->createZeroedArray('H3Index', $numCells);
        $compactedSet = $this->createZeroedArray('H3Index', $numCells);

        foreach ($cells as $i => $cell) {
            $cellSet[$i] = $cell;
        }

        $error = $this->ffi->compactCells($cellSet, $compactedSet, $numCells);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to compact cells", $error);
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
     * Expands compacted cells back to a uniform resolution. All output cells
     * will be at the specified target resolution.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * // Start with compacted cells (mixed resolutions)
     * $compacted = [...]; // From compactCells() or storage
     *
     * // Expand to uniform resolution 9
     * $expanded = $h3->uncompactCells($compacted, 9);
     *
     * // All cells are now at resolution 9
     * foreach ($expanded as $cell) {
     *     echo $h3->getResolution($cell) . "\n"; // Always 9
     * }
     * ```
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

        $compactedSet = $this->createZeroedArray('H3Index', $numCells);
        foreach ($cells as $i => $cell) {
            $compactedSet[$i] = $cell;
        }

        $outSize = $this->ffi->new('int64_t');
        $error = $this->ffi->uncompactCellsSize($compactedSet, $numCells, $res, FFI::addr($outSize));

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get uncompact size", $error);
        }

        $maxCells = $outSize->cdata;
        $cellSet = $this->createZeroedArray('H3Index', $maxCells);
        $error = $this->ffi->uncompactCells($compactedSet, $numCells, $cellSet, $maxCells, $res);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to uncompact cells", $error);
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
     * Two cells are neighbors if they share an edge. Both cells must be at the
     * same resolution. Hexagons have 6 neighbors, pentagons have 5.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $neighbors = $h3->gridRing($cell, 1);
     *
     * // All ring-1 cells are neighbors
     * foreach ($neighbors as $neighbor) {
     *     var_dump($h3->areNeighborCells($cell, $neighbor)); // true
     * }
     *
     * // Non-adjacent cells are not neighbors
     * $farCell = $h3->gridRing($cell, 5)[0];
     * var_dump($h3->areNeighborCells($cell, $farCell)); // false
     * ```
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
            $this->throwH3Exception("Failed to check neighbor cells", $error);
        }

        return $out->cdata !== 0;
    }

    /**
     * Get directed edge between two neighboring cells.
     *
     * Creates an edge index representing the boundary between two adjacent cells.
     * The edge has a direction from origin to destination.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $neighbor = $h3->gridRing($cell, 1)[0];
     *
     * // Get the directed edge from cell to neighbor
     * $edge = $h3->cellsToDirectedEdge($cell, $neighbor);
     *
     * // Edge properties
     * $origin = $h3->getDirectedEdgeOrigin($edge);      // Same as $cell
     * $dest = $h3->getDirectedEdgeDestination($edge);   // Same as $neighbor
     * $boundary = $h3->directedEdgeToBoundary($edge);   // Edge vertices
     * ```
     *
     * @param int $origin Origin H3 cell index.
     * @param int $destination Destination H3 cell index.
     * @return int Directed edge H3 index.
     * @throws H3Exception If the operation fails or cells are not neighbors.
     */
    public function cellsToDirectedEdge(int $origin, int $destination): int
    {
        $out = $this->ffi->new('H3Index');
        $error = $this->ffi->cellsToDirectedEdge($origin, $destination, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get directed edge", $error);
        }

        return $out->cdata;
    }

    /**
     * Check if an H3 index is a valid directed edge.
     *
     * Validates that the index represents a legitimate H3 directed edge.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $neighbor = $h3->gridRing($cell, 1)[0];
     * $edge = $h3->cellsToDirectedEdge($cell, $neighbor);
     *
     * var_dump($h3->isValidDirectedEdge($edge)); // true
     * var_dump($h3->isValidDirectedEdge($cell)); // false (it's a cell, not an edge)
     * ```
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
     * Example:
     * ```php
     * $h3 = new H3();
     * $cellA = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $cellB = $h3->gridRing($cellA, 1)[0];
     * $edge = $h3->cellsToDirectedEdge($cellA, $cellB);
     *
     * $origin = $h3->getDirectedEdgeOrigin($edge);
     * var_dump($origin === $cellA); // true
     * ```
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
            $this->throwH3Exception("Failed to get edge origin", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the destination cell of a directed edge.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cellA = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $cellB = $h3->gridRing($cellA, 1)[0];
     * $edge = $h3->cellsToDirectedEdge($cellA, $cellB);
     *
     * $destination = $h3->getDirectedEdgeDestination($edge);
     * var_dump($destination === $cellB); // true
     * ```
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
            $this->throwH3Exception("Failed to get edge destination", $error);
        }

        return $out->cdata;
    }

    /**
     * Get both origin and destination cells of a directed edge.
     *
     * Convenience method that returns both endpoints of the edge in one call.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $edges = $h3->originToDirectedEdges($cell);
     *
     * $cells = $h3->directedEdgeToCells($edges[0]);
     * echo "Origin: {$cells['origin']}\n";
     * echo "Destination: {$cells['destination']}\n";
     * ```
     *
     * @param int $edge H3 directed edge index.
     * @return array{origin: int, destination: int} Origin and destination cells.
     * @throws H3Exception If the operation fails.
     */
    public function directedEdgeToCells(int $edge): array
    {
        $cells = $this->createZeroedArray('H3Index', 2);
        $error = $this->ffi->directedEdgeToCells($edge, $cells);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get edge cells", $error);
        }

        return [
            'origin' => $cells[0],
            'destination' => $cells[1],
        ];
    }

    /**
     * Get all directed edges from a cell.
     *
     * Returns all edges that originate from this cell. Hexagons have 6 edges,
     * pentagons have 5.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * $edges = $h3->originToDirectedEdges($cell);
     * echo count($edges); // 6 for hexagons, 5 for pentagons
     *
     * // Get all edge boundaries for visualization
     * foreach ($edges as $edge) {
     *     $boundary = $h3->directedEdgeToBoundary($edge);
     *     // Draw edge on map
     * }
     * ```
     *
     * @param int $origin H3 cell index.
     * @return int[] Array of directed edge H3 indices.
     * @throws H3Exception If the operation fails.
     */
    public function originToDirectedEdges(int $origin): array
    {
        $edges = $this->createZeroedArray('H3Index', 6);
        $error = $this->ffi->originToDirectedEdges($origin, $edges);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get directed edges", $error);
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
     * Returns the geographic coordinates of the edge line between two cells.
     * Typically returns 2 vertices (the endpoints of the shared edge).
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $edges = $h3->originToDirectedEdges($cell);
     *
     * $boundary = $h3->directedEdgeToBoundary($edges[0]);
     * // Returns 2 points defining the edge line
     * echo "Start: ({$boundary[0]['lat']}, {$boundary[0]['lng']})\n";
     * echo "End: ({$boundary[1]['lat']}, {$boundary[1]['lng']})\n";
     * ```
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
            $this->throwH3Exception("Failed to get edge boundary", $error);
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
     * Returns an H3 vertex index for the specified corner of the cell.
     * Vertices are shared between adjacent cells.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * // Get vertex 0
     * $vertex = $h3->cellToVertex($cell, 0);
     *
     * // Get its coordinates
     * $coords = $h3->vertexToLatLng($vertex);
     * echo "Vertex 0: ({$coords['lat']}, {$coords['lng']})";
     * ```
     *
     * @param int $cell H3 cell index.
     * @param int $vertexNum Vertex number (0-5 for hexagons, 0-4 for pentagons).
     * @return int H3 vertex index.
     * @throws H3Exception If the operation fails or vertex number is invalid.
     */
    public function cellToVertex(int $cell, int $vertexNum): int
    {
        $this->validateVertexNum($cell, $vertexNum);

        $out = $this->ffi->new('H3Index');
        $error = $this->ffi->cellToVertex($cell, $vertexNum, FFI::addr($out));

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get cell vertex", $error);
        }

        return $out->cdata;
    }

    /**
     * Get all vertices of a cell.
     *
     * Returns H3 vertex indices for all corners of the cell.
     * Hexagons have 6 vertices, pentagons have 5.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * $vertices = $h3->cellToVertexes($cell);
     * echo count($vertices); // 6 for hexagons
     *
     * // Get coordinates of all vertices
     * foreach ($vertices as $i => $vertex) {
     *     $coords = $h3->vertexToLatLng($vertex);
     *     echo "Vertex $i: ({$coords['lat']}, {$coords['lng']})\n";
     * }
     * ```
     *
     * @param int $cell H3 cell index.
     * @return int[] Array of H3 vertex indices.
     * @throws H3Exception If the operation fails.
     */
    public function cellToVertexes(int $cell): array
    {
        $vertices = $this->createZeroedArray('H3Index', 6);
        $error = $this->ffi->cellToVertexes($cell, $vertices);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get cell vertices", $error);
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
     * Converts an H3 vertex index to geographic coordinates.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $vertex = $h3->cellToVertex($cell, 0);
     *
     * $coords = $h3->vertexToLatLng($vertex);
     * echo "Lat: {$coords['lat']}, Lng: {$coords['lng']}";
     * ```
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
            $this->throwH3Exception("Failed to get vertex lat/lng", $error);
        }

        return [
            'lat' => rad2deg($latLng->lat),
            'lng' => rad2deg($latLng->lng),
        ];
    }

    /**
     * Check if an H3 index is a valid vertex.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $vertex = $h3->cellToVertex($cell, 0);
     *
     * var_dump($h3->isValidVertex($vertex)); // true
     * var_dump($h3->isValidVertex($cell));   // false (it's a cell, not a vertex)
     * ```
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
     * Uses the H3 library's conversion for consistency with other H3 calculations.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $radians = $h3->degsToRads(180.0);
     * echo $radians; // ~3.14159 (π)
     * ```
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
     * Uses the H3 library's conversion for consistency with other H3 calculations.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $degrees = $h3->radsToDegs(M_PI);
     * echo $degrees; // 180.0
     * ```
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
     * Returns the average area of hexagonal cells at the given resolution.
     * Actual cell areas vary slightly due to the icosahedral projection.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * // Compare areas at different resolutions
     * echo "Res 0: " . $h3->getHexagonAreaAvgKm2(0) . " km²\n";   // ~4,357,449 km²
     * echo "Res 5: " . $h3->getHexagonAreaAvgKm2(5) . " km²\n";   // ~253 km²
     * echo "Res 9: " . $h3->getHexagonAreaAvgKm2(9) . " km²\n";   // ~0.1 km²
     * echo "Res 15: " . $h3->getHexagonAreaAvgKm2(15) . " km²\n"; // ~0.0000009 km²
     * ```
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
            $this->throwH3Exception("Failed to get hexagon area", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the average hexagon area in square meters at a resolution.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * // Resolution 9 is commonly used for city-level analysis
     * $area = $h3->getHexagonAreaAvgM2(9);
     * echo "Res 9 average area: $area m²"; // ~105,332 m²
     * ```
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
            $this->throwH3Exception("Failed to get hexagon area", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the exact area of a specific cell in square kilometers.
     *
     * Unlike getHexagonAreaAvgKm2(), this returns the precise area of the
     * specific cell, accounting for distortion from the projection.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * $area = $h3->cellAreaKm2($cell);
     * echo "Cell area: $area km²";
     *
     * // Compare with average
     * $avg = $h3->getHexagonAreaAvgKm2(9);
     * $diff = abs($area - $avg) / $avg * 100;
     * echo "Differs from average by: $diff%";
     * ```
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
            $this->throwH3Exception("Failed to get cell area", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the exact area of a specific cell in square meters.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * $area = $h3->cellAreaM2($cell);
     * echo "Cell area: $area m²";
     * ```
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
            $this->throwH3Exception("Failed to get cell area", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the exact area of a specific cell in square radians.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * $area = $h3->cellAreaRads2($cell);
     * echo "Cell area: $area rad²";
     * ```
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
            $this->throwH3Exception("Failed to get cell area", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the average hexagon edge length in kilometers at a resolution.
     *
     * Useful for understanding the physical scale of cells at different resolutions.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * echo "Res 0: " . $h3->getHexagonEdgeLengthAvgKm(0) . " km\n";  // ~1,107 km
     * echo "Res 5: " . $h3->getHexagonEdgeLengthAvgKm(5) . " km\n";  // ~8 km
     * echo "Res 9: " . $h3->getHexagonEdgeLengthAvgKm(9) . " km\n";  // ~174 m
     * echo "Res 15: " . $h3->getHexagonEdgeLengthAvgKm(15) . " km\n"; // ~0.5 m
     * ```
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
            $this->throwH3Exception("Failed to get edge length", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the average hexagon edge length in meters at a resolution.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * $edgeLength = $h3->getHexagonEdgeLengthAvgM(9);
     * echo "Res 9 edge length: $edgeLength m"; // ~174m
     * ```
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
            $this->throwH3Exception("Failed to get edge length", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the exact length of a directed edge in kilometers.
     *
     * Returns the precise length of a specific cell edge.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $edges = $h3->originToDirectedEdges($cell);
     *
     * $length = $h3->edgeLengthKm($edges[0]);
     * echo "Edge length: $length km";
     * ```
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
            $this->throwH3Exception("Failed to get edge length", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the exact length of a directed edge in meters.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $edges = $h3->originToDirectedEdges($cell);
     *
     * $length = $h3->edgeLengthM($edges[0]);
     * echo "Edge length: $length m";
     * ```
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
            $this->throwH3Exception("Failed to get edge length", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the exact length of a directed edge in radians.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $edges = $h3->originToDirectedEdges($cell);
     *
     * $length = $h3->edgeLengthRads($edges[0]);
     * echo "Edge length: $length radians";
     * ```
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
            $this->throwH3Exception("Failed to get edge length", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the number of unique H3 cells at a resolution.
     *
     * Returns the total count of cells that tile the entire globe at a resolution.
     * The count grows by approximately 7x per resolution level.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * echo "Res 0: " . $h3->getNumCells(0) . " cells\n";   // 122
     * echo "Res 5: " . $h3->getNumCells(5) . " cells\n";   // 2,016,842
     * echo "Res 9: " . $h3->getNumCells(9) . " cells\n";   // 4,842,432,842
     * echo "Res 15: " . $h3->getNumCells(15) . " cells\n"; // 569,707,381,193,162
     * ```
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
            $this->throwH3Exception("Failed to get number of cells", $error);
        }

        return (int) $out->cdata;
    }

    /**
     * Get all resolution 0 cells.
     *
     * Returns the 122 base cells that form the coarsest level of the H3 grid.
     * These cells are the starting point for the hierarchical indexing system.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * $baseCells = $h3->getRes0Cells();
     * echo count($baseCells); // 122
     *
     * // Each base cell covers a large portion of Earth
     * foreach ($baseCells as $cell) {
     *     $area = $h3->cellAreaKm2($cell);
     *     echo "Cell area: $area km²\n"; // ~4,357,449 km² average
     * }
     * ```
     *
     * @return int[] Array of H3 cell indices.
     * @throws H3Exception If the operation fails.
     */
    public function getRes0Cells(): array
    {
        $count = self::RES0_CELL_COUNT;
        $out = $this->createZeroedArray('H3Index', $count);
        $error = $this->ffi->getRes0Cells($out);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get res 0 cells", $error);
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
     * Returns the 12 pentagon cells that exist at every resolution.
     * Pentagons are located at the vertices of the icosahedron and have
     * only 5 neighbors instead of 6.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * $pentagons = $h3->getPentagons(9);
     * echo count($pentagons); // Always 12
     *
     * foreach ($pentagons as $pentagon) {
     *     $center = $h3->cellToLatLng($pentagon);
     *     echo "Pentagon at ({$center['lat']}, {$center['lng']})\n";
     * }
     * ```
     *
     * @param int $res Resolution (0-15).
     * @return int[] Array of H3 cell indices.
     * @throws H3Exception If the operation fails.
     */
    public function getPentagons(int $res): array
    {
        $this->validateResolution($res);

        $count = self::PENTAGON_COUNT;
        $out = $this->createZeroedArray('H3Index', $count);
        $error = $this->ffi->getPentagons($res, $out);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get pentagons", $error);
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
     * Calculates the shortest distance between two points on Earth's surface
     * using the Haversine formula.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * // Distance between San Francisco and New York
     * $sf = ['lat' => 37.7749, 'lng' => -122.4194];
     * $ny = ['lat' => 40.7128, 'lng' => -74.0060];
     *
     * $distance = $h3->greatCircleDistanceKm(
     *     $sf['lat'], $sf['lng'],
     *     $ny['lat'], $ny['lng']
     * );
     * echo "SF to NY: $distance km"; // ~4,129 km
     * ```
     *
     * @param float $lat1 Latitude of first point in degrees (-90 to 90).
     * @param float $lng1 Longitude of first point in degrees (-180 to 180).
     * @param float $lat2 Latitude of second point in degrees (-90 to 90).
     * @param float $lng2 Longitude of second point in degrees (-180 to 180).
     * @return float Distance in km.
     * @throws H3Exception If coordinates are invalid.
     */
    public function greatCircleDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $this->validateCoordinates($lat1, $lng1);
        $this->validateCoordinates($lat2, $lng2);

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
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * // Distance between two nearby points
     * $distance = $h3->greatCircleDistanceM(
     *     37.7749, -122.4194,
     *     37.7750, -122.4195
     * );
     * echo "Distance: $distance m";
     * ```
     *
     * @param float $lat1 Latitude of first point in degrees (-90 to 90).
     * @param float $lng1 Longitude of first point in degrees (-180 to 180).
     * @param float $lat2 Latitude of second point in degrees (-90 to 90).
     * @param float $lng2 Longitude of second point in degrees (-180 to 180).
     * @return float Distance in m.
     * @throws H3Exception If coordinates are invalid.
     */
    public function greatCircleDistanceM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $this->validateCoordinates($lat1, $lng1);
        $this->validateCoordinates($lat2, $lng2);

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
     * Example:
     * ```php
     * $h3 = new H3();
     *
     * $radians = $h3->greatCircleDistanceRads(
     *     37.7749, -122.4194,
     *     40.7128, -74.0060
     * );
     * // Convert to km: radians * Earth's radius (6371 km)
     * $km = $radians * 6371;
     * ```
     *
     * @param float $lat1 Latitude of first point in degrees (-90 to 90).
     * @param float $lng1 Longitude of first point in degrees (-180 to 180).
     * @param float $lat2 Latitude of second point in degrees (-90 to 90).
     * @param float $lng2 Longitude of second point in degrees (-180 to 180).
     * @return float Distance in radians.
     * @throws H3Exception If coordinates are invalid.
     */
    public function greatCircleDistanceRads(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $this->validateCoordinates($lat1, $lng1);
        $this->validateCoordinates($lat2, $lng2);

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
     * Produces IJ coordinates for a cell relative to an origin cell.
     * Useful for local coordinate systems and grid operations.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $origin = $h3->latLngToCell(37.7749, -122.4194, 9);
     * $neighbor = $h3->gridRing($origin, 1)[0];
     *
     * $ij = $h3->cellToLocalIj($origin, $neighbor);
     * echo "I: {$ij['i']}, J: {$ij['j']}";
     *
     * // Convert back to cell
     * $cell = $h3->localIjToCell($origin, $ij['i'], $ij['j']);
     * ```
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
            $this->throwH3Exception("Failed to convert cell to local IJ", $error);
        }

        return [
            'i' => $ij->i,
            'j' => $ij->j,
        ];
    }

    /**
     * Convert local IJ coordinates to cell.
     *
     * Converts IJ coordinates back to an H3 cell index, given an origin.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $origin = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * // Get a cell at IJ offset (1, 0) from origin
     * $cell = $h3->localIjToCell($origin, 1, 0);
     *
     * // Verify it's a neighbor
     * var_dump($h3->areNeighborCells($origin, $cell)); // true
     * ```
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
            $this->throwH3Exception("Failed to convert local IJ to cell", $error);
        }

        return $out->cdata;
    }

    /**
     * Get the icosahedron faces that a cell intersects.
     *
     * The H3 grid is based on an icosahedron with 20 faces.
     * Most cells belong to a single face, but cells near face edges
     * may span multiple faces.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $cell = $h3->latLngToCell(37.7749, -122.4194, 9);
     *
     * $faces = $h3->getIcosahedronFaces($cell);
     * echo "Cell spans " . count($faces) . " face(s): " . implode(', ', $faces);
     * ```
     *
     * @param int $cell H3 cell index.
     * @return int[] Array of face indices (0-19).
     * @throws H3Exception If the operation fails.
     */
    public function getIcosahedronFaces(int $cell): array
    {
        $maxFaces = $this->ffi->new('int');
        $error = $this->ffi->maxFaceCount($cell, FFI::addr($maxFaces));

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get max face count", $error);
        }

        $count = $maxFaces->cdata;
        $faces = $this->createZeroedArray('int', $count);

        // Initialize to -1 (unused face indicator)
        for ($i = 0; $i < $count; $i++) {
            $faces[$i] = -1;
        }

        $error = $this->ffi->getIcosahedronFaces($cell, $faces);

        if ($error !== self::E_SUCCESS) {
            $this->throwH3Exception("Failed to get icosahedron faces", $error);
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
     * Validate latitude and longitude values.
     *
     * @param float $lat Latitude in degrees.
     * @param float $lng Longitude in degrees.
     * @throws H3Exception If coordinates are invalid (NaN, Inf, or out of range).
     */
    private function validateCoordinates(float $lat, float $lng): void
    {
        if (is_nan($lat) || is_nan($lng)) {
            throw new H3Exception(
                'Coordinates cannot be NaN',
                self::E_LATLNG_DOMAIN
            );
        }

        if (is_infinite($lat) || is_infinite($lng)) {
            throw new H3Exception(
                'Coordinates cannot be infinite',
                self::E_LATLNG_DOMAIN
            );
        }

        if ($lat < -90.0 || $lat > 90.0) {
            throw new H3Exception(
                "Latitude must be between -90 and 90 degrees, got: $lat",
                self::E_LATLNG_DOMAIN
            );
        }

        if ($lng < -180.0 || $lng > 180.0) {
            throw new H3Exception(
                "Longitude must be between -180 and 180 degrees, got: $lng",
                self::E_LATLNG_DOMAIN
            );
        }
    }

    /**
     * Validate the k parameter for grid operations.
     *
     * @param int $k Grid distance.
     * @throws H3Exception If k is negative or exceeds the maximum allowed value.
     */
    private function validateGridK(int $k): void
    {
        if ($k < 0) {
            throw new H3Exception(
                "Grid distance k cannot be negative, got: $k",
                self::E_DOMAIN
            );
        }

        if ($k > self::$maxGridK) {
            throw new H3Exception(
                sprintf(
                    'Grid distance k=%d exceeds maximum allowed value of %d. ' .
                    'This limit prevents memory exhaustion. Use H3::setMaxGridK() to increase if needed.',
                    $k,
                    self::$maxGridK
                ),
                self::E_DOMAIN
            );
        }
    }

    /**
     * Validate an H3 string representation before parsing.
     *
     * @param string $str H3 string to validate.
     * @throws H3Exception If the string is invalid.
     */
    private function validateH3String(string $str): void
    {
        // Check for null bytes which could cause truncation in C
        if (strpos($str, "\0") !== false) {
            throw new H3Exception(
                'H3 string cannot contain null bytes',
                self::E_FAILED
            );
        }

        // Check length (H3 index is 15 hex chars max)
        $len = strlen($str);
        if ($len === 0) {
            throw new H3Exception(
                'H3 string cannot be empty',
                self::E_FAILED
            );
        }

        if ($len > self::MAX_H3_STRING_LENGTH) {
            throw new H3Exception(
                sprintf(
                    'H3 string too long: %d characters (maximum is %d)',
                    $len,
                    self::MAX_H3_STRING_LENGTH
                ),
                self::E_FAILED
            );
        }

        // Validate hex characters only
        if (!ctype_xdigit($str)) {
            throw new H3Exception(
                'H3 string must contain only hexadecimal characters (0-9, a-f, A-F)',
                self::E_FAILED
            );
        }
    }

    /**
     * Validate vertex number for cell vertex operations.
     *
     * @param int $cell H3 cell index.
     * @param int $vertexNum Vertex number.
     * @throws H3Exception If vertex number is invalid.
     */
    private function validateVertexNum(int $cell, int $vertexNum): void
    {
        $maxVertex = $this->isPentagon($cell) ? 4 : 5;

        if ($vertexNum < 0 || $vertexNum > $maxVertex) {
            throw new H3Exception(
                sprintf(
                    'Vertex number must be between 0 and %d for this cell, got: %d',
                    $maxVertex,
                    $vertexNum
                ),
                self::E_DOMAIN
            );
        }
    }

    /**
     * Validate that child resolution is finer than parent resolution.
     *
     * @param int $cell Parent cell.
     * @param int $childRes Child resolution.
     * @throws H3Exception If child resolution is not finer than parent.
     */
    private function validateChildResolution(int $cell, int $childRes): void
    {
        $parentRes = $this->getResolution($cell);

        if ($childRes <= $parentRes) {
            throw new H3Exception(
                sprintf(
                    'Child resolution (%d) must be greater than parent resolution (%d)',
                    $childRes,
                    $parentRes
                ),
                self::E_RES_MISMATCH
            );
        }
    }

    /**
     * Validate that parent resolution is coarser than cell resolution.
     *
     * @param int $cell Child cell.
     * @param int $parentRes Parent resolution.
     * @throws H3Exception If parent resolution is not coarser than cell.
     */
    private function validateParentResolution(int $cell, int $parentRes): void
    {
        $cellRes = $this->getResolution($cell);

        if ($parentRes >= $cellRes) {
            throw new H3Exception(
                sprintf(
                    'Parent resolution (%d) must be less than cell resolution (%d)',
                    $parentRes,
                    $cellRes
                ),
                self::E_RES_MISMATCH
            );
        }
    }

    /**
     * Create and zero-initialize an FFI array.
     *
     * @param string $type C type for the array elements (e.g., 'H3Index', 'int').
     * @param int $size Number of elements.
     * @return FFI\CData The zero-initialized array.
     */
    private function createZeroedArray(string $type, int $size): FFI\CData
    {
        $array = $this->ffi->new("{$type}[$size]");
        FFI::memset($array, 0, FFI::sizeof($array));
        return $array;
    }

    /**
     * Throw an H3Exception with a descriptive error message.
     *
     * @param string $message Base error message.
     * @param int $errorCode H3 error code.
     * @throws H3Exception Always throws.
     * @return never
     */
    private function throwH3Exception(string $message, int $errorCode): never
    {
        $errorDesc = self::ERROR_MESSAGES[$errorCode] ?? "Unknown error code: $errorCode";
        throw new H3Exception(
            "$message: $errorDesc (code: $errorCode)",
            $errorCode
        );
    }

    /**
     * Get the underlying FFI instance.
     *
     * Provides direct access to the FFI object for advanced use cases
     * not covered by this wrapper.
     *
     * Example:
     * ```php
     * $h3 = new H3();
     * $ffi = $h3->getFFI();
     *
     * // Access FFI directly for low-level operations
     * $latLng = $ffi->new('LatLng');
     * $latLng->lat = deg2rad(37.7749);
     * $latLng->lng = deg2rad(-122.4194);
     * ```
     *
     * @return FFI The FFI instance.
     */
    public function getFFI(): FFI
    {
        return $this->ffi;
    }
}
