# H3 PHP Examples

This directory contains comprehensive real-world examples demonstrating all functionality of the H3 PHP library.

## Quick Start

```bash
# Run any example
php examples/01-indexing.php

# Run all examples
for f in examples/*.php; do echo "=== $f ===" && php "$f" && echo; done
```

## Example Files

### Core Function Examples

| File | Description | Functions Covered |
|------|-------------|-------------------|
| [01-indexing.php](01-indexing.php) | Convert coordinates to H3 cells and back | `latLngToCell`, `cellToLatLng`, `cellToBoundary` |
| [02-inspection.php](02-inspection.php) | Examine cell properties and validate data | `getResolution`, `isValidCell`, `isPentagon`, `h3ToString`, `stringToH3`, `getBaseCellNumber`, `isResClassIII`, `getIcosahedronFaces` |
| [03-traversal.php](03-traversal.php) | Navigate between cells and find neighbors | `gridDisk`, `gridDiskDistances`, `gridRing`, `gridDistance`, `gridPathCells` |
| [04-hierarchy.php](04-hierarchy.php) | Work with parent/child cell relationships | `cellToParent`, `cellToChildren`, `cellToCenterChild`, `compactCells`, `uncompactCells` |
| [05-directed-edges.php](05-directed-edges.php) | Work with cell boundaries and connections | `areNeighborCells`, `cellsToDirectedEdge`, `isValidDirectedEdge`, `getDirectedEdgeOrigin`, `getDirectedEdgeDestination`, `directedEdgeToCells`, `originToDirectedEdges`, `directedEdgeToBoundary` |
| [06-vertices.php](06-vertices.php) | Work with hexagon corner points | `cellToVertex`, `cellToVertexes`, `vertexToLatLng`, `isValidVertex` |
| [07-measurements.php](07-measurements.php) | Calculate areas, distances, and lengths | `cellAreaKm2`, `cellAreaM2`, `getHexagonAreaAvgKm2`, `getHexagonEdgeLengthAvgKm`, `edgeLengthKm`, `edgeLengthM`, `greatCircleDistanceKm`, `greatCircleDistanceM` |
| [08-utilities.php](08-utilities.php) | General utility functions | `degsToRads`, `radsToDegs`, `getNumCells`, `getRes0Cells`, `getPentagons` |
| [09-local-coordinates.php](09-local-coordinates.php) | Local IJ coordinate system | `cellToLocalIj`, `localIjToCell` |

### Real-World Application Examples

| File | Description | Use Cases |
|------|-------------|-----------|
| [10-ride-sharing-app.php](10-ride-sharing-app.php) | Complete ride-sharing platform | Driver location indexing, surge pricing zones, service area boundaries, ETA calculation |
| [11-food-delivery-platform.php](11-food-delivery-platform.php) | Food delivery service | Restaurant search, delivery zones, dynamic fees, order batching |
| [12-real-estate-analytics.php](12-real-estate-analytics.php) | Real estate analysis platform | Property valuation, neighborhood analysis, proximity scoring, market heatmaps |
| [13-fleet-management.php](13-fleet-management.php) | Fleet & logistics management | Vehicle tracking, geofencing, coverage analysis, dispatch optimization |

## Example Scenarios

### 1. Indexing (01-indexing.php)
- Restaurant location indexing for food delivery
- Map marker aggregation at cell centers
- Hexagon boundary visualization for delivery zones
- Multi-resolution location indexing
- GPS trace processing for fleet management

### 2. Inspection (02-inspection.php)
- API input validation for H3 cells
- Auto-detecting resolution from imported data
- Global data distribution by base cells
- Pentagon detection for algorithm handling
- Bulk data validation reports

### 3. Traversal (03-traversal.php)
- Restaurant search radius queries
- Delivery zone distance-based pricing
- Geofencing with ring boundaries
- Route planning between locations
- Service area coverage analysis
- Expanding search for nearest available resources

### 4. Hierarchy (04-hierarchy.php)
- Privacy-aware location hierarchy
- Analytics drill-down systems
- Efficient storage with cell compaction
- Multi-resolution data aggregation
- Hierarchical geofence systems

### 5. Directed Edges (05-directed-edges.php)
- Traffic flow analysis
- Delivery zone border mapping
- Cell connectivity networks
- Direction-based service routing
- Boundary fence generation

### 6. Vertices (06-vertices.php)
- Infrastructure placement at hexagon corners
- Precise polygon generation for maps
- Shared vertices between adjacent zones
- Corner markers for service boundaries

### 7. Measurements (07-measurements.php)
- Delivery zone area-based pricing
- Resolution selection guide
- Store locator distance calculations
- Perimeter fence cost calculation
- Global cell area comparison
- Area-based resource allocation

### 8. Utilities (08-utilities.php)
- GPS coordinate format conversion
- Database capacity planning
- Global data partitioning
- Pentagon special case handling
- Bearing calculation between points

### 9. Local Coordinates (09-local-coordinates.php)
- Hexagonal game board creation
- Warehouse zone mapping
- Vehicle relative position tracking
- Drone search patterns
- Agricultural plot management

## Resolution Guide

| Resolution | Avg Edge Length | Avg Area | Best For |
|------------|-----------------|----------|----------|
| 0 | 1,281 km | 4.3M km² | Continental analysis |
| 4 | 22.6 km | 1,770 km² | City-level |
| 7 | 1.4 km | 5.16 km² | Neighborhoods |
| 9 | 174 m | 0.105 km² | City blocks |
| 10 | 65.9 m | 0.015 km² | Buildings |
| 12 | 9.4 m | 0.0003 km² | Rooms |
| 15 | 0.5 m | 0.9 m² | Precise points |

## Common Patterns

### Spatial Indexing
```php
$h3 = H3::getInstance();
$cell = $h3->latLngToCell($lat, $lng, 9);
```

### Nearby Search
```php
$searchArea = $h3->gridDisk($originCell, 3);
foreach ($searchArea as $cell) {
    // Check if items exist in this cell
}
```

### Distance Calculation
```php
$distanceKm = $h3->greatCircleDistanceKm($lat1, $lng1, $lat2, $lng2);
```

### Cell Hierarchy
```php
$neighborhood = $h3->cellToParent($preciseCell, 8);
$buildings = $h3->cellToChildren($blockCell, 12);
```

### Efficient Storage
```php
$compacted = $h3->compactCells($largeCellSet);
$restored = $h3->uncompactCells($compacted, $targetResolution);
```

## Tips

1. **Choose resolution wisely** - Higher resolutions mean more cells and more storage/processing
2. **Use compaction** - When storing large areas, compact cells to reduce storage by 50-90%
3. **Handle pentagons** - 12 pentagon cells exist at each resolution; some algorithms need special handling
4. **Validate input** - Always validate H3 indices from external sources with `isValidCell()`
5. **Consider cell area variation** - Cell areas vary slightly based on location (±15% from equator to poles)
