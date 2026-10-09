<?php

declare(strict_types=1);

// Run against a clean consumer installation to verify the library's actual Composer autoload paths.
$options = getopt('', ['autoload:', 'with-loader', 'mysql:']);
$autoload = $options['autoload'] ?? dirname(__DIR__).'/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Install Composer dependencies first, or supply --autoload=/path/to/vendor/autoload.php.');
}
$checks = 0;
function composerCheck($expected, $actual, string $message): void {
    global $checks;
    ++$checks;
    if ($expected !== $actual) {
        throw new RuntimeException($message.': expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}
ob_start();
require $autoload;
composerCheck('', ob_get_clean(), 'Autoloading has no output');
// Load PDO first: it must work independently of the database class or a server-specific constant.
ob_start();
foreach (['LGV_TZ_Lookup_PDO', 'LGV_TZ_Lookup_Entity', 'LGV_TZ_Lookup_Database', 'LGV_TZ_Lookup_Query'] as $class) {
    composerCheck(true, class_exists($class), 'Composer autoloads '.$class);
}
composerCheck('', ob_get_clean(), 'Loading library classes has no output');
composerCheck(false, class_exists('LGV_TZ_Lookup_Loader', false), 'Lookup does not eagerly load the boundary loader');
composerCheck(false, class_exists('JsonStreamingParser\\Parser', false), 'Lookup does not eagerly load the JSON parser');

$connection = new PDO('sqlite::memory:');
$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$connection->exec('CREATE TABLE timezones (
    id INTEGER PRIMARY KEY, tzname TEXT, east REAL, west REAL, north REAL, south REAL, polygon BLOB
)');
$insert = $connection->prepare('INSERT INTO timezones VALUES (?, ?, 4, 0, 4, 0, ?)');
foreach ([
    [1, 'America/New_York', [0, 0, 4, 0, 4, 1, 0, 1, 0, 0]],
    [2, 'America/Chicago', [0, 0, 4, 0, 4, 4, 0, 4, 0, 0]],
    [3, 'Etc/GMT', [0, 0, 4, 0, 4, 4, 0, 4, 0, 0]],
] as [$id, $zone, $points]) {
    $insert->bindValue(1, $id, PDO::PARAM_INT);
    $insert->bindValue(2, $zone, PDO::PARAM_STR);
    $insert->bindValue(3, pack('d*', ...$points), PDO::PARAM_LOB);
    $insert->execute();
}
$wrapper = (new ReflectionClass(LGV_TZ_Lookup_PDO::class))->newInstanceWithoutConstructor();
$connectionProperty = new ReflectionProperty(LGV_TZ_Lookup_PDO::class, '_pdo');
if (PHP_VERSION_ID < 80100) { $connectionProperty->setAccessible(true); }
$connectionProperty->setValue($wrapper, $connection);
$wrapper->driver_type = 'sqlite';
$database = (new ReflectionClass(LGV_TZ_Lookup_Database::class))->newInstanceWithoutConstructor();
$database->pdo_instance = $wrapper;
$lookup = new LGV_TZ_Lookup_Query($database);
composerCheck('America/Chicago', $lookup->get_tz(2, 2), 'Installed package decodes candidate polygons');
composerCheck('', $lookup->get_tz(10, 10), 'Installed package returns empty for no match');
$connection->exec('DELETE FROM timezones WHERE id = 2');
$connection->exec("INSERT INTO timezones SELECT 4, 'America/Los_Angeles', east, west, north, south, polygon FROM timezones WHERE id = 1");
composerCheck('Etc/GMT', $lookup->get_tz(2, 2), 'Installed package handles ocean fallback');

if (isset($options['with-loader'])) {
    $loaded = new class {
        public array $entities = [];
        public int $resets = 0;
        public function reset_database() { ++$this->resets; }
        public function store_entity($entity) { $this->entities[] = $entity; }
    };
    $listener = new LGV_TZ_Lookup_Loader($loaded);
    $stream = fopen('php://memory', 'w+b');
    fwrite($stream, json_encode([
        'type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'properties' => ['tzid' => 'Etc/GMT'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]]]],
        ]],
    ], JSON_THROW_ON_ERROR));
    rewind($stream);
    try {
        (new JsonStreamingParser\Parser($stream, $listener))->parse();
    } finally {
        fclose($stream);
    }
    composerCheck(1, $loaded->resets, 'Optional loader initializes its database');
    composerCheck(1, count($loaded->entities), 'Optional loader parses a GeoJSON feature');
    composerCheck('Etc/GMT', $loaded->entities[0]->tzID, 'Optional loader retains the timezone');
    composerCheck(['east' => 4, 'west' => 0, 'north' => 4, 'south' => 0], $loaded->entities[0]->domainRect, 'Optional loader computes bounds');
} else {
    composerCheck(false, class_exists(JsonStreamingParser\Listener\GeoJsonListener::class), 'Lookup installation has no parser dependency');
    try {
        class_exists('LGV_TZ_Lookup_Loader');
        throw new RuntimeException('Expected an optional-loader dependency exception.');
    } catch (RuntimeException $error) {
        composerCheck(true, str_contains($error->getMessage(), 'composer require salsify/json-streaming-parser'), 'Missing parser gives installation guidance');
    }
}

if (isset($options['mysql'])) {
    if (!preg_match('/^lgv_tz_benchmark_[a-zA-Z0-9_]+$/D', $options['mysql'])) {
        throw new InvalidArgumentException('Use an isolated lgv_tz_benchmark_NAME database.');
    }
    $timeLimit = ini_get('max_execution_time');
    $database = new LGV_TZ_Lookup_Database($options['mysql'], getenv('LGV_TZ_BENCH_USER') ?: 'root',
        getenv('LGV_TZ_BENCH_PASSWORD') ?: '', 'mysql', getenv('LGV_TZ_BENCH_HOST') ?: 'localhost',
        (int)(getenv('LGV_TZ_BENCH_PORT') ?: 3306));
    composerCheck($timeLimit, ini_get('max_execution_time'), 'Library connection preserves the application time limit');
    $lookup = new LGV_TZ_Lookup_Query($database);
    require __DIR__.'/../src/TestLocations.php';
    foreach ($test_locations_param_array as $case) {
        $lng = $case['params']['lng'];
        $lat = $case['params']['lat'];
        while ($lng < -180) { $lng += 360; }
        while ($lng > 180) { $lng -= 360; }
        while ($lat < -90) { $lat += 180; }
        while ($lat > 90) { $lat -= 180; }
        composerCheck($case['result'], $lookup->get_tz($lng, $lat), $case['title']);
    }
}
echo 'Passed '.$checks." Composer integration checks.\n";
