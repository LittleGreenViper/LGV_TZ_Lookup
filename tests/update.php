<?php
/**
    © Copyright 2023-2026, Little Green Viper Software Development LLC.
    MIT License; see LICENSE.
    \file
    \brief Check boundary updating and failure recovery against isolated MySQL or PostgreSQL databases.
    Uses small local ZIP/release fixtures, with the same LGV_TZ_TEST_* settings as tests/databases.php.
    Add --live to exercise the installed shell command with the latest full download and all known-location checks.
*/
declare(strict_types=1);
require dirname(__DIR__).'/tools/update.php';
require dirname(__DIR__).'/tools/deploy.php';
require dirname(__DIR__).'/vendor/autoload.php';
$options = getopt('', ['driver:', 'live']);
$driver = $options['driver'] ?? 'mysql';
if (!in_array($driver, ['mysql', 'pgsql'], true)) { throw new InvalidArgumentException('Choose mysql or pgsql.'); }
$checks = 0;

/** \brief Check a result and count successful assertions. */
function updaterCheck($expected, $actual, string $label): void {
    global $checks;
    ++$checks;
    if ($expected !== $actual) { throw new RuntimeException($label.' failed: '.var_export($actual, true)); }
}

/** \brief Expect an operation to fail with a diagnostic message. */
function updaterFailure(callable $operation, string $message): void {
    try { $operation(); }
    catch (Throwable $error) { updaterCheck(true, str_contains($error->getMessage(), $message), 'Expected '.$message); return; }
    throw new RuntimeException('Expected failure: '.$message);
}

$name = 'lgv_tz_update_test_'.bin2hex(random_bytes(8));
$settings = ['driver' => $driver, 'host' => getenv('LGV_TZ_TEST_HOST') ?: 'localhost',
    'port' => (int)(getenv('LGV_TZ_TEST_PORT') ?: ($driver === 'pgsql' ? 5432 : 3306)),
    'database' => $name, 'user' => getenv('LGV_TZ_TEST_USER') ?: ($driver === 'pgsql' ? (getenv('USER') ?: get_current_user()) : 'root'),
    'password' => getenv('LGV_TZ_TEST_PASSWORD') ?: '', 'admin_database' => getenv('LGV_TZ_TEST_ADMIN_DATABASE') ?: 'postgres'];
