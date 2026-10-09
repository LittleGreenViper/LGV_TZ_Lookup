<?php
/***************************************************************************************************************************/
/**
    © Copyright 2023-2026, [Little Green Viper Software Development LLC](https://littlegreenviper.com)

    LICENSE:

    MIT License

    Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation
    files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy,
    modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
    Software is furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
    OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
    IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
    CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

    [Little Green Viper Software Development LLC](https://littlegreenviper.com)
*/
/***************************************************************************************************************************/
/**
    \file
    \brief These are the independent geometry checks and audit helpers used by the Composer demo.

    This file is included after the temporary application's Composer autoloader has been loaded. The installed
    package performs the actual database load and lookup. These helpers retain source geometry, generate expected
    answers without calling the package's lookup algorithm, and record which stored polygons the package evaluates.
*/

declare(strict_types=1);

/***************************************************************************************************************************/
/**
    \brief This retains a compact reference copy of each polygon in the decoded GeoJSON.

    A shape corresponds to one Polygon, or one member of a MultiPolygon. Rings are packed as native doubles, in
    longitude/latitude order, rounded to six decimal places. The first ring is the exterior; remaining rings are holes.
    Storage validation checks the package's documented exterior-ring encoding. Lookup expectations also account
    for the holes, using horizontal ray casting instead of the package's vertical winding-number test.
*/
class DemoSourceGeometry {
    /***********************************************************************************************************************/
    /**
        \brief These are the source polygon records, keyed by their expected database row IDs.

        Each record contains id, tzname, rings, bounds, vertices, bytes, and sha256. The bounds come from the source's
        exterior ring. vertices, bytes, and sha256 describe the exterior ring that the package stores in its polygon column.
        Records follow source traversal order, matching a newly initialized table's auto-increment IDs.
     */
    public array $shapes = [];
    /***********************************************************************************************************************/
    /** \brief This is the total number of exterior-ring points, including repeated closing points. */
    public int $vertices = 0;
    /***********************************************************************************************************************/
    /** \brief This is the total expected size, in bytes, of the stored exterior-ring polygon data. */
    public int $polygonBytes = 0;

    /***********************************************************************************************************************/
    /**
        \brief This records the polygons from one decoded GeoJSON Feature.

        MultiPolygons are separated into individual records, in the same source order used by the package loader.
        We retain holes for the independent geographic reference, and compute exterior-ring hashes for storage checks.

        \throws RuntimeException if the geometry type, coordinate dimensions, or polygon contents are unsupported.
     */
    public function observe(array $feature   ///< The decoded Feature, with properties.tzid and geometry.coordinates.
                            ): void {
        $geometry = $feature['geometry'];
        if ($geometry['type'] === 'Polygon') {
            $polygons = [$geometry['coordinates']];
        } elseif ($geometry['type'] === 'MultiPolygon') {
            $polygons = $geometry['coordinates'];
        } else {
            throw new RuntimeException('Unsupported source geometry: '.$geometry['type']);
        }
        foreach ($polygons as $polygon) {
            $rings = [];
            $bounds = ['east' => -INF, 'west' => INF, 'north' => -INF, 'south' => INF];
            foreach ($polygon as $ringIndex => $ring) {
                $packed = '';
                foreach ($ring as $point) {
                    if (count($point) !== 2) {
                        throw new RuntimeException('Expected two-dimensional source coordinates.');
                    }
                    $packed .= pack('d2', round($point[0], 6), round($point[1], 6));
                    if ($ringIndex === 0) {
                        $bounds['east'] = max($bounds['east'], $point[0]);
                        $bounds['west'] = min($bounds['west'], $point[0]);
                        $bounds['north'] = max($bounds['north'], $point[1]);
                        $bounds['south'] = min($bounds['south'], $point[1]);
                    }
                }
                $rings[] = $packed;
            }
            if (empty($rings) || strlen($rings[0]) < 48) {
                throw new RuntimeException('The source contains an empty or degenerate polygon.');
            }
            $count = intdiv(strlen($rings[0]), 16);
            $id = count($this->shapes) + 1;
            $this->shapes[$id] = [
                'id' => $id, 'tzname' => $feature['properties']['tzid'], 'rings' => $rings,
                'bounds' => $bounds, 'vertices' => $count, 'bytes' => strlen($rings[0]),
                'sha256' => hash('sha256', $rings[0]),
            ];
            $this->vertices += $count;
            $this->polygonBytes += strlen($rings[0]);
        }
    }

