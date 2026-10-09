<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/Sources/LGV_TZ_Lookup_Query.class.php';
require_once __DIR__.'/support/SQLiteDatabase.php';

$checks = 0;
function check($expected, $actual, string $label): void {
    global $checks;
    ++$checks;
    if ($expected !== $actual) {
        throw new RuntimeException($label.': expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

// The pre-optimization winding test is an independent reference for edge/vertex behavior.
function referenceContains(array $point, array $polygon): bool {
    $winding = 0;
    $count = count($polygon);
    for ($i = 0; $i < $count; ++$i) {
        $a = $polygon[$i];
        $b = $polygon[($i + 1) % $count];
        $side = ($b[1] - $a[1]) * ($point[0] - $a[0]) - ($point[1] - $a[1]) * ($b[0] - $a[0]);
        if ($a[0] <= $point[0]) {
            if ($b[0] > $point[0] && $side > 0) { ++$winding; }
        } elseif ($b[0] <= $point[0] && $side < 0) {
            --$winding;
        }
    }
    return $winding !== 0;
}

function packedRing(array $ring): string {
    $binary = '';
    foreach ($ring as $point) {
        $binary .= pack('d2', $point[0], $point[1]);
    }
    return $binary;
}

$packedTest = new ReflectionMethod(LGV_TZ_Lookup_Query::class, '_wn_PackedPoly');
if (PHP_VERSION_ID < 80100) { $packedTest->setAccessible(true); }
$rings = [[], [[1.0, 2.0]], [[0.0, 0.0], [2.0, 2.0]],
    [[0, 0], [4, 0], [4, 4], [0, 4]],
    [[0, 0], [4, 0], [4, 4], [2, 2], [0, 4], [0, 0]],
    [[0, 0], [4, 4], [0, 4], [4, 0], [0, 0]],
    [[0, 0], [0, 0], [4, 0], [4, 4], [0, 4], [0, 0]],
];
// Cross decoding-block boundaries, with closed/open rings and either orientation.
foreach ([1023, 1024, 1025, 2048, 2049, 8193] as $vertices) {
    $ring = [];
    for ($i = 0; $i < $vertices; ++$i) {
        $angle = 2 * M_PI * $i / $vertices;
        $radius = ($i % 2) ? 4 : 2;
        $ring[] = [round(cos($angle) * $radius, 6), round(sin($angle) * $radius, 6)];
    }
    $rings[] = $ring;
    $ring[] = $ring[0];
    $rings[] = $ring;
}
mt_srand(20261007);
foreach ($rings as $ring) {
    foreach ([$ring, array_reverse($ring)] as $oriented) {
        $packed = packedRing($oriented);
        $points = [[0, 0], [1, 1], [4, 4], [-4, -4], [0, 4], [4, 0]];
        foreach ([0, 1, 1022, 1023, 1024, count($oriented) - 1] as $index) {
            if (isset($oriented[$index])) {
                $points[] = $oriented[$index];
                $points[] = [$oriented[$index][0] + 1e-7, $oriented[$index][1] - 1e-7];
                if (isset($oriented[$index + 1])) {
                    $points[] = [($oriented[$index][0] + $oriented[$index + 1][0]) / 2,
                        ($oriented[$index][1] + $oriented[$index + 1][1]) / 2];
                }
            }
        }
        for ($i = 0; $i < 100; ++$i) {
            $points[] = [mt_rand(-5000000, 5000000) / 1e6, mt_rand(-5000000, 5000000) / 1e6];
        }
        foreach ($points as $point) {
            check(referenceContains($point, $oriented), $packedTest->invoke(null, $point[0], $point[1], $packed),
                'Winding result with '.count($oriented).' vertices');
        }
    }
}

class TrackingDatabase extends SQLiteDatabase {
    public array $polygonRequests = [];
    public function get_tz_polygons($ids) {
        $this->polygonRequests[] = $ids;
        yield from parent::get_tz_polygons($ids);
    }
}
$database = new TrackingDatabase(':memory:');
$database->reset_database();
$lookup = new LGV_TZ_Lookup_Query($database);
check('', $lookup->get_tz(0, 0), 'Empty database');
check([], $database->polygonRequests, 'No empty polygon queries');
$rect = ['east' => 4, 'west' => 0, 'north' => 4, 'south' => 0];
$miss = [[0, 0], [4, 0], [4, 1], [0, 1], [0, 0]];
$hit = [[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]];
$database->store_entity(new LGV_TZ_Lookup_Entity('Region/First', $rect, $miss));
check('Region/First', $lookup->get_tz(2, 2), 'Existing single-named-candidate shortcut');
$database->store_entity(new LGV_TZ_Lookup_Entity('Region/Second', $rect, $miss));
$database->store_entity(new LGV_TZ_Lookup_Entity('Etc/GMT', $rect, $hit));
$database->polygonRequests = [];
check('Etc/GMT', $lookup->get_tz(2, 2), 'Ocean fallback');
check([[1, 2], [3]], $database->polygonRequests, 'Fetch each candidate only once');
$database->store_entity(new LGV_TZ_Lookup_Entity('Region/Third', $rect, $hit));
check('Region/Third', $lookup->get_tz(2, 2), 'Named zone takes precedence over oceans');
$database->store_entity(new LGV_TZ_Lookup_Entity('Region/Fourth', $rect, $hit));
check('Region/Third', $lookup->get_tz(2, 2), 'Primary-key first-match precedence');
check('', $lookup->get_tz(10, 10), 'No bounding-box candidates');
check([], iterator_to_array($database->get_tz_polygons([])), 'Empty polygon iterator');
$decoded = $database->get_tz_entities([3]);
check('Etc/GMT', $decoded[0]['tzname'], 'Legacy decoded-entity API');
check($hit, array_map(static fn($p) => array_map('intval', $p), $decoded[0]['polygon']), 'Existing binary format');

// Exercise the production PDO reader with an isolated PDO connection, including early cursor cleanup.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$wrapper = (new ReflectionClass(LGV_TZ_Lookup_PDO::class))->newInstanceWithoutConstructor();
$connectionProperty = new ReflectionProperty(LGV_TZ_Lookup_PDO::class, '_pdo');
if (PHP_VERSION_ID < 80100) { $connectionProperty->setAccessible(true); }
$connectionProperty->setValue($wrapper, $pdo);
$wrapper->driver_type = 'sqlite';
$pdo->beginTransaction();
check([['value' => 1]], $wrapper->preparedStatement('SELECT 1 AS value', [], true), 'Read API');
check(true, $pdo->inTransaction(), 'Read leaves a caller transaction open');
$rows = $wrapper->preparedRows('SELECT 1 AS value UNION ALL SELECT 2');
foreach ($rows as $row) { break; }
unset($rows);
check([['value' => 3]], $wrapper->preparedStatement('SELECT 3 AS value', [], true), 'Query after early return');
$pdo->rollBack();
check(true, $wrapper->preparedStatement('CREATE TABLE write_test (value INTEGER)'), 'Write API return value');
$wrapper->preparedStatement('INSERT INTO write_test VALUES (?)', [7]);
check([['value' => 7]], $wrapper->preparedStatement('SELECT value FROM write_test', [], true), 'Write commits successfully');
try {
    iterator_to_array($wrapper->preparedRows('SELECT * FROM missing_table'));
    throw new RuntimeException('Expected a database exception.');
} catch (Exception $error) {
    check(true, $error->getPrevious() instanceof PDOException, 'Preserve database exception context');
}

$options = getopt('', ['mysql:']);
if (isset($options['mysql'])) {
    if (!preg_match('/^lgv_tz_benchmark_[a-zA-Z0-9_]+$/D', $options['mysql'])) {
        throw new InvalidArgumentException('Use an isolated lgv_tz_benchmark_NAME database.');
    }
    $mysqlWrapper = new LGV_TZ_Lookup_PDO($options['mysql'], getenv('LGV_TZ_BENCH_USER') ?: 'root',
        getenv('LGV_TZ_BENCH_PASSWORD') ?: '', 'mysql', getenv('LGV_TZ_BENCH_HOST') ?: 'localhost',
        (int)(getenv('LGV_TZ_BENCH_PORT') ?: 3306));
    $mysqlConnection = $connectionProperty->getValue($mysqlWrapper);
    $attribute = class_exists('Pdo\\Mysql') ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
    $originalBuffering = $mysqlConnection->getAttribute($attribute);
    $rows = $mysqlWrapper->preparedRows('SELECT 1 AS value UNION ALL SELECT 2', [], false);
    $rows->rewind();
    check(false, $mysqlConnection->getAttribute($attribute), 'Unbuffered MySQL reader');
    unset($rows);
    check($originalBuffering, $mysqlConnection->getAttribute($attribute), 'Restore buffering after early return');
    check(3, (int)$mysqlWrapper->preparedStatement('SELECT 3 AS value', [], true)[0]['value'], 'MySQL query after early return');
    try {
        iterator_to_array($mysqlWrapper->preparedRows('SELECT * FROM lgv_tz_nonexistent_table', [], false));
        throw new RuntimeException('Expected a MySQL exception.');
    } catch (Exception $error) {
        check(true, $error->getPrevious() instanceof PDOException, 'MySQL exception context');
    }
    check($originalBuffering, $mysqlConnection->getAttribute($attribute), 'Restore buffering after database exception');
    $mysqlConnection->beginTransaction();
    $mysqlWrapper->preparedStatement('SELECT 1', [], true);
    check(true, $mysqlConnection->inTransaction(), 'MySQL read leaves caller transaction open');
    $mysqlConnection->rollBack();
    check(true, $mysqlWrapper->preparedStatement('CREATE TEMPORARY TABLE lgv_tz_write_test (value INTEGER)'), 'MySQL write API');
    $mysqlWrapper->preparedStatement('INSERT INTO lgv_tz_write_test VALUES (?)', [7]);
    check(7, (int)$mysqlWrapper->preparedStatement('SELECT value FROM lgv_tz_write_test', [], true)[0]['value'], 'MySQL write commits successfully');
}
echo 'Passed '.$checks." regression checks.\n";