$admin = deployConnection($settings, true);
$admin->exec('CREATE DATABASE '.deployIdentifier($name, $driver));
$work = sys_get_temp_dir().'/lgv-tz-update-test-'.bin2hex(random_bytes(8));
mkdir($work, 0700);
$connection = null;
try {
    $config = "<?php\n";
    foreach (['g_dbName' => $name, 'g_dbType' => $driver, 'g_dbUserName' => $settings['user'],
        'g_dbPassword' => $settings['password'], 'g_dbHost' => $settings['host'], 'g_dbPort' => $settings['port'],
        'g_server_secret' => 'existing-secret'] as $key => $value) { $config .= '$'.$key.' = '.var_export($value, true).";\n"; }
    file_put_contents($work.'/config.php', $config);
    $connection = deployConnection($settings);
    $connection->exec('CREATE TABLE unrelated (value INTEGER)');
    $connection->exec('INSERT INTO unrelated VALUES (42)');
    $database = new LGV_TZ_Lookup_Database($name, $settings['user'], $settings['password'], $driver, $settings['host'], $settings['port']);
    $feature = ['type' => 'Feature', 'properties' => ['tzid' => 'Asia/Tokyo'],
        'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]]]]];
    $listener = new LGV_TZ_Lookup_Loader($database);
    $listener::listener_action($feature);
    $feature['properties']['tzid'] = 'America/New_York';
    $json = json_encode(['type' => 'FeatureCollection', 'features' => [$feature]], JSON_THROW_ON_ERROR);
    $zip = new ZipArchive();
    $zip->open($work.'/fixture.zip', ZipArchive::CREATE);
    $zip->addFromString('combined-with-oceans.json', $json);
    $zip->close();
    $metadata = ['tag_name' => 'test-release', 'assets' => [['name' => 'timezones-with-oceans.geojson.zip',
        'browser_download_url' => 'https://example.com/boundaries.zip', 'size' => filesize($work.'/fixture.zip'),
        'digest' => 'sha256:'.hash_file('sha256', $work.'/fixture.zip')]]];
    $calls = [];
    $download = static function(string $url, string $destination) use ($work, &$metadata, &$calls): void {
        $calls[] = $url;
        if (str_ends_with($url, '/releases/latest')) { file_put_contents($destination, json_encode($metadata, JSON_THROW_ON_ERROR)); }
        else { copy($work.'/fixture.zip', $destination); }
    };
    ob_start();
    updateRun($work, '/does/not/exist.php', true, $download);
    $report = ob_get_clean();
    updaterCheck(true, str_contains($report, 'Latest boundary release: test-release'), 'Version report');
    updaterCheck(1, count($calls), 'Check downloads metadata only');
    updaterCheck('Asia/Tokyo', (new LGV_TZ_Lookup_Query($database))->get_tz(1, 1), 'Check preserves live data');

    $lock = updateConnection($settings);
    updaterFailure(static fn() => updateConnection($settings), 'Another timezone');
    $lock = null;
    updateRun($work, $work.'/config.php', false, $download);
    updaterCheck('America/New_York', (new LGV_TZ_Lookup_Query($database))->get_tz(1, 1), 'Publish new data');
    updaterCheck(1, (int)$connection->query('SELECT COUNT(*) FROM timezones')->fetchColumn(), 'Replace rather than append');
    updaterCheck(42, (int)$connection->query('SELECT value FROM unrelated')->fetchColumn(), 'Preserve unrelated tables');
    updaterCheck($config, file_get_contents($work.'/config.php'), 'Preserve settings and secret');
    updateCleanup($work);
    foreach (['boundaries.zip', 'boundaries.json', 'release.json', 'update.json'] as $file) {
        updaterCheck(false, is_file($work.'/'.$file), 'Delete '.$file);
    }
    // Repeated updates work even when the release tag is unchanged.
    updateRun($work, $work.'/config.php', false, $download);
    updateCleanup($work);

    $original = $metadata;
    $metadata['assets'][0]['digest'] = 'sha256:'.str_repeat('0', 64);
    updaterFailure(static fn() => updateRun($work, $work.'/config.php', false, $download), 'checksum differs');
    updateCleanup($work);
    $metadata = $original;
    $metadata['assets'] = [];
    updaterFailure(static fn() => updateRun($work, $work.'/config.php', false, $download), 'no full oceans');
    updateCleanup($work);
    $metadata = $original;
    $metadata['tag_name'] = '';
    updaterFailure(static fn() => updateRun($work, $work.'/config.php', false, $download), 'no version tag');
    updateCleanup($work);
    $metadata = $original;

    // A syntactically broken file writes one polygon into staging before the parser fails.
    $zip->open($work.'/fixture.zip', ZipArchive::OVERWRITE);
    $zip->addFromString('combined-with-oceans.json', substr($json, 0, -2).',');
    $zip->close();
    clearstatcache();
    $metadata['assets'][0]['size'] = filesize($work.'/fixture.zip');
    $metadata['assets'][0]['digest'] = 'sha256:'.hash_file('sha256', $work.'/fixture.zip');
    updaterFailure(static fn() => updateRun($work, $work.'/config.php', false, $download), 'JSON');
    updaterCheck('America/New_York', (new LGV_TZ_Lookup_Query($database))->get_tz(1, 1), 'Failed load preserves live data');
    updateCleanup($work);
    $zip->open($work.'/fixture.zip', ZipArchive::OVERWRITE);
    $zip->addFromString('combined-with-oceans.json', '{"type":"FeatureCollection","features":[]}');
    $zip->close();
    clearstatcache();
    $metadata['assets'][0]['size'] = filesize($work.'/fixture.zip');
    $metadata['assets'][0]['digest'] = 'sha256:'.hash_file('sha256', $work.'/fixture.zip');
    updaterFailure(static fn() => updateRun($work, $work.'/config.php', false, $download), 'No polygons');
    updateCleanup($work);

    $table = 'lgv_tz_update_'.bin2hex(random_bytes(8));
    $connection->exec('CREATE TABLE '.$table.' (id INTEGER)');
    $old = 'lgv_tz_old_'.substr($table, -16);
    $connection->exec('CREATE TABLE '.$old.' (id INTEGER)');
    file_put_contents($work.'/update.json', json_encode(['config' => $work.'/config.php', 'table' => $table], JSON_THROW_ON_ERROR));
    file_put_contents($work.'/boundaries.json', 'partial download');
    updateCleanup($work);
    updaterCheck(false, deployTableExists($connection, $table), 'Remove interrupted staging table');
    updaterCheck(false, deployTableExists($connection, $old), 'Remove interrupted old copy');
    file_put_contents($work.'/update.json', json_encode(['config' => $work.'/config.php', 'table' => 'timezones'], JSON_THROW_ON_ERROR));
    file_put_contents($work.'/boundaries.zip', 'partial download');
    updaterFailure(static fn() => updateCleanup($work), 'Invalid staging table');
    updaterCheck(false, is_file($work.'/boundaries.zip'), 'Delete downloads before recovery refusal');
    updaterCheck(true, deployTableExists($connection, 'timezones'), 'Refuse cleanup of live table');
    unlink($work.'/update.json');
    $schema = $driver === 'pgsql' ? 'current_schema()' : 'DATABASE()';
    $tables = $connection->query('SELECT table_name FROM information_schema.tables WHERE table_schema = '.$schema.' ORDER BY table_name')->fetchAll(PDO::FETCH_COLUMN);
    updaterCheck(['timezones', 'unrelated'], $tables, 'No staging or backup tables remain');
    if (isset($options['live'])) {
        // Exercise the deployed layout and default config discovery, with the current package's Composer autoloader.
        $installed = $work.'/installed';
        mkdir($installed.'/tools', 0700, true);
        mkdir($installed.'/app/vendor', 0700, true);
        mkdir($work.'/downloads', 0700);
        copy($work.'/config.php', $installed.'/config.php');
        foreach (['update.php', 'Setup.php'] as $file) { copy(dirname(__DIR__).'/tools/'.$file, $installed.'/tools/'.$file); }
        copy(dirname(__DIR__).'/update.sh', $installed.'/update.sh');
        chmod($installed.'/update.sh', 0700);
        file_put_contents($installed.'/app/vendor/autoload.php', '<?php require '.var_export(dirname(__DIR__).'/vendor/autoload.php', true).';');
        $environment = getenv();
        $environment['TMPDIR'] = $work.'/downloads';
        $environment['LGV_TZ_UPDATE_PHP'] = PHP_BINARY;
        $process = proc_open([$installed.'/update.sh'], [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes, null, $environment);
        if ($process === false) { throw new RuntimeException('Could not start installed updater.'); }
        updaterCheck(0, proc_close($process), 'Full installed update succeeds');
        updaterCheck([], array_values(array_diff(scandir($work.'/downloads'), ['.', '..'])), 'Shell removes all downloaded files');
        require dirname(__DIR__).'/src/TestLocations.php';
        $lookup = new LGV_TZ_Lookup_Query($database);
        foreach ($test_locations_param_array as $case) {
            $lng = $case['params']['lng']; $lat = $case['params']['lat'];
            while ($lng < -180) { $lng += 360; } while ($lng > 180) { $lng -= 360; }
            while ($lat < -90) { $lat += 180; } while ($lat > 90) { $lat -= 180; }
            updaterCheck($case['result'], $lookup->get_tz($lng, $lat), $case['title']);
        }
        $lookup = null;
        updaterCheck($config, file_get_contents($installed.'/config.php'), 'Full update preserves server configuration');
        updaterCheck(42, (int)$connection->query('SELECT value FROM unrelated')->fetchColumn(), 'Full update preserves unrelated data');
    }
    echo 'Passed '.$checks.' '.$driver." updater checks.\n";
} finally {
    if (ob_get_level()) { ob_end_clean(); }
    LGV_TZ_Lookup_Loader::$db_object = null;
    $database = null; $listener = null; $connection = null; $lock = null;
    $admin->exec('DROP DATABASE '.deployIdentifier($name, $driver));
    deployRemoveTree($work);
}
