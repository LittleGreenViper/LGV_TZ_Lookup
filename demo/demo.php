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
    \brief This is the PHP worker for the command-line Composer demo.

    Call demo/run.sh to run the demo. The shell creates a temporary working directory, and calls this file in either
    "run" or "cleanup" mode. In "run" mode, we install the released package through Composer, load the latest boundary
    data, check the resulting database, run location tests, and display metrics. The lookup classes come from the
    temporary Composer application, so this is a test of the installed package.

    The separate "cleanup" invocation lets the shell remove the database and downloads, even if the loader's PHP
    process runs out of memory. The shell removes the remaining working directory after database cleanup succeeds.
*/

declare(strict_types=1);
require_once dirname(__DIR__).'/tools/Setup.php';

/***************************************************************************************************************************/
/** \brief This is the package installed into the temporary Composer application. */
const DEMO_PACKAGE = 'littlegreenviper/lgv_tz_lookup';
/***************************************************************************************************************************/
/** \brief This GitHub endpoint identifies the latest published Timezone Boundary Builder release. */
const DEMO_RELEASE_API = 'https://api.github.com/repos/evansiroky/timezone-boundary-builder/releases/latest';

/***************************************************************************************************************************/
/**
    \brief These are the measurements collected by the worker.

    "started" contains a monotonic timestamp in nanoseconds. "timings" contains phase durations in seconds.
    The lookup arrays contain individual call durations in milliseconds. Fields ending in "bytes" describe data sizes
    or PHP memory usage. "largest" contains only the small description records needed for the final report.

    We retain measurements from completed phases, so a failed run can still print the information available so far.
*/
$demoMetrics = ['started' => hrtime(true), 'timings' => [], 'lookup_ms' => [], 'known_lookup_ms' => [], 'generated_lookup_ms' => [],
    'lookup_extra_bytes' => 0, 'lookup_peak_bytes' => 0, 'phase_memory_available' => function_exists('memory_reset_peak_usage')];

/***************************************************************************************************************************/
/**
    \brief This records the elapsed time for one completed phase.
*/
function demoTiming(string $label,   ///< The label displayed in the metrics report.
                    $start          ///< The phase's starting hrtime(true) value, in nanoseconds.
                    ): void {
    global $demoMetrics;
    $demoMetrics['timings'][$label] = (hrtime(true) - $start) / 1e9;
}

/***************************************************************************************************************************/
/**
    \brief This starts a new peak-memory measurement, when the PHP version supports it.

    PHP 8.2 and later can reset the peak between phases and lookup calls. Earlier versions leave the lifetime peak
    intact; the report labels those measurements accordingly.
*/
function demoResetMemoryPeak(): void {
    if (function_exists('memory_reset_peak_usage')) { memory_reset_peak_usage(); }
}

/***************************************************************************************************************************/
/**
    \brief This formats the timing statistics for one group of lookup calls.

    We report the arithmetic mean, the median, the nearest-rank 95th percentile, and the slowest call.

    \returns: A printable string. An empty group reports that no lookup measurements are available.
*/
function demoLatency(array $values   ///< Individual lookup durations, in milliseconds.
                    ): string {
    sort($values);
    $count = count($values);
    if ($count === 0) { return 'No lookups completed'; }
    $median = ($values[(int)floor(($count - 1) / 2)] + $values[(int)floor($count / 2)]) / 2;
    $p95 = $values[max(0, (int)ceil($count * 0.95) - 1)];
    return sprintf('mean %.3f ms; median %.3f ms; p95 %.3f ms; max %.3f ms', array_sum($values) / $count, $median, $p95, max($values));
}

