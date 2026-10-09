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
    \brief These are database integration tests for MySQL and PostgreSQL.

    Run with --driver=mysql or --driver=pgsql. Connection overrides use LGV_TZ_TEST_HOST, PORT, USER, PASSWORD, and
    ADMIN_DATABASE. The tests create their own uniquely named database and remove it in a finally block. They check
    loading, binary encoding, lookup precedence, bounded row fetching, cursor cleanup, and transaction ownership.
    --autoload=/path/to/vendor/autoload.php runs the same checks against a Composer consumer installation.
*/

declare(strict_types=1);

$options = getopt('', ['driver:', 'autoload:']);
$driver = $options['driver'] ?? 'mysql';
if (!in_array($driver, ['mysql', 'pgsql'], true)) {
    throw new InvalidArgumentException('--driver must be mysql or pgsql.');
}
if (isset($options['autoload'])) {
    require $options['autoload'];
} else {
    require dirname(__DIR__).'/src/Sources/LGV_TZ_Lookup_Query.class.php';
    require dirname(__DIR__).'/src/Sources/LGV_TZ_Lookup_Loader.class.php';
}
$checks = 0;

/***************************************************************************************************************************/
/**
    \brief This observes native PostgreSQL result sizes without keeping statements or cursors alive.
    PHP heap counters alone do not include libpq's result buffers. A weak reference lets the test inspect the
    active result and verify that it contains one row, while normal statement destruction still performs cleanup.
*/
class DatabaseTestStatement extends PDOStatement {
    public static ?WeakReference $last = null; ///< The most recently prepared statement, while it remains alive.
    protected function __construct() { self::$last = WeakReference::create($this); }
}

