<?php

declare(strict_types=1);

// php -d memory_limit=1G tools/benchmark.php --fixture=/tmp/tz.sqlite --build
// php -d memory_limit=1G tools/benchmark.php --fixture=/tmp/tz.sqlite --runs=3
// php tools/benchmark.php --mysql=lgv_tz_benchmark_local --runs=3 --random=2000 --reconnect
// php tools/benchmark.php --pgsql=lgv_tz_benchmark_local --runs=3 --random=2000
$options = getopt('', ['fixture:', 'mysql:', 'pgsql:', 'source:', 'build', 'runs:', 'random:', 'results:', 'expect:', 'reconnect']);
if (count(array_intersect(['fixture', 'mysql', 'pgsql'], array_keys($options))) !== 1) {
    fwrite(STDERR, "Select one backend: --fixture=/path/to/isolated.sqlite, --mysql=lgv_tz_benchmark_NAME, or --pgsql=lgv_tz_benchmark_NAME\n");
    exit(2);
}
$source = $options['source'] ?? dirname(__DIR__).'/src';
require_once $source.'/Sources/LGV_TZ_Lookup_Query.class.php';
require_once dirname(__DIR__).'/tests/support/SQLiteDatabase.php';

if (isset($options['fixture']) && !isset($options['build']) && !is_file($options['fixture'])) {
    fwrite(STDERR, "Build the isolated fixture first with --build.\n");
    exit(2);
}
$createDatabase = static function() use ($options) {
    if (isset($options['mysql']) || isset($options['pgsql'])) {
        $driver = isset($options['pgsql']) ? 'pgsql' : 'mysql';
        if (!preg_match('/^lgv_tz_benchmark_[a-zA-Z0-9_]+$/D', $options[$driver])) {
            throw new InvalidArgumentException('Use a separate database named lgv_tz_benchmark_NAME.');
        }
        return new LGV_TZ_Lookup_Database($options[$driver],
            getenv('LGV_TZ_BENCH_USER') ?: ($driver === 'pgsql' ? (getenv('USER') ?: get_current_user()) : 'root'),
            getenv('LGV_TZ_BENCH_PASSWORD') ?: '', $driver, getenv('LGV_TZ_BENCH_HOST') ?: 'localhost',
            (int)(getenv('LGV_TZ_BENCH_PORT') ?: ($driver === 'pgsql' ? 5432 : 3306)));
    }
    return new SQLiteDatabase($options['fixture']);
};
$database = $createDatabase();
if (isset($options['build'])) {
    require_once $source.'/Sources/LGV_TZ_Lookup_Loader.class.php';
    $stream = fopen($source.'/combined-with-oceans.json', 'rb');
    if (!$stream) {
        throw new RuntimeException('Boundary file unavailable.');
    }
    $start = hrtime(true);
    $listener = new LGV_TZ_Lookup_Loader($database);
    // One local transaction keeps disk commits out of the PHP loading comparison.
    if ($database instanceof SQLiteDatabase) {
        $database->pdo_instance->connection->beginTransaction();
    }
    try {
        $parser = new JsonStreamingParser\Parser($stream, $listener);
        $parser->parse();
        if ($database instanceof SQLiteDatabase) {
            $database->pdo_instance->connection->commit();
        }
    } finally {
        fclose($stream);
    }
    echo json_encode([
        'mode' => 'load', 'seconds' => (hrtime(true) - $start) / 1e9,
        'peak_php_mib' => memory_get_peak_usage() / 1048576,
        'polygons' => $database->pdo_instance->preparedStatement('SELECT COUNT(*) AS count FROM timezones', [], true)[0]['count'],
        'polygon_bytes' => $database->pdo_instance->preparedStatement('SELECT SUM(LENGTH(polygon)) AS bytes FROM timezones', [], true)[0]['bytes'],
    ], JSON_PRETTY_PRINT)."\n";
    exit;
}