    /***********************************************************************************************************************/
    /**
        \brief This selects the most detailed polygons, using exterior-ring point counts.

        "Largest" means the most points to decode, rather than the greatest geographic area. Equal counts are ordered
        by row ID, so the selection is repeatable for a given boundary release.

        \returns: Up to the requested number of shape records, in descending point-count order.
     */
    public function largest(int $count   ///< The positive number of polygons to select.
                            ): array {
        $shapes = array_values($this->shapes);
        usort($shapes, static fn($a, $b) => ($b['vertices'] <=> $a['vertices']) ?: ($a['id'] <=> $b['id']));
        return array_slice($shapes, 0, $count);
    }

    /***********************************************************************************************************************/
    /**
        \brief This checks a point against one reference ring, using horizontal ray casting.

        We decode points in bounded blocks and carry the previous point across blocks. The last point is joined to
        the first, so a closing point need not be duplicated. Ring orientation does not change the result. Generated
        boundary probes are placed away from the boundary, where an exact edge/vertex result could be ambiguous.

        \returns: True, if the point is inside the ring; false, for an exterior point or a ring with fewer than three points.
     */
    public static function ringContains(string $ring,   ///< Packed longitude/latitude pairs, using native doubles.
                                        float $lng,     ///< The point's longitude, in degrees.
                                        float $lat      ///< The point's latitude, in degrees.
                                        ): bool {
        $bytes = strlen($ring);
        if ($bytes < 48) { return false; }
        $previous = unpack('dx/dy', $ring, $bytes - 16);
        $previousX = $previous['x'];
        $previousY = $previous['y'];
        $inside = false;
        for ($offset = 0; $offset < $bytes; $offset += 16384) {
            $count = min(2048, intdiv($bytes - $offset, 8));
            $coordinates = unpack('d'.$count, $ring, $offset);
            for ($i = 1; $i < $count; $i += 2) {
                $x = $coordinates[$i];
                $y = $coordinates[$i + 1];
                if (($previousY > $lat) !== ($y > $lat) &&
                    $lng < $previousX + ($x - $previousX) * ($lat - $previousY) / ($y - $previousY)) {
                    $inside = !$inside;
                }
                $previousX = $x;
                $previousY = $y;
            }
        }
        return $inside;
    }

    /***********************************************************************************************************************/
    /**
        \brief This checks a point against a complete source polygon, including its holes.

        The source domain rect rejects obvious misses. A remaining point must be inside the exterior ring and outside
        every hole. This preserves the GeoJSON reference even when a package implementation does not store holes.

        \returns: True, if the source polygon contains the point.
     */
    public static function contains(array $shape,   ///< A source shape record, including bounds and rings.
                                    float $lng,     ///< The point's longitude, in degrees.
                                    float $lat      ///< The point's latitude, in degrees.
                                    ): bool {
        $bounds = $shape['bounds'];
        if ($lng < $bounds['west'] || $lng > $bounds['east'] || $lat < $bounds['south'] || $lat > $bounds['north'] ||
            !self::ringContains($shape['rings'][0], $lng, $lat)) {
            return false;
        }
        // GeoJSON holes are part of the reference, even if a package implementation omits them.
        for ($i = 1; $i < count($shape['rings']); ++$i) {
            if (self::ringContains($shape['rings'][$i], $lng, $lat)) { return false; }
        }
        return true;
    }

