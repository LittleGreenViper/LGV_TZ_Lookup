<?php
/***************************************************************************************************************************/
/**
    © Copyright 2023-2026, [Little Green Viper Software Development LLC](https://littlegreenviper.com)

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
*/
/***************************************************************************************************************************/
/**
    \file
    \brief Report the latest boundary release, or replace an existing installation's boundaries.

    Run through update.sh. Its shell trap removes downloads even after interruption or a PHP memory-limit failure.
    New polygons load into a private staging table. MySQL publishes with one RENAME TABLE; PostgreSQL uses transactional
    renames. Failed loading preserves the live table. Recovery removes this run's staging/old tables, never timezones.
*/
declare(strict_types=1);
require_once __DIR__.'/Setup.php';

/***************************************************************************************************************************/
/** \brief Read the server's existing database settings without starting the HTTP endpoint. */
function updateSettings(string $config): array {
    if (!is_file($config) || !is_readable($config)) { throw new RuntimeException('The private configuration file is not readable.'); }
    require $config;
    if (!isset($g_dbName, $g_dbUserName, $g_dbPassword) || !is_string($g_dbName) || $g_dbName === '') {
        throw new RuntimeException('The configuration must define g_dbName, g_dbUserName, and g_dbPassword.');
    }
    $driver = strtolower($g_dbType ?? 'mysql');
    if (!in_array($driver, ['mysql', 'pgsql'], true)) { throw new RuntimeException('The database driver must be mysql or pgsql.'); }
    return ['database' => $g_dbName, 'user' => $g_dbUserName, 'password' => $g_dbPassword,
        'driver' => $driver, 'host' => $g_dbHost ?? '127.0.0.1', 'port' => $g_dbPort ?? ($driver === 'pgsql' ? 5432 : 3306)];
}