require dirname(__DIR__).'/src/TestLocations.php';
$cases = $test_locations_param_array;
mt_srand(20261007);
for ($i = 0; $i < (int)($options['random'] ?? 0); ++$i) {
    $cases[] = ['title' => 'Random '.$i, 'params' => [
        'lng' => mt_rand(-180000000, 180000000) / 1e6,
        'lat' => mt_rand(-90000000, 90000000) / 1e6,
    ]];
}
$lookup = new LGV_TZ_Lookup_Query($database);
$expectedResults = isset($options['expect']) ? json_decode(file_get_contents($options['expect']), true, 512, JSON_THROW_ON_ERROR) : null;
$times = [];
$results = [];
$failures = [];
$maxExtra = 0;
$maxUsage = 0;
$maxReserved = 0;
$slowest = [];
$start = hrtime(true);
for ($run = 0; $run < max(1, (int)($options['runs'] ?? 3)); ++$run) {
    foreach ($cases as $caseIndex => $case) {
        $longitude = $case['params']['lng'];
        $latitude = $case['params']['lat'];
        while ($longitude < -180) { $longitude += 360; }
        while ($longitude > 180) { $longitude -= 360; }
        while ($latitude < -90) { $latitude += 180; }
        while ($latitude > 90) { $latitude -= 180; }
        if (function_exists('memory_reset_peak_usage')) { memory_reset_peak_usage(); }
        $memory = memory_get_usage();
        $caseStart = hrtime(true);
        if (isset($options['reconnect'])) {
            $database = $createDatabase();
            $lookup = new LGV_TZ_Lookup_Query($database);
        }
        $result = $lookup->get_tz($longitude, $latitude);
        $elapsed = (hrtime(true) - $caseStart) / 1e6;
        $times[] = $elapsed;
        $maxExtra = max($maxExtra, memory_get_peak_usage() - $memory);
        $maxUsage = max($maxUsage, memory_get_peak_usage());
        $maxReserved = max($maxReserved, memory_get_peak_usage(true));
        if ($run === 0) {
            $results[] = $result;
            $slowest[] = ['title' => $case['title'], 'milliseconds' => $elapsed];
            if (isset($case['result']) && $case['result'] !== $result) {
                $failures[] = ['title' => $case['title'], 'expected' => $case['result'], 'actual' => $result];
            }
        } elseif ($results[$caseIndex] !== $result) {
            throw new RuntimeException('Result changed between runs: '.$case['title']);
        }
    }
}
$seconds = (hrtime(true) - $start) / 1e9;
sort($times);
usort($slowest, static fn($a, $b) => $b['milliseconds'] <=> $a['milliseconds']);
if (isset($options['results'])) {
    file_put_contents($options['results'], json_encode($results, JSON_PRETTY_PRINT)."\n");
}
echo json_encode([
    'mode' => 'query', 'backend' => isset($options['pgsql']) ? 'pgsql' : (isset($options['mysql']) ? 'mysql' : 'sqlite'),
    'reconnect' => isset($options['reconnect']),
    'cases' => count($cases), 'lookups' => count($times), 'seconds' => $seconds,
    'mean_ms' => array_sum($times) / count($times), 'median_ms' => $times[(int)floor(count($times) * .5)],
    'p95_ms' => $times[min(count($times) - 1, (int)floor(count($times) * .95))],
    'max_ms' => max($times), 'max_extra_php_mib' => $maxExtra / 1048576,
    'peak_php_mib' => $maxUsage / 1048576, 'peak_reserved_php_mib' => $maxReserved / 1048576,
    'statements' => $database instanceof SQLiteDatabase ? $database->pdo_instance->statements : null,
    'failures' => $failures, 'slowest' => array_slice($slowest, 0, 5),
], JSON_PRETTY_PRINT)."\n";
if (null !== $expectedResults && $expectedResults !== $results) {
    fwrite(STDERR, "Results differ from the supplied baseline.\n");
    exit(1);
}
exit(empty($failures) ? 0 : 1);