/***************************************************************************************************************************/
/**
    \brief This prints the collected durations, data sizes, memory measurements, and test coverage.

    Lookup timings cover calls to the package API, using an existing connection. They exclude downloading, loading,
    and reference-geometry calculations. PHP memory measurements describe this worker, and do not include the database server
    or the separate Composer process. Large-polygon audit counters show which geometry was actually evaluated.
*/
function demoPrintMetrics(): void {
    global $demoMetrics;
    echo "\nMetrics\n";
    foreach ($demoMetrics['timings'] as $label => $seconds) {
        printf("  %-35s %9.2f s\n", $label, $seconds);
    }
    printf("  %-35s %9.2f s\n", 'Total before cleanup', (hrtime(true) - $demoMetrics['started']) / 1e9);
    foreach (['download_bytes' => 'Compressed download', 'extracted_bytes' => 'Extracted GeoJSON',
        'database_bytes' => 'Stored polygon payload',
        'load_peak_bytes' => $demoMetrics['phase_memory_available'] ? 'PHP peak: load + source capture' : 'PHP lifetime peak after loading',
        'reference_peak_bytes' => $demoMetrics['phase_memory_available'] ? 'PHP peak: independent validation' : 'PHP lifetime peak after validation'] as $field => $label) {
        if (isset($demoMetrics[$field])) { printf("  %-35s %9.2f MiB\n", $label, $demoMetrics[$field] / 1048576); }
    }
    if (!empty($demoMetrics['lookup_ms'])) {
        echo '  All lookup calls:      '.demoLatency($demoMetrics['lookup_ms'])."\n";
        echo '  Known-location calls:  '.demoLatency($demoMetrics['known_lookup_ms'])."\n";
        echo '  Largest-shape calls:   '.demoLatency($demoMetrics['generated_lookup_ms'])."\n";
        printf("  Lookup throughput (API time only): %.1f calls/s\n", count($demoMetrics['lookup_ms']) * 1000 / array_sum($demoMetrics['lookup_ms']));
        if ($demoMetrics['phase_memory_available']) {
            printf("  Peak extra PHP memory per lookup: %.2f MiB (total peak %.2f MiB)\n", $demoMetrics['lookup_extra_bytes'] / 1048576, $demoMetrics['lookup_peak_bytes'] / 1048576);
        } else {
            echo "  Per-phase memory measurement requires PHP 8.2 or later.\n";
        }
    }
    if (isset($demoMetrics['largest'])) {
        if (isset($demoMetrics['polygon_rows'])) {
            printf("  Actual polygon rows evaluated: %d (%.2f MiB processed across lookup calls)\n", $demoMetrics['polygon_rows'], $demoMetrics['polygon_bytes_processed'] / 1048576);
            printf("  Largest polygons decoded in generated tests: %d/%d\n", $demoMetrics['largest_decoded'], count($demoMetrics['largest']));
        }
        if (isset($demoMetrics['boundary_pairs'])) {
            printf("  Boundary pairs with distinct source zones: %d/%d (%d named-to-named)\n", $demoMetrics['crossing_pairs'], $demoMetrics['boundary_pairs'], $demoMetrics['named_pairs']);
        }
        echo "  Largest polygons tested (ranked by vertex count):\n";
        foreach ($demoMetrics['largest'] as $shape) {
            printf("    %-28s id %-5d %8d vertices %6.2f MiB\n", $shape['tzname'], $shape['id'], $shape['vertices'], $shape['bytes'] / 1048576);
        }
    }
    if (isset($demoMetrics['lookup_ms']) && !empty($demoMetrics['lookup_ms'])) {
        echo "  Reference geometry is freed before measuring package lookup memory.\n";
    }
    echo "  PHP memory describes this worker; the database server and Composer run separately.\n\n";
}

/***************************************************************************************************************************/
/**
    \brief This reads a demo setting from the environment.

    The "LGV_TZ_DEMO_" prefix is added to the provided name. An explicitly set empty value is retained, which is
    useful for a MySQL user with an empty password.

    \returns: The environment value, or the supplied default when the variable is not set.
*/
function demoEnvironment(string $name,    ///< The setting name, without the LGV_TZ_DEMO_ prefix.
                        string $default ///< The value to use when the setting is absent.
                        ): string {
    $value = getenv('LGV_TZ_DEMO_'.$name);
    return false === $value ? $default : $value;
}