    /***********************************************************************************************************************/
    /**
        \brief This finds all acceptable time zone names at a point, using the source geometry.

        Named zones take precedence over Etc/ ocean zones. More than one named zone may contain a point where
        source polygons overlap; any of those names is a valid generated expectation. No package lookup is performed.

        \returns: A sorted array of unique time zone names, or an empty array for a point outside source coverage.
     */
    public function zonesAt(float $lng, ///< The point's longitude, in degrees.
                            float $lat  ///< The point's latitude, in degrees.
                            ): array {
        $named = [];
        $ocean = [];
        foreach ($this->shapes as $shape) {
            if (!str_starts_with($shape['tzname'], 'Etc/') && self::contains($shape, $lng, $lat)) {
                $named[$shape['tzname']] = true;
            }
        }
        if (!empty($named)) {
            $zones = array_keys($named);
        } else {
            foreach ($this->shapes as $shape) {
                if (str_starts_with($shape['tzname'], 'Etc/') && self::contains($shape, $lng, $lat)) {
                    $ocean[$shape['tzname']] = true;
                }
            }
            $zones = array_keys($ocean);
        }
        sort($zones);
        return $zones;
    }

    /***********************************************************************************************************************/
    /**
        \brief This compares every stored polygon with its decoded source record.

        We check row counts, names, exterior-ring byte lengths and SHA-256 hashes. Domain rect coordinates allow a
        small tolerance for the schema's single-precision FLOAT storage. Hashes are calculated by MySQL, avoiding
        the memory cost of fetching all stored polygon blobs into PHP again.

        \returns: An array of failure descriptions. An empty array means all checks passed.
        \throws PDOException if the validation query fails.
     */
    public function validateStorage(PDO $admin,         ///< The demo's administrative PDO connection.
                                    string $database    ///< The generated, validated database name owned by this run.
                                    ): array {
        // Server-side hashes avoid transferring every large blob back to PHP just to check its encoding.
        $rows = $admin->query('SELECT id, tzname, OCTET_LENGTH(polygon) AS bytes, SHA2(polygon, 256) AS sha256,
            east + 0.0 AS east, west + 0.0 AS west, north + 0.0 AS north, south + 0.0 AS south
            FROM `'.$database.'`.timezones ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $failures = [];
        if (count($rows) !== count($this->shapes)) {
            $failures[] = 'Source/database polygon counts differ: '.count($this->shapes).' versus '.count($rows);
        }
        foreach ($rows as $row) {
            $source = $this->shapes[(int)$row['id']] ?? null;
            if (null === $source || $row['tzname'] !== $source['tzname'] ||
                (int)$row['bytes'] !== $source['bytes'] || $row['sha256'] !== $source['sha256']) {
                $failures[] = 'Stored geometry/name mismatch for polygon '.$row['id'].' ('.$row['tzname'].')';
                continue;
            }
            foreach ($source['bounds'] as $edge => $value) {
                // The schema stores bounding coordinates as single-precision FLOAT.
                if (abs((float)$row[$edge] - $value) > 0.00002) {
                    $failures[] = 'Stored '.$edge.' bound mismatch for polygon '.$row['id'];
                }
            }
        }
        return $failures;
    }

    /***********************************************************************************************************************/
    /**
        \brief This generates boundary and interior tests for the largest polygons.

        Boundary samples are spread around each exterior ring, with additional samples next to decoding-block
        transitions and the closing edge. For each usable edge, we offset a point to each side by approximately the
        requested distance. The local conversion adjusts longitude distance for latitude. Degenerate edges, edges
        crossing the date line, and offsets too close to a pole are skipped.

        We also select up to three interior points from a small grid over the domain rect. All expected names come
        from zonesAt(), using the complete source collection. A narrow or winding boundary may put both offset
        points in the same zone; the report counts actual crossings separately.

        \returns: Test records with title, params, expected, and kind. Boundary records also contain pair and side.
     */
    public function casesForLargest(int $count,             ///< The positive number of largest polygons to test.
                                    float $distanceKm = 5.0 ///< Approximate distance from each boundary, in kilometers.
                                    ): array {
        $cases = [];
        foreach ($this->largest($count) as $shape) {
            $ring = $shape['rings'][0];
            $vertices = $shape['vertices'];
            // Spread probes around the contour; test both sides approximately three miles from each edge.
            $indices = [];
            for ($sample = 0; $sample < 8; ++$sample) { $indices[] = (int)floor($sample * ($vertices - 1) / 8); }
            // Include edges adjoining decoding blocks and the closing edge, as well as contour-wide probes.
            $indices = array_unique(array_merge($indices, [1023, 1024, 2047, 2048, $vertices - 2]));
            foreach ($indices as $index) {
                if ($index >= $vertices - 1) { continue; }
                $a = unpack('dx/dy', $ring, $index * 16);
                $b = unpack('dx/dy', $ring, (($index + 1) % $vertices) * 16);
                $latitude = ($a['y'] + $b['y']) / 2;
                $cosine = cos(deg2rad($latitude));
                if (abs($cosine) < 0.02 || abs($b['x'] - $a['x']) > 180) { continue; }
                $dx = ($b['x'] - $a['x']) * $cosine;
                $dy = $b['y'] - $a['y'];
                $length = hypot($dx, $dy);
                if ($length === 0.0) { continue; }
                foreach ([-1, 1] as $side) {
                    $lng = ($a['x'] + $b['x']) / 2 - $side * $dy / $length * $distanceKm / (111.32 * $cosine);
                    $lat = $latitude + $side * $dx / $length * $distanceKm / 111.32;
                    while ($lng < -180) { $lng += 360; }
                    while ($lng > 180) { $lng -= 360; }
                    if (abs($lat) >= 89.9) { continue; }
                    $cases[] = [
                        'title' => $shape['tzname'].' polygon '.$shape['id'].' border vertex '.$index.' side '.$side,
                        'params' => ['lng' => $lng, 'lat' => $lat], 'expected' => $this->zonesAt($lng, $lat),
                        'kind' => 'generated boundary', 'pair' => $shape['id'].':'.$index, 'side' => $side,
                    ];
                }
            }
            $inside = 0;
            foreach ([0.37, 0.59, 0.73] as $x) {
                foreach ([0.37, 0.59, 0.73] as $y) {
                    $lng = $shape['bounds']['west'] + $x * ($shape['bounds']['east'] - $shape['bounds']['west']);
                    $lat = $shape['bounds']['south'] + $y * ($shape['bounds']['north'] - $shape['bounds']['south']);
                    if ($inside < 3 && self::contains($shape, $lng, $lat)) {
                        ++$inside;
                        $cases[] = ['title' => $shape['tzname'].' polygon '.$shape['id'].' interior '.$inside,
                            'params' => ['lng' => $lng, 'lat' => $lat], 'expected' => $this->zonesAt($lng, $lat),
                            'kind' => 'generated interior'];
                    }
                }
            }
        }
        return $cases;
    }
}

/***************************************************************************************************************************/
/**
    \brief This adds audit counters around the installed package's normal polygon reader.

    Loading, SQL, decoding, and lookup selection remain in the package. We count the rows and bytes actually yielded
    to the lookup algorithm, and record the row IDs encountered during generated calls. The package's documented
    primary-key order lets us associate yielded rows with the requested IDs without fetching another copy of the blobs.
*/
class DemoTrackedDatabase extends LGV_TZ_Lookup_Database {
    /***********************************************************************************************************************/
    /** \brief This is true while a generated test is being measured, and false for the known-location tests. */
    public bool $generatedCall = false;
    /***********************************************************************************************************************/
    /** \brief This counts polygon rows actually evaluated across all lookup calls. */
    public int $polygonRows = 0;
    /***********************************************************************************************************************/
    /** \brief This sums the bytes yielded across lookup calls; repeated evaluations are counted again. */
    public int $polygonBytes = 0;
    /***********************************************************************************************************************/
    /** \brief These row IDs were evaluated by generated calls, and are used to check largest-polygon coverage. */
    public array $generatedPolygons = [];