/***************************************************************************************************************************/
/** \brief Connect and hold the same database lock used by the installer. */
function updateConnection(array $settings): PDO {
    $driver = $settings['driver'];
    $dsn = $driver.':host='.$settings['host'].';port='.$settings['port'].';dbname='.$settings['database'];
    $dsn .= $driver === 'mysql' ? ';charset=utf8mb4' : ';options=--client_encoding=UTF8';
    $connection = new PDO($dsn, $settings['user'], $settings['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $sql = $driver === 'pgsql' ? "SELECT pg_try_advisory_lock(hashtext('LGV_TZ_Lookup.install'))"
        : "SELECT GET_LOCK(CONCAT('lgv_tz_', SHA2(DATABASE(), 224)), 0)";
    if (!$connection->query($sql)->fetchColumn()) { throw new RuntimeException('Another timezone installation or update is using this database.'); }
    return $connection;
}

/***************************************************************************************************************************/
/** \brief Remove downloaded data and a recorded staging table after an interrupted load. */
function updateCleanup(string $work): void {
    foreach (['boundaries.zip', 'boundaries.json', 'release.json'] as $file) {
        if (is_file($work.'/'.$file) && !unlink($work.'/'.$file)) { throw new RuntimeException('Could not remove downloaded '.$file); }
    }
    if (!is_file($work.'/update.json')) { return; }
    $state = json_decode(file_get_contents($work.'/update.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_string($state['table'] ?? null) || !preg_match('/^lgv_tz_update_[a-f0-9]{16}$/D', $state['table'])) {
        throw new RuntimeException('Invalid staging table record; refusing database cleanup.');
    }
    $connection = updateConnection(updateSettings($state['config']));
    $connection->exec('DROP TABLE IF EXISTS '.$state['table']);
    $connection->exec('DROP TABLE IF EXISTS lgv_tz_old_'.substr($state['table'], -16));
    if (!unlink($work.'/update.json')) { throw new RuntimeException('Could not remove staging recovery information.'); }
}

/***************************************************************************************************************************/
/** \brief Load into staging, then publish atomically. The caller retains the database lock throughout. */
function updateLoad(PDO $connection, array $settings, string $json, string $table): int {
    if (!preg_match('/^lgv_tz_update_[a-f0-9]{16}$/D', $table)) { throw new InvalidArgumentException('Invalid staging table name.'); }
    $database = new class($settings, $table) extends LGV_TZ_Lookup_Database {
        /** \brief Use the normal loader schema and storage format, with this run's private destination. */
        public function __construct(array $settings, string $table) {
            parent::__construct($settings['database'], $settings['user'], $settings['password'],
                $settings['driver'], $settings['host'], $settings['port']);
            $this->_load_table = $table;
        }
    };
    $stream = fopen($json, 'rb');
    if ($stream === false) { throw new RuntimeException('Could not open the downloaded boundary file.'); }
    try {
        $listener = new class($database) extends LGV_TZ_Lookup_Loader {
            public bool $complete = false; ///< True only after the parser closes the entire JSON document.
            /** \brief Reject truncated input even when the streaming parser silently reaches EOF. */
            public function endDocument(): void { parent::endDocument(); $this->complete = true; }
        };
        (new JsonStreamingParser\Parser($stream, $listener))->parse();
        if (!$listener->complete) { throw new RuntimeException('The boundary JSON is incomplete; existing boundaries were retained.'); }
    } finally { fclose($stream); LGV_TZ_Lookup_Loader::$db_object = null; }
    $count = (int)$connection->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
    if ($count === 0) { throw new RuntimeException('No polygons were loaded; existing boundaries were retained.'); }
    if ($settings['driver'] === 'pgsql') { $connection->exec('ANALYZE '.$table); }
    $old = 'lgv_tz_old_'.substr($table, -16);
    if ($settings['driver'] === 'pgsql') {
        $connection->beginTransaction();
        try {
            $connection->exec('ALTER TABLE timezones RENAME TO '.$old);
            $connection->exec('ALTER TABLE '.$table.' RENAME TO timezones');
            $connection->exec('DROP TABLE '.$old);
            $connection->commit();
        } catch (Throwable $error) {
            if ($connection->inTransaction()) { $connection->rollBack(); }
            throw $error;
        }
    } else {
        $connection->exec('RENAME TABLE timezones TO '.$old.', '.$table.' TO timezones');
        // Publication already succeeded. A failure removing the old copy must not be described as a failed load.
        try { $connection->exec('DROP TABLE '.$old); }
        catch (Throwable $error) { fwrite(STDERR, 'Updated boundaries, but could not remove old table '.$old.'.'.PHP_EOL); }
    }
    return $count;
}

/***************************************************************************************************************************/
/** \brief Discover, report, download, and load boundaries; --check stops before reading config or touching the database. */
function updateRun(string $work, string $config, bool $check = false, ?callable $download = null): void {
    if (!extension_loaded('curl')) { throw new RuntimeException('The PHP curl extension is required.'); }
    $download = $download ?? [LGV_TZ_Lookup_Setup::class, 'download'];
    $release = LGV_TZ_Lookup_Setup::latestBoundaries($work, $download);
    echo 'Latest boundary release: '.$release['version'].PHP_EOL;
    if ($check) { return; }
    foreach (['zip', 'pdo', 'mbstring', 'ctype'] as $extension) {
        if (!extension_loaded($extension)) { throw new RuntimeException('The PHP '.$extension.' extension is required.'); }
    }
    $config = realpath($config);
    if ($config === false) { throw new RuntimeException('The private configuration file was not found.'); }
    $settings = updateSettings($config);
    // Installed servers keep their Composer application next to config.php; manual copies use root dependencies.
    foreach ([dirname($config).'/app/vendor/autoload.php', dirname($config).'/vendor/autoload.php',
        dirname(__DIR__).'/vendor/autoload.php'] as $autoload) {
        if (is_file($autoload)) { require_once $autoload; }
    }
    if (!class_exists('LGV_TZ_Lookup_Database')) {
        require_once dirname(__DIR__).'/src/Sources/LGV_TZ_Lookup_Database.class.php';
    }
    if (!class_exists('LGV_TZ_Lookup_Loader')) {
        require_once dirname(__DIR__).'/src/Sources/LGV_TZ_Lookup_Loader.class.php';
    }
    // Older installed packages cannot redirect the loader's writes; fail before loading rather than reset the live table.
    if (!property_exists(LGV_TZ_Lookup_Database::class, '_load_table')) {
        throw new RuntimeException('Update the installed Composer package to 1.4.1 or later before running this updater.');
    }
    $connection = updateConnection($settings);
    $connection->query('SELECT id, tzname, east, west, north, south, polygon FROM timezones LIMIT 0')->closeCursor();
    echo "Downloading full timezone boundaries with oceans...\n";
    $download($release['asset']['browser_download_url'], $work.'/boundaries.zip');
    LGV_TZ_Lookup_Setup::verifyBoundaries($work.'/boundaries.zip', $release['asset']);
    LGV_TZ_Lookup_Setup::extract($work.'/boundaries.zip', $work.'/boundaries.json');
    $table = 'lgv_tz_update_'.bin2hex(random_bytes(8));
    if (file_put_contents($work.'/update.json', json_encode(['config' => $config, 'table' => $table], JSON_THROW_ON_ERROR)) === false ||
        !chmod($work.'/update.json', 0600)) { throw new RuntimeException('Could not record staging recovery information.'); }
    echo 'Loading boundary release '.$release['version']."...\n";
    try {
        $count = updateLoad($connection, $settings, $work.'/boundaries.json', $table);
        echo 'Updated to boundary release '.$release['version'].'; loaded '.$count." polygons.\n";
    } finally {
        // On success the staging table was renamed; on failure it contains only this run's incomplete data.
        $connection->exec('DROP TABLE IF EXISTS '.$table);
        unlink($work.'/update.json');
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) { return; }
$cleanup = ($argv[1] ?? '') === 'cleanup';
$work = $argv[$cleanup ? 2 : 1] ?? '';
if (PHP_SAPI !== 'cli' || $argc < 3 || !is_dir($work)) {
    fwrite(STDERR, "Run ./update.sh [--check] [CONFIG_FILE].\n"); exit(2);
}
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    foreach ([SIGHUP, SIGINT, SIGTERM] as $signal) {
        pcntl_signal($signal, static function(int $signal): void { throw new RuntimeException('Update interrupted by signal '.$signal.'.'); });
    }
}
try {
    if ($cleanup) { updateCleanup($work); }
    else { updateRun($work, $argv[2], ($argv[3] ?? '') === '1'); }
} catch (Throwable $error) {
    fwrite(STDERR, 'Update error: '.$error->getMessage().PHP_EOL); exit(1);
}