/***************************************************************************************************************************/
/** \brief This checks a result and counts successful assertions. \throws RuntimeException if values differ. */
function databaseCheck($expected, $actual, string $label): void {
    global $checks;
    ++$checks;
    if ($expected !== $actual) {
        throw new RuntimeException($label.': expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

/***************************************************************************************************************************/
/**
    \brief This exercises the installed database and lookup classes in an isolated database.
    The function scope releases every test connection before the outer cleanup drops the PostgreSQL database.
    \throws Exception if a database operation or assertion fails.
*/
function testDatabase(string $name, string $user, string $password, string $driver, string $host, int $port): void {
    $database = new LGV_TZ_Lookup_Database($name, $user, $password, strtoupper($driver), $host, $port);
    $database->reset_database();
    $wrapper = $database->pdo_instance;
    $property = new ReflectionProperty(LGV_TZ_Lookup_PDO::class, '_pdo');
    if (PHP_VERSION_ID < 80100) { $property->setAccessible(true); }
    $connection = $property->getValue($wrapper);
    $connection->setAttribute(PDO::ATTR_STATEMENT_CLASS, [DatabaseTestStatement::class, []]);
    databaseCheck('UTF8', $driver === 'pgsql' ? $connection->query('SHOW client_encoding')->fetchColumn() : 'UTF8', 'Connection encoding');
    $lookup = new LGV_TZ_Lookup_Query($database);
    databaseCheck('', $lookup->get_tz(2, 2), 'Empty database');
    $bounds = ['east' => 4, 'west' => 0, 'north' => 4, 'south' => 0];
    $miss = [[0, 0], [4, 0], [4, 1], [0, 1], [0, 0]];
    $hit = [[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]];
    $database->store_entity(new LGV_TZ_Lookup_Entity('Region/First', $bounds, $miss));
    databaseCheck('Region/First', $lookup->get_tz(2, 2), 'Existing single-candidate behavior');
    $database->store_entity(new LGV_TZ_Lookup_Entity('Region/Second', $bounds, $miss));
    $database->store_entity(new LGV_TZ_Lookup_Entity('Etc/GMT', $bounds, $hit));
    databaseCheck('Etc/GMT', $lookup->get_tz(2, 2), 'Ocean fallback after named polygons miss');
    $database->store_entity(new LGV_TZ_Lookup_Entity('Region/Third', $bounds, $hit));
    $database->store_entity(new LGV_TZ_Lookup_Entity('Region/Fourth', $bounds, $hit));
    databaseCheck('Region/Third', $lookup->get_tz(2, 2), 'Named precedence and primary-key order');
    databaseCheck([], iterator_to_array($database->get_tz_polygons([])), 'Empty polygon iterator');
    $decoded = $database->get_tz_entities([3]);
    databaseCheck($hit, array_map(static fn($p) => array_map('intval', $p), $decoded[0]['polygon']), 'Legacy decoded-polygon API');

    // A known circle crosses two decoder blocks. A second named candidate forces actual polygon decoding.
    $ring = [];
    for ($i = 0; $i < 2053; ++$i) {
        $angle = 2 * M_PI * $i / 2053;
        $ring[] = [40 + cos($angle), 40 + sin($angle)];
    }
    $ring[] = $ring[0];
    $bounds = ['east' => 41, 'west' => 39, 'north' => 41, 'south' => 39];
    $database->store_entity(new LGV_TZ_Lookup_Entity('Region/Circle', $bounds, $ring));
    $database->store_entity(new LGV_TZ_Lookup_Entity('Region/CircleMiss', $bounds, [[39, 39], [41, 39], [41, 39.001], [39, 39.001], [39, 39]]));
    foreach ([-0.99, -0.7, 0.0, 0.7, 0.99] as $x) {
        foreach ([-0.99, -0.7, 0.0, 0.7, 0.99] as $y) {
            databaseCheck($x * $x + $y * $y < 1 ? 'Region/Circle' : '', $lookup->get_tz(40 + $x, 40 + $y), 'Analytic circle '.$x.','.$y);
        }
    }
    $sourceBytes = '';
    foreach ($ring as $point) { $sourceBytes .= pack('d2', round($point[0], 6), round($point[1], 6)); }
    $row = iterator_to_array($database->get_tz_polygons([6]))[0];
    databaseCheck($sourceBytes, $row['polygon'], 'Packed double bytes survive database round trip');
    unset($ring, $row, $sourceBytes);

    // Many one-MiB blobs would exceed this bound if the PDO reader buffered the whole result in PHP memory.
    $type = $driver === 'pgsql' ? 'BYTEA' : 'LONGBLOB';
    $wrapper->preparedStatement('CREATE TEMPORARY TABLE binary_fixture (id INTEGER PRIMARY KEY, payload '.$type.')');
    $blob = str_repeat(implode('', array_map('chr', range(0, 255))), 4096);
    for ($i = 1; $i <= 24; ++$i) {
        $wrapper->preparedStatement('INSERT INTO binary_fixture VALUES (?, ?)', [$i, $blob], false, [1 => PDO::PARAM_LOB]);
    }
    $wrapper->preparedStatement('INSERT INTO binary_fixture VALUES (?, ?)', [25, ''], false, [1 => PDO::PARAM_LOB]);
    databaseCheck('', $wrapper->preparedStatement('SELECT payload FROM binary_fixture WHERE id = 25', [], true)[0]['payload'], 'Empty binary value');
    if (function_exists('memory_reset_peak_usage')) { memory_reset_peak_usage(); }
    $before = memory_get_usage();
    $count = 0;
    $nativePeak = 0;
    foreach ($wrapper->preparedRows('SELECT payload FROM binary_fixture WHERE id <= ? ORDER BY id', [24], false) as $row) {
        databaseCheck(hash('sha256', $blob), hash('sha256', $row['payload']), 'All 256 byte values in row '.++$count);
        if ($driver === 'pgsql' && defined('Pdo\\Pgsql::ATTR_RESULT_MEMORY_SIZE')) {
            $nativePeak = max($nativePeak, DatabaseTestStatement::$last->get()->getAttribute(\Pdo\Pgsql::ATTR_RESULT_MEMORY_SIZE));
        }
        unset($row);
    }
    databaseCheck(24, $count, 'All binary rows fetched');
    if (function_exists('memory_reset_peak_usage')) {
        $extra = memory_get_peak_usage() - $before;
        databaseCheck(true, $extra < 8 * 1048576, 'Binary reader stays below eight MiB extra PHP memory');
        printf("  Binary reader peak extra PHP memory: %.2f MiB for 24 MiB of rows.\n", $extra / 1048576);
    }
    if ($nativePeak > 0) {
        databaseCheck(true, $nativePeak < 4 * 1048576, 'PostgreSQL native result buffer holds one row');
        printf("  PostgreSQL native result buffer peak: %.2f MiB.\n", $nativePeak / 1048576);
    }

    $rows = $wrapper->preparedRows('SELECT payload FROM binary_fixture ORDER BY id', [], false);
    foreach ($rows as $row) { break; }
    unset($rows, $row);
    databaseCheck(3, (int)$wrapper->preparedStatement('SELECT 3 AS value', [], true)[0]['value'], 'Connection usable after early cursor exit');
    databaseCheck(false, $connection->inTransaction(), 'Reader leaves no transaction open');
    $connection->beginTransaction();
    $rows = $wrapper->preparedRows('SELECT payload FROM binary_fixture ORDER BY id', [], false);
    foreach ($rows as $row) { break; }
    unset($rows, $row);
    databaseCheck(true, $connection->inTransaction(), 'Reader preserves caller transaction');
    $wrapper->preparedStatement('INSERT INTO binary_fixture VALUES (?, ?)', [26, $blob], false, [1 => PDO::PARAM_LOB]);
    databaseCheck(true, $connection->inTransaction(), 'Write preserves caller transaction');
    $connection->rollBack();
    databaseCheck(0, (int)$wrapper->preparedStatement('SELECT COUNT(*) AS count FROM binary_fixture WHERE id = 26', [], true)[0]['count'], 'Caller rollback undoes the write');
    foreach ([true, false] as $write) {
        try {
            if ($write) { $wrapper->preparedStatement('INSERT INTO lgv_tz_missing_table VALUES (1)'); }
            else { iterator_to_array($wrapper->preparedRows('SELECT * FROM lgv_tz_missing_table', [], false)); }
            throw new RuntimeException('Missing table should fail.');
        } catch (Exception $error) {
            databaseCheck(true, $error->getPrevious() instanceof PDOException, 'Database exception retains PDO context');
        }
        databaseCheck(9, (int)$wrapper->preparedStatement('SELECT 9 AS value', [], true)[0]['value'], 'Connection usable after database error');
    }

    if ($driver === 'pgsql') {
        // Exercise the server-cursor mechanism used by PHP 8.0-8.4, as well as the normal PHP 8.5 reader above.
        $cursor = $connection->prepare('SELECT payload FROM binary_fixture WHERE id = ?', [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL]);
        $cursor->execute([1]);
        $stream = $cursor->fetch(PDO::FETCH_ASSOC)['payload'];
        databaseCheck($blob, stream_get_contents($stream), 'PostgreSQL server cursor preserves binary values');
        fclose($stream);
        $cursor->closeCursor();
        unset($cursor);
        databaseCheck(0, (int)$connection->query("SELECT COUNT(*) FROM pg_cursors WHERE name LIKE 'pdo_crsr_%'")->fetchColumn(), 'PostgreSQL server cursor released');
    }
    unset($blob);

    // The real loader must rebuild the schema, restart IDs, and preserve MultiPolygon geometry.
    $listener = new LGV_TZ_Lookup_Loader($database);
    $stream = fopen('php://memory', 'w+b');
    fwrite($stream, json_encode(['type' => 'FeatureCollection', 'features' => [[
        'type' => 'Feature', 'properties' => ['tzid' => 'Etc/GMT'],
        'geometry' => ['type' => 'MultiPolygon', 'coordinates' => [[ $hit ], [ [[10, 10], [14, 10], [14, 14], [10, 14], [10, 10]] ]]],
    ]]], JSON_THROW_ON_ERROR));
    rewind($stream);
    try { (new JsonStreamingParser\Parser($stream, $listener))->parse(); }
    finally { fclose($stream); }
    databaseCheck(2, (int)$wrapper->preparedStatement('SELECT COUNT(*) AS count FROM timezones', [], true)[0]['count'], 'Loader resets schema and loads both polygons');
    databaseCheck([1, 2], array_map(static fn($r) => (int)$r['id'], $wrapper->preparedStatement('SELECT id FROM timezones ORDER BY id', [], true)), 'Reset restarts generated IDs');
    databaseCheck('Etc/GMT', $lookup->get_tz(2, 2), 'Loaded first polygon');
    databaseCheck('Etc/GMT', $lookup->get_tz(12, 12), 'Loaded second polygon');
}

$host = getenv('LGV_TZ_TEST_HOST') ?: 'localhost';
$port = (int)(getenv('LGV_TZ_TEST_PORT') ?: ($driver === 'pgsql' ? 5432 : 3306));
$user = getenv('LGV_TZ_TEST_USER') ?: ($driver === 'pgsql' ? (getenv('USER') ?: get_current_user()) : 'root');
$password = getenv('LGV_TZ_TEST_PASSWORD') ?: '';
$dsn = $driver.':host='.$host.';port='.$port;
if ($driver === 'pgsql') { $dsn .= ';dbname='.(getenv('LGV_TZ_TEST_ADMIN_DATABASE') ?: 'postgres'); }
$admin = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$name = 'lgv_tz_test_'.bin2hex(random_bytes(12));
$quote = $driver === 'pgsql' ? '"' : '`';
$admin->exec('CREATE DATABASE '.$quote.$name.$quote);
try {
    testDatabase($name, $user, $password, $driver, $host, $port);
    echo 'Passed '.$checks.' '.$driver." integration checks.\n";
} finally {
    if (class_exists('LGV_TZ_Lookup_Loader', false)) { LGV_TZ_Lookup_Loader::$db_object = null; }
    $admin->exec('DROP DATABASE '.$quote.$name.$quote);
    echo "Removed temporary test database.\n";
}