    /***********************************************************************************************************************/
    /**
        \brief This forwards the package's polygon iterator, recording each yielded row.

        The parent generator retains responsibility for cursor cleanup, including an early return after a match.

        \returns: A generator yielding the package's unchanged tzname/polygon rows.
     */
    public function get_tz_polygons($ids  ///< Existing row IDs returned by the package's domain-rect query.
                                    ) {
        // The package documents primary-key ordering for its polygon reader.
        $ordered = array_map('intval', $ids);
        sort($ordered, SORT_NUMERIC);
        $index = 0;
        foreach (parent::get_tz_polygons($ids) as $row) {
            ++$this->polygonRows;
            $this->polygonBytes += strlen($row['polygon']);
            if ($this->generatedCall) { $this->generatedPolygons[$ordered[$index]] = true; }
            ++$index;
            yield $row;
        }
    }
}

/***************************************************************************************************************************/
/**
    \brief This forwards parser events to the installed loader and observes the decoded source features.

    All parser events reach the package unchanged. When a Feature object closes, the loader's GeoJSON listener
    exposes that decoded Feature through getJson(). We retain its compact reference geometry before the next
    Feature replaces it. We do not build a second nested copy of the entire GeoJSON document.
*/
class DemoInspectingListener implements JsonStreamingParser\Listener\ListenerInterface {
    /***********************************************************************************************************************/
    /** \brief This is the installed package's listener, which performs the actual database load. */
    private LGV_TZ_Lookup_Loader $loader;
    /***********************************************************************************************************************/
    /** \brief This receives the decoded source polygons for independent validation. */
    private DemoSourceGeometry $source;
    /***********************************************************************************************************************/
    /** \brief This tracks object/array nesting; a Feature ends when nesting returns to level two. */
    private int $depth = 0;