/***************************************************************************************************************************/
/**
    \brief This validates the selected database driver.
    \returns: "mysql" (the default) or "pgsql".
    \throws RuntimeException if another driver is requested.
*/
function demoDriver(): string {
    $driver = strtolower(demoEnvironment('DRIVER', 'mysql'));
    if (!in_array($driver, ['mysql', 'pgsql'], true)) {
        throw new RuntimeException('LGV_TZ_DEMO_DRIVER must be mysql or pgsql.');
    }
    return $driver;
}

/***************************************************************************************************************************/
/**
    \brief This opens an administrative or test-database connection for the selected backend.
    Connection settings come from DRIVER, HOST, PORT, USER, PASSWORD, and ADMIN_DATABASE. PostgreSQL administration
    connects to the existing "postgres" database by default; loaded data always goes into a separate temporary database.
    \returns: A PDO connection with exception reporting enabled.
    \throws RuntimeException if the configured port is invalid.
    \throws PDOException if the connection fails.
*/
function demoConnection(?string $database = null, ///< Test database, or NULL for the administrative database.
                        ?string $driver = null   ///< Recorded cleanup driver, or NULL for the environment setting.
                        ): PDO {
    $driver = $driver ?? demoDriver();
    $port = filter_var(demoEnvironment('PORT', $driver === 'pgsql' ? '5432' : '3306'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if (false === $port) {
        throw new RuntimeException('LGV_TZ_DEMO_PORT must be between 1 and 65535.');
    }
    $dsn = $driver.':host='.demoEnvironment('HOST', 'localhost').';port='.$port;
    if ($driver === 'pgsql') {
        $dsn .= ';dbname='.($database ?? demoEnvironment('ADMIN_DATABASE', 'postgres')).';options=--client_encoding=UTF8';
    } else {
        $dsn .= ';charset=utf8mb4'.($database === null ? '' : ';dbname='.$database);
    }
    return new PDO($dsn, demoEnvironment('USER', $driver === 'pgsql' ? (getenv('USER') ?: get_current_user()) : 'root'),
        demoEnvironment('PASSWORD', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/***************************************************************************************************************************/
/**
    \brief This downloads one HTTPS resource directly to a file.

    Redirects must also use HTTPS. We stream the response to disk, so the large boundary archive is never read into
    a single PHP string. A partial download remains in the working directory for the shell's cleanup to remove.

    \throws RuntimeException if the destination cannot be opened, or the transfer fails.
*/
function demoDownload(string $url,           ///< The HTTPS resource to download.
                    string $destination     ///< The local filename to create in the demo's working directory.
                    ): void {
    LGV_TZ_Lookup_Setup::download($url, $destination);
}

/***************************************************************************************************************************/
/**
    \brief This extracts the archive's single GeoJSON file into a controlled local filename.

    The ZIP may contain other files, but must contain exactly one .json or .geojson entry. We stream that entry into
    our own destination, instead of using paths from the archive. The extracted size must match the ZIP entry's size.

    \throws RuntimeException if the archive cannot be read, its contents are unexpected, or extraction fails.
*/
function demoExtract(string $archive,        ///< The downloaded boundary ZIP file.
                    string $destination     ///< The local GeoJSON filename to create.
                    ): void {
    LGV_TZ_Lookup_Setup::extract($archive, $destination);
}

/***************************************************************************************************************************/
/**
    \brief This builds the temporary Composer application and loads its autoloader.

    We download and verify Composer, write the application's composer.json, and install the published lookup
    package and its optional JSON parser. Composer's home and cache are placed in the working directory, as well.
    Installation does not execute package scripts or plugins, and does not change the user's installed Composer.

    \throws RuntimeException if checksum verification or Composer installation fails.
*/
function demoInstall(string $directory  ///< The demo's temporary application directory.
                    ): void {
    LGV_TZ_Lookup_Setup::install($directory, demoDriver(), demoEnvironment('PACKAGE_PATH', ''), demoEnvironment('PACKAGE_VERSION', '^1.4'));
}

/***************************************************************************************************************************/
/**
    \brief This removes the compressed boundary archive and the extracted GeoJSON.

    We call this as soon as loading finishes, and again during cleanup. Already-removed files are harmless.

    \throws RuntimeException if a remaining boundary file cannot be removed.
*/
function demoRemoveDownloads(string $directory   ///< The working directory containing the downloaded files.
                            ): void {
    foreach (['boundaries.zip', 'boundaries.json'] as $file) {
        if (is_file($directory.'/'.$file) && !unlink($directory.'/'.$file)) {
            throw new RuntimeException('Could not delete '.$directory.'/'.$file);
        }
    }
}

/***************************************************************************************************************************/
/**
    \brief This removes the downloads and drops the database owned by this demo run.

    The database name comes from database.json, and must match the generated demo naming pattern. We delete large
    files before connecting to the database server, so they are removed even when the server is unavailable. If dropping the
    database fails, its cleanup record remains for recovery. The shell removes the working directory only on success.

    \returns: True, if cleanup succeeded, or no database had been created.
    \throws RuntimeException if a cleanup record is unexpected, or a file cannot be removed.
    \throws PDOException if database cleanup fails.
*/
function demoCleanup(string $directory  ///< The working directory owned by this demo run.
                    ): bool {
    $start = hrtime(true);
    // Delete the large files even if the database server is temporarily unavailable during cleanup.
    demoRemoveDownloads($directory);
    $state = $directory.'/database.json';
    if (!is_file($state)) {
        printf("Cleanup: no temporary database to remove (%.3f s).\n", (hrtime(true) - $start) / 1e9);
        return true;
    }
    $record = json_decode(file_get_contents($state), true, 512, JSON_THROW_ON_ERROR);
    $database = $record['database'];
    if (!preg_match('/^lgv_tz_demo_[a-f0-9]{24}$/D', $database)) {
        throw new RuntimeException('Unexpected demo database name; refusing to drop it.');
    }
    echo 'Removing temporary database '.$database."...\n";
    $driver = $record['driver'] ?? 'mysql';
    if (!in_array($driver, ['mysql', 'pgsql'], true)) {
        throw new RuntimeException('Unexpected recorded database driver; refusing to drop it.');
    }
    $quote = $driver === 'pgsql' ? '"' : '`';
    demoConnection(null, $driver)->exec('DROP DATABASE IF EXISTS '.$quote.$database.$quote);
    if (!unlink($state)) {
        throw new RuntimeException('Could not remove the database cleanup record.');
    }
    printf("Database/download cleanup: %.3f s.\n", (hrtime(true) - $start) / 1e9);
    return true;
}

/***************************************************************************************************************************/
/**
    \brief This creates a uniquely named database and records it for cleanup.

    CREATE does not use IF NOT EXISTS, so a name collision cannot adopt an existing database. Where pcntl is
    available, interruption signals are blocked until the cleanup record has been written. If the record cannot be
    written, we drop the new database before reporting the failure.

    \returns: The new database name, beginning with "lgv_tz_demo_".
    \throws RuntimeException if the cleanup record cannot be written.
    \throws PDOException if database creation or rollback fails.
*/
function demoCreateDatabase(PDO $admin,          ///< An administrative connection with CREATE and DROP permissions.
                            string $directory   ///< The working directory in which database.json is saved.
                            ): string {
    $name = 'lgv_tz_demo_'.bin2hex(random_bytes(12));
    $driver = $admin->getAttribute(PDO::ATTR_DRIVER_NAME);
    $quote = $driver === 'pgsql' ? '"' : '`';
    // Do not deliver an interruption between creating the database and saving its cleanup record.
    $previousMask = [];
    $blocked = function_exists('pcntl_sigprocmask') && pcntl_sigprocmask(SIG_BLOCK, [SIGHUP, SIGINT, SIGTERM], $previousMask);
    try {
        // CREATE without IF NOT EXISTS ensures this run can never adopt or reset an existing database.
        $admin->exec('CREATE DATABASE '.$quote.$name.$quote);
        if (false === file_put_contents($directory.'/database.json', json_encode(['database' => $name, 'driver' => $driver], JSON_THROW_ON_ERROR))) {
            $admin->exec('DROP DATABASE '.$quote.$name.$quote);
            throw new RuntimeException('Could not record the temporary database for cleanup.');
        }
    } finally {
        if ($blocked) { pcntl_sigprocmask(SIG_SETMASK, $previousMask); }
    }
    return $name;
}

/***************************************************************************************************************************/
/**
    \brief This performs the complete Composer installation, data load, system test, and metrics run.

    We first check PHP and the database server, then install the package and obtain the latest boundary release. The package
    performs the actual load while a listener retains independent source geometry for storage and lookup checks.
    The large files are deleted after loading, and the reference geometry is freed before lookup measurements.

    The known locations come from the installed package. Generated tests sample the largest polygons, including
    points on both sides of boundaries. Coverage must show that those polygons were actually decoded by the API.
    The parent shell handles database and directory cleanup after this function returns or the worker fails.

    \returns: 0, if every check passes; 1, if a storage, lookup, or coverage check fails.
    \throws RuntimeException if setup, download, loading, or test preparation cannot complete.
*/
function demoRun(string $directory  ///< The private working directory created by run.sh.
                ): int {
    global $demoMetrics;
    if (PHP_VERSION_ID < 80000) {
        throw new RuntimeException('PHP 8.0 or later is required.');
    }
    $driver = demoDriver();
    foreach (['pdo_'.$driver, 'curl', 'zip', 'mbstring', 'ctype'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException('The PHP '.$extension.' extension is required.');
        }
    }
    if (!function_exists('proc_open')) {
        throw new RuntimeException('PHP proc_open must be enabled to run Composer.');
    }
    // Check credentials before downloading anything. The connection must be able to create and drop a database.
    $admin = demoConnection();
    printf("Database: %s %s.\n", $driver, $admin->getAttribute(PDO::ATTR_SERVER_VERSION));
    $name = demoCreateDatabase($admin, $directory);
    $start = hrtime(true);
    demoInstall($directory);
    demoTiming('Composer setup', $start);

    echo "Finding the latest timezone boundary release...\n";
    $start = hrtime(true);
    demoDownload(DEMO_RELEASE_API, $directory.'/release.json');
    $release = json_decode(file_get_contents($directory.'/release.json'), true, 512, JSON_THROW_ON_ERROR);
    $asset = null;
    foreach ($release['assets'] ?? [] as $candidate) {
        if ($candidate['name'] === 'timezones-with-oceans.geojson.zip') {
            $asset = $candidate;
            break;
        }
    }
    if (null === $asset) {
        throw new RuntimeException('The latest release does not contain timezones-with-oceans.geojson.zip.');
    }
    printf("Downloading boundary release %s (%.1f MiB compressed)...\n", $release['tag_name'], $asset['size'] / 1048576);
    $archive = $directory.'/boundaries.zip';
    $boundary = $directory.'/boundaries.json';
    demoDownload($asset['browser_download_url'], $archive);
    if (isset($asset['digest']) && str_starts_with($asset['digest'], 'sha256:') &&
        !hash_equals(substr($asset['digest'], 7), hash_file('sha256', $archive))) {
        throw new RuntimeException('Boundary archive checksum did not match the release metadata.');
    }
    $demoMetrics['download_bytes'] = filesize($archive);
    demoTiming('Release discovery + download', $start);
    $start = hrtime(true);
    demoExtract($archive, $boundary);
    $demoMetrics['extracted_bytes'] = filesize($boundary);
    demoTiming('ZIP extraction', $start);
    printf("Extracted %.1f MiB of GeoJSON.\n", filesize($boundary) / 1048576);

    echo 'Loading boundaries into temporary database '.$name."...\n";
    require_once __DIR__.'/SystemTests.php';
    $database = new DemoTrackedDatabase($name,
        demoEnvironment('USER', $driver === 'pgsql' ? (getenv('USER') ?: get_current_user()) : 'root'),
        demoEnvironment('PASSWORD', ''), $driver, demoEnvironment('HOST', 'localhost'),
        (int)demoEnvironment('PORT', $driver === 'pgsql' ? '5432' : '3306'));
    $stream = fopen($boundary, 'rb');
    if (false === $stream) {
        throw new RuntimeException('Could not open the boundary file.');
    }
    $start = hrtime(true);
    demoResetMemoryPeak();
    $source = new DemoSourceGeometry();
    try {
        $listener = new LGV_TZ_Lookup_Loader($database);
        $inspector = new DemoInspectingListener($listener, $source);
        $parser = new JsonStreamingParser\Parser($stream, $inspector);
        $parser->parse();
        $demoMetrics['load_peak_bytes'] = memory_get_peak_usage();
    } finally {
        fclose($stream);
        unset($parser, $listener, $inspector);
        demoRemoveDownloads($directory);
    }
    demoTiming('Load + capture reference geometry', $start);
    $validation = demoConnection($name);
    if ($driver === 'pgsql') {
        // Give PostgreSQL current statistics before its first bounding-box queries.
        $validation->exec('ANALYZE timezones');
    }
    $polygons = $validation->query('SELECT COUNT(*) FROM timezones')->fetchColumn();
    printf("Loaded %s polygons in %.2f seconds. Download and extracted file deleted.\n", $polygons, (hrtime(true) - $start) / 1e9);
    if ((int)$polygons === 0) {
        throw new RuntimeException('No polygons were loaded.');
    }

    $start = hrtime(true);
    demoResetMemoryPeak();
    echo "Checking stored polygons against the decoded source GeoJSON...\n";
    $storageFailures = $source->validateStorage($validation);
    foreach ($storageFailures as $failure) { echo 'FAIL '.$failure."\n"; }
    printf("Source/storage validation: %d polygons checked, %d failures.\n", count($source->shapes), count($storageFailures));
    $demoMetrics['database_bytes'] = $source->polygonBytes;
    $largestCount = filter_var(demoEnvironment('LARGEST_SHAPES', '10'), FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 100]]);
    if (false === $largestCount) { throw new RuntimeException('LGV_TZ_DEMO_LARGEST_SHAPES must be between 1 and 100.'); }
    $demoMetrics['largest'] = array_map(static fn($shape) => array_intersect_key($shape, array_flip(['id', 'tzname', 'vertices', 'bytes'])), $source->largest($largestCount));
    echo 'Generating independent interior and two-sided boundary probes for the '.$largestCount." largest polygons...\n";
    $generated = $source->casesForLargest($largestCount);
    $pairs = [];
    foreach ($generated as $case) {
        if (isset($case['pair'])) { $pairs[$case['pair']][$case['side']] = $case['expected']; }
    }
    $demoMetrics['boundary_pairs'] = count($pairs);
    $demoMetrics['crossing_pairs'] = 0;
    $demoMetrics['named_pairs'] = 0;
    foreach ($pairs as $sides) {
        if (isset($sides[-1], $sides[1]) && $sides[-1] !== $sides[1]) {
            ++$demoMetrics['crossing_pairs'];
            $zones = array_merge($sides[-1], $sides[1]);
            if (!empty($sides[-1]) && !empty($sides[1]) &&
                count(array_filter($zones, static fn($z) => str_starts_with($z, 'Etc/'))) === 0) {
                ++$demoMetrics['named_pairs'];
            }
        }
    }
    $demoMetrics['reference_peak_bytes'] = memory_get_peak_usage();
    unset($source);
    gc_collect_cycles();
    if (function_exists('gc_mem_caches')) { gc_mem_caches(); }
    demoTiming('Independent source/storage validation', $start);

    $package = Composer\InstalledVersions::getInstallPath(DEMO_PACKAGE);
    require $package.'/src/TestLocations.php';
    if (empty($test_locations_param_array)) {
        throw new RuntimeException('The installed package contains no location tests.');
    }
    $lookup = new LGV_TZ_Lookup_Query($database);
    $cases = [];
    foreach ($test_locations_param_array as $test) {
        $test['expected'] = [$test['result']];
        $test['kind'] = 'known location';
        $cases[] = $test;
    }
    $cases = array_merge($cases, $generated);
    $failures = count($storageFailures);
    $groups = [];
    $start = hrtime(true);
    echo 'Running '.count($test_locations_param_array).' known locations and '.count($generated)." generated system tests through the Composer lookup API...\n";
    foreach ($cases as $index => $test) {
        $lng = $test['params']['lng'];
        $lat = $test['params']['lat'];
        while ($lng < -180) { $lng += 360; }
        while ($lng > 180) { $lng -= 360; }
        while ($lat < -90) { $lat += 180; }
        while ($lat > 90) { $lat -= 180; }
        demoResetMemoryPeak();
        $baselineMemory = memory_get_usage();
        $database->generatedCall = $test['kind'] !== 'known location';
        $callStart = hrtime(true);
        $actual = $lookup->get_tz($lng, $lat);
        $elapsed = (hrtime(true) - $callStart) / 1e6;
        $demoMetrics['lookup_ms'][] = $elapsed;
        if ($test['kind'] !== 'known location') { $demoMetrics['generated_lookup_ms'][] = $elapsed; }
        else { $demoMetrics['known_lookup_ms'][] = $elapsed; }
        $demoMetrics['lookup_extra_bytes'] = max($demoMetrics['lookup_extra_bytes'], memory_get_peak_usage() - $baselineMemory);
        $demoMetrics['lookup_peak_bytes'] = max($demoMetrics['lookup_peak_bytes'], memory_get_peak_usage());
        $pass = in_array($actual, empty($test['expected']) ? [''] : $test['expected'], true);
        $groups[$test['kind']]['total'] = ($groups[$test['kind']]['total'] ?? 0) + 1;
        $groups[$test['kind']]['passed'] = ($groups[$test['kind']]['passed'] ?? 0) + (int)$pass;
        if (!$pass) {
            ++$failures;
            printf("FAIL %s [%.6f, %.6f]: expected %s, received %s\n", $test['title'], $lng, $lat,
                empty($test['expected']) ? '(no match)' : implode(' or ', $test['expected']), $actual === '' ? '(no match)' : $actual);
        } elseif ($index < 5) {
            printf("PASS %s: %s\n", $test['title'], $actual);
        }
    }
    demoTiming('Package lookup tests', $start);
    $demoMetrics['polygon_rows'] = $database->polygonRows;
    $demoMetrics['polygon_bytes_processed'] = $database->polygonBytes;
    $demoMetrics['largest_decoded'] = 0;
    foreach ($demoMetrics['largest'] as $shape) {
        if (isset($database->generatedPolygons[$shape['id']])) { ++$demoMetrics['largest_decoded']; }
        else {
            ++$failures;
            echo 'FAIL coverage: generated probes did not decode largest polygon '.$shape['id'].' ('.$shape['tzname'].").\n";
        }
    }
    foreach ($groups as $kind => $group) { printf("%s: %d/%d passed.\n", ucfirst($kind), $group['passed'], $group['total']); }
    demoPrintMetrics();
    if ($failures > 0) {
        fwrite(STDERR, "System tests found mismatches. Generated expectations come from GeoJSON, not package output.\n");
    }
    return $failures === 0 ? 0 : 1;
}

/***************************************************************************************************************************/
/**
    \brief This is the worker entrypoint.

    run.sh supplies the mode and working directory. An invalid invocation exits with 2. A caught worker error exits
    with 1, after printing any collected metrics. The shell preserves the worker's exit status unless cleanup fails.
*/
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) { return; }

if (PHP_SAPI !== 'cli' || $argc !== 3 || !in_array($argv[1], ['run', 'cleanup'], true) || !is_dir($argv[2])) {
    fwrite(STDERR, "Run this demo with: ./demo/run.sh\n");
    exit(2);
}
// On PHP CLI builds with pcntl, allow the shell's cleanup trap to run promptly on interruption.
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    foreach ([SIGHUP, SIGINT, SIGTERM] as $signal) {
        pcntl_signal($signal, static function(int $signal): void {
            throw new RuntimeException('Demo interrupted by signal '.$signal.'.');
        });
    }
}
try {
    exit($argv[1] === 'cleanup' ? (demoCleanup($argv[2]) ? 0 : 1) : demoRun($argv[2]));
} catch (Throwable $error) {
    fwrite(STDERR, 'Demo error: '.$error->getMessage().PHP_EOL);
    if ($argv[1] === 'run') { demoPrintMetrics(); }
    exit(1);
}