    /***********************************************************************************************************************/
    /** \brief This connects the installed loader with the independent source collector. */
    public function __construct(LGV_TZ_Lookup_Loader $loader, ///< The initialized package listener to receive parser events.
                                DemoSourceGeometry $source ///< The collector to receive each complete source Feature.
                                ) {
        $this->loader = $loader;
        $this->source = $source;
    }
    /***********************************************************************************************************************/
    /** \brief This resets nesting and forwards the start-of-document event. */
    public function startDocument(): void { $this->depth = 0; $this->loader->startDocument(); }
    /***********************************************************************************************************************/
    /** \brief This forwards the end-of-document event. */
    public function endDocument(): void { $this->loader->endDocument(); }
    /***********************************************************************************************************************/
    /** \brief This records entry into an object, and forwards that event. */
    public function startObject(): void { ++$this->depth; $this->loader->startObject(); }
    /***********************************************************************************************************************/
    /** \brief This forwards a completed object, collecting source geometry when that object is a Feature. */
    public function endObject(): void {
        $this->loader->endObject();
        --$this->depth;
        if ($this->depth === 2) { $this->source->observe($this->loader->getJson()); }
    }
    /***********************************************************************************************************************/
    /** \brief This records entry into an array, and forwards that event. */
    public function startArray(): void { ++$this->depth; $this->loader->startArray(); }
    /***********************************************************************************************************************/
    /** \brief This forwards a completed array and updates nesting. */
    public function endArray(): void { $this->loader->endArray(); --$this->depth; }
    /***********************************************************************************************************************/
    /** \brief This forwards a JSON object key. */
    public function key(string $key ///< The decoded property name supplied by the parser.
                        ): void { $this->loader->key($key); }
    /***********************************************************************************************************************/
    /** \brief This forwards a JSON scalar value without modification. */
    public function value($value ///< The string, number, boolean, or null value supplied by the parser.
                            ): void { $this->loader->value($value); }
    /***********************************************************************************************************************/
    /** \brief This forwards whitespace reported by the parser. */
    public function whitespace(string $whitespace ///< The whitespace supplied by the parser.
                                ): void { $this->loader->whitespace($whitespace); }
}
