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
    \brief These tests check deployment ownership and recovery against an isolated MySQL or PostgreSQL database.
    Run with --driver=mysql or --driver=pgsql, using the same LGV_TZ_TEST_* settings as tests/databases.php.
    No boundaries are downloaded. Add --real-composer to install the local fixture through Composer as well.
    The test database and working directories are removed afterward.
*/
declare(strict_types=1);
require dirname(__DIR__).'/tools/deploy.php';
$options = getopt('', ['driver:', 'real-composer']);
$driver = $options['driver'] ?? 'mysql';
if (!in_array($driver, ['mysql', 'pgsql'], true)) { throw new InvalidArgumentException('Choose mysql or pgsql.'); }
$checks = 0;

/***************************************************************************************************************************/
/** \brief This checks an installer result. \throws RuntimeException if it differs. */
function deployCheck($expected, $actual, string $label): void {
    global $checks;
    ++$checks;
    if ($expected !== $actual) { throw new RuntimeException($label.' failed.'); }
}

// The web directory can be a nested served directory, so its URL prefix must survive deployment reporting.
foreach ([
    ['https://example.com', 'timezone', 'https://example.com/timezone/'],
    ['https://example.com/', 'timezones', 'https://example.com/timezones/'],
    ['https://recovrr.org/recovrr/timezones', 'timezone', 'https://recovrr.org/recovrr/timezones/timezone/'],
    ['https://recovrr.org/recovrr/timezones/', 'timezone', 'https://recovrr.org/recovrr/timezones/timezone/'],
    ['http://localhost:8080/services', 'timezone', 'http://localhost:8080/services/timezone/'],
    ['https://example.com/a%20directory/', 'tz_service-2', 'https://example.com/a%20directory/tz_service-2/'],
] as [$webUrl, $endpoint, $expected]) {
    deployCheck($expected, deployServiceUrl($webUrl, $endpoint), 'Preserve the public directory URL and append one service subdirectory');
}
foreach (['', '/timezone', 'recovrr.org/recovrr', 'ftp://example.com', 'https://user:password@example.com',
    'https://example.com/?secret=old', 'https://example.com/#section', 'https://example.com/a b',
    'https://example.com/path\\other'] as $webUrl) {
    try { deployServiceUrl($webUrl, 'timezone'); throw new RuntimeException('Expected web URL refusal.'); }
    catch (InvalidArgumentException $error) { deployCheck(true, str_contains($error->getMessage(), 'full http://'), 'Reject an ambiguous or unsuitable web directory URL'); }
}

$name = 'lgv_tz_deploy_test_'.bin2hex(random_bytes(8));
$settings = ['driver' => $driver, 'host' => getenv('LGV_TZ_TEST_HOST') ?: 'localhost',
    'port' => (int)(getenv('LGV_TZ_TEST_PORT') ?: ($driver === 'pgsql' ? 5432 : 3306)),
    'database' => $name, 'user' => getenv('LGV_TZ_TEST_USER') ?: ($driver === 'pgsql' ? (getenv('USER') ?: get_current_user()) : 'root'),
    'password' => getenv('LGV_TZ_TEST_PASSWORD') ?: '', 'admin_database' => getenv('LGV_TZ_TEST_ADMIN_DATABASE') ?: 'postgres'];
$admin = deployConnection($settings, true);
$admin->exec('CREATE DATABASE '.deployIdentifier($name, $driver));
$work = sys_get_temp_dir().'/lgv-tz-deploy-test-'.bin2hex(random_bytes(8));
mkdir($work, 0700);
try {
    // Fresh installations can use directories prepared by the operator. Publication and rollback preserve ownership.
    foreach (['before-publication', 'interrupted', 'interrupted-before-stage', 'interrupted-both',
        'published', 'new-file', 'public-new-file', 'changed-identity'] as $scenario) {
        $caseWork = $work.'/empty-'.$scenario;
        mkdir($caseWork, 0700);
        $privateTarget = $caseWork.'/private'; $publicTarget = $caseWork.'/public';
        mkdir($privateTarget, 0750); mkdir($publicTarget, 0700);
        $empty = deployEmptyDirectories(['private' => $privateTarget, 'public' => $publicTarget]);
        deployCheck(['private', 'public'], array_keys($empty), 'Recognize pre-existing empty destinations');
        $token = bin2hex(random_bytes(32));
        $directoryState = ['private' => $privateTarget, 'public' => $publicTarget,
            'private_stage' => $caseWork.'/private-stage', 'public_stage' => $caseWork.'/public-stage',
            'token' => $token, 'published' => false, 'empty_directories' => $empty];
        foreach (['private_stage', 'public_stage'] as $key) {
            mkdir($directoryState[$key], 0700);
            file_put_contents($directoryState[$key].'/.lgv-tz-install-owner', $token);
            file_put_contents($directoryState[$key].'/fixture.txt', 'installed file');
        }
        deploySave($caseWork, $directoryState);
        $privatePermissions = fileperms($privateTarget) & 0777;
        $privateOwner = fileowner($privateTarget); $privateGroup = filegroup($privateTarget);
        if ($scenario === 'interrupted') {
            // Stop after the private directory switched, while the original public directory is still in place.
            rename($privateTarget, $empty['private']['backup']);
            rename($directoryState['private_stage'], $privateTarget);
        } elseif ($scenario === 'interrupted-before-stage') {
            rename($privateTarget, $empty['private']['backup']);
        } elseif ($scenario === 'interrupted-both') {
            deployPublishDirectories($directoryState);
        } elseif ($scenario === 'published') {
            deployPublishDirectories($directoryState);
            $directoryState['published'] = true;
            deploySave($caseWork, $directoryState);
        } elseif ($scenario === 'new-file' || $scenario === 'public-new-file') {
            $noteTarget = $scenario === 'new-file' ? $privateTarget : $publicTarget;
            file_put_contents($noteTarget.'/.operator-note', 'keep this new file');
            try { deployPublishDirectories($directoryState); throw new RuntimeException('Expected changed-directory refusal.'); }
            catch (RuntimeException $error) { deployCheck(true, str_contains($error->getMessage(), 'changed during'), 'Refuse publication after a hidden file appears'); }
        } elseif ($scenario === 'changed-identity') {
            rename($privateTarget, $caseWork.'/original-private');
            mkdir($privateTarget, 0700);
            try { deployPublishDirectories($directoryState); throw new RuntimeException('Expected replaced-directory refusal.'); }
            catch (RuntimeException $error) { deployCheck(true, str_contains($error->getMessage(), 'changed during'), 'Refuse an empty directory replaced during installation'); }
            // The replacement belongs to someone else. Recovery refuses it without deleting it.
            try { deployCleanup($caseWork); throw new RuntimeException('Expected replacement ownership refusal.'); }
            catch (RuntimeException $error) { deployCheck(true, str_contains($error->getMessage(), 'ownership differs'), 'Cleanup preserves the replacement directory'); }
            deployCheck(true, is_dir($privateTarget), 'Replacement directory survives');
            rmdir($privateTarget); rename($caseWork.'/original-private', $privateTarget);
        }
        deployCleanup($caseWork);
        deployCheck(false, is_file($caseWork.'/deployment.json'), 'Cleanup completes for empty destinations');
        deployCheck([], glob($caseWork.'/.lgv-tz-empty-*'), 'No empty-directory backups left');
        if ($scenario === 'published') {
            deployCheck('installed file', file_get_contents($privateTarget.'/fixture.txt'), 'Publish into the selected private directory');
            deployCheck('installed file', file_get_contents($publicTarget.'/fixture.txt'), 'Publish into the selected public directory');
        } else {
            deployCheck(true, deploySameDirectory($privateTarget, $empty['private']), 'Preserve the original private directory inode');
            deployCheck(true, deploySameDirectory($publicTarget, $empty['public']), 'Preserve the original public directory inode');
            deployCheck($privatePermissions, fileperms($privateTarget) & 0777, 'Preserve original private directory permissions');
            deployCheck($privateOwner, fileowner($privateTarget), 'Preserve original private directory owner');
            deployCheck($privateGroup, filegroup($privateTarget), 'Preserve original private directory group');
            deployCheck(false, is_file($privateTarget.'/.lgv-tz-install-owner'), 'Do not leave installation ownership in the original empty directory');
            if ($scenario === 'new-file' || $scenario === 'public-new-file') {
                deployCheck('keep this new file', file_get_contents($noteTarget.'/.operator-note'), 'Preserve a file added during setup');
            }
        }
    }

    // An unrelated nonempty directory is not classified as a fresh destination, including hidden files and links.
    mkdir($work.'/nonempty', 0700); file_put_contents($work.'/nonempty/.hidden', 'keep');
    deployCheck([], deployEmptyDirectories(['private' => $work.'/nonempty']), 'Nonempty directories are not treated as fresh targets');
    symlink($work.'/nonempty', $work.'/linked');
    try { deployEmptyDirectories(['private' => $work.'/linked']); throw new RuntimeException('Expected linked-target refusal.'); }
    catch (RuntimeException $error) { deployCheck(true, str_contains($error->getMessage(), 'file or link'), 'Refuse linked destination directories'); }

    $connection = deployConnection($settings);
    $connection->exec('CREATE TABLE unrelated (value INTEGER)');
    $connection->exec('INSERT INTO unrelated VALUES (42)');
    foreach ([false, true] as $published) {
        $token = bin2hex(random_bytes(32));
        $connection->exec('CREATE TABLE timezones (id INTEGER)');
        $connection->exec('CREATE TABLE lgv_tz_install_guard (token VARCHAR(64))');
        $statement = $connection->prepare('INSERT INTO lgv_tz_install_guard VALUES (?)');
        $statement->execute([$token]);
        $statement = null;
        $state = ['settings' => $settings, 'token' => $token, 'guard_created' => true, 'database_created' => false,
            'published' => $published, 'private_stage' => $work.'/private-stage', 'public_stage' => $work.'/public-stage',
            'private' => $work.'/private', 'public' => $work.'/public'];
        foreach (['private', 'public', 'private_stage', 'public_stage'] as $key) {
            mkdir($state[$key], 0700); file_put_contents($state[$key].'/.lgv-tz-install-owner', $token);
        }
        deploySave($work, $state);
        file_put_contents($work.'/boundaries.zip', 'temporary'); file_put_contents($work.'/boundaries.json', 'temporary');
        deployCleanup($work);
        deployCheck($published, deployTableExists($connection, 'timezones'), 'Keep completed table / remove incomplete table');
        deployCheck(false, deployTableExists($connection, 'lgv_tz_install_guard'), 'Remove installer guard');
        deployCheck(42, (int)$connection->query('SELECT value FROM unrelated')->fetchColumn(), 'Preserve unrelated data');
        deployCheck(false, is_dir($state['private_stage']), 'Remove private staging directory');
        deployCheck(false, is_dir($state['public_stage']), 'Remove public staging directory');
        deployCheck($published, is_dir($state['private']), 'Keep only published private directory');
        deployCheck($published, is_dir($state['public']), 'Keep only published public directory');
        deployCheck(false, is_file($work.'/boundaries.zip'), 'Remove ZIP');
        deployCheck(false, is_file($work.'/boundaries.json'), 'Remove GeoJSON');
        deployCheck(false, is_file($work.'/deployment.json'), 'Remove recovery state after successful cleanup');
        if ($published) {
            $connection->exec('DROP TABLE timezones');
            deployRemoveTree($state['private']); deployRemoveTree($state['public']);
        }
    }
    $connection->exec('CREATE TABLE timezones (id INTEGER)');
    $connection->exec("CREATE TABLE lgv_tz_install_guard (token VARCHAR(64))");
    $connection->exec("INSERT INTO lgv_tz_install_guard VALUES ('different-owner')");
    $state['published'] = false; $state['guard_created'] = true;
    deploySave($work, $state);
    file_put_contents($work.'/boundaries.zip', 'temporary');
    try { deployCleanup($work); throw new RuntimeException('Expected ownership refusal.'); }
    catch (RuntimeException $error) { deployCheck(true, str_contains($error->getMessage(), 'ownership differs'), 'Reject foreign database ownership'); }
    deployCheck(true, deployTableExists($connection, 'timezones'), 'Preserve foreign table');
    deployCheck(false, is_file($work.'/boundaries.zip'), 'Downloads removed before ownership refusal');
    deployCheck(true, is_file($work.'/deployment.json'), 'Retain refused cleanup record');
    $connection->exec('DROP TABLE lgv_tz_install_guard');
    $state['guard_created'] = false;
    mkdir($state['private'], 0700);
    file_put_contents($state['private'].'/.lgv-tz-install-owner', 'different-directory-owner');
    deploySave($work, $state);
    try { deployCleanup($work); throw new RuntimeException('Expected directory refusal.'); }
    catch (RuntimeException $error) { deployCheck(true, str_contains($error->getMessage(), 'ownership differs'), 'Reject foreign directory ownership'); }
    deployCheck(true, is_dir($state['private']), 'Preserve foreign directory');
    deployCheck(0600, fileperms($work.'/deployment.json') & 0777, 'Recovery credentials are owner-only');

    // Drive the real prompt flow with an in-memory terminal. An existing table must stop setup before any download.
    mkdir($work.'/web', 0700); mkdir($work.'/fresh-run', 0700);
    $answers = [$driver, $settings['host'], (string)$settings['port'], $name, $settings['user'], $settings['password']];
    if ($driver === 'pgsql') { $answers[] = $settings['admin_database']; }
    $answers = array_merge($answers, ['n', $work.'/web', 'https://example.com/nested', 'service', $work.'/installer-private', posix_getgrgid(posix_getegid())['name']]);
    $terminal = fopen('php://memory', 'w+b');
    fwrite($terminal, implode("\n", $answers)."\n"); rewind($terminal);
    try { deployRun($work.'/fresh-run', true, $terminal); throw new RuntimeException('Expected existing-table refusal.'); }
    catch (RuntimeException $error) { deployCheck(true, str_contains($error->getMessage(), 'already contains'), 'Real installer refuses existing timezone data'); }
    finally { fclose($terminal); }
    deployCleanup($work.'/fresh-run');
    deployCheck(true, deployTableExists($connection, 'timezones'), 'Refused installation preserves existing table');
    deployCheck([], glob($work.'/web/*'), 'Refused installation publishes no endpoint');
    deployCheck(false, is_dir($work.'/installer-private'), 'Refused installation leaves no private application');
    deployCheck(false, is_file($work.'/fresh-run/deployment.json'), 'Refused installation cleanup completes');

    // The same real prompt flow must accept an existing empty private directory and empty public service directory.
    mkdir($work.'/installer-private', 0750); mkdir($work.'/web/service', 0755);
    $originalEmpty = deployEmptyDirectories(['private' => $work.'/installer-private', 'public' => $work.'/web/service']);
    $terminal = fopen('php://memory', 'w+b');
    fwrite($terminal, implode("\n", $answers)."\n"); rewind($terminal);
    try { deployRun($work.'/fresh-run', true, $terminal); throw new RuntimeException('Expected existing-table refusal.'); }
    catch (RuntimeException $error) { deployCheck(true, str_contains($error->getMessage(), 'already contains'), 'Empty destinations reach database validation'); }
    finally { fclose($terminal); }
    deployCleanup($work.'/fresh-run');
    foreach (['private' => $work.'/installer-private', 'public' => $work.'/web/service'] as $key => $path) {
        deployCheck(true, deploySameDirectory($path, $originalEmpty[$key]), 'Preserve pre-existing empty '.$key.' directory on early failure');
        deployCheck(true, deployDirectoryIsEmpty($path), 'Early failure leaves '.$key.' directory empty');
    }
    $connection->exec('DROP TABLE timezones');
    $terminal = fopen('php://memory', 'w+b');
    fwrite($terminal, implode("\n", $answers)."\n"); rewind($terminal);
    try {
        deployRun($work.'/fresh-run', true, $terminal, static function(): void { throw new RuntimeException('Fixture fresh Composer failure'); });
        throw new RuntimeException('Expected fresh package failure.');
    } catch (RuntimeException $error) { deployCheck(true, str_contains($error->getMessage(), 'Fixture fresh Composer failure'), 'Empty destinations reach package installation'); }
    finally { fclose($terminal); }
    deployCleanup($work.'/fresh-run');
    deployCheck(false, deployTableExists($connection, 'lgv_tz_install_guard'), 'Package failure removes only its database guard');
    foreach (['private' => $work.'/installer-private', 'public' => $work.'/web/service'] as $key => $path) {
        deployCheck(true, deploySameDirectory($path, $originalEmpty[$key]), 'Package failure retains original '.$key.' directory');
        deployCheck(true, deployDirectoryIsEmpty($path), 'Package failure leaves '.$key.' directory empty');
    }

    // A database recorded as newly created by this installer is removed on failure, even before table loading.
    $ownedName = $name.'_owned';
    $admin->exec('CREATE DATABASE '.deployIdentifier($ownedName, $driver));
    $ownedState = $state;
    $ownedState['settings']['database'] = $ownedName;
    $ownedState['guard_created'] = false; $ownedState['database_created'] = true;
    foreach (['private', 'public', 'private_stage', 'public_stage'] as $key) { $ownedState[$key] = $work.'/absent-'.$key; }
    deploySave($work.'/fresh-run', $ownedState);
    deployCleanup($work.'/fresh-run');
    $statement = $admin->prepare($driver === 'pgsql' ? 'SELECT COUNT(*) FROM pg_database WHERE datname = ?'
        : 'SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
    $statement->execute([$ownedName]);
    deployCheck(0, (int)$statement->fetchColumn(), 'Rollback removes newly created database');
    $statement = null;

    // Refresh a populated, installer-owned service with a small local package fixture; no downloads are needed.
    $fixtureAutoloader = require dirname(__DIR__).'/vendor/autoload.php';
    $database = new LGV_TZ_Lookup_Database($name, $settings['user'], $settings['password'], $driver, $settings['host'], $settings['port']);
    $database->reset_database();
    $database->store_entity(new LGV_TZ_Lookup_Entity('Asia/Tokyo', ['east' => 4, 'west' => 0, 'north' => 4, 'south' => 0],
        [[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]]));
    $private = $work.'/refresh-private'; $public = $work.'/web/refresh-service';
    mkdir($private.'/app/vendor', 0700, true); mkdir($private.'/tools', 0700); mkdir($public, 0700);
    $originalToken = bin2hex(random_bytes(32));
    foreach ([$private, $public] as $directory) { file_put_contents($directory.'/.lgv-tz-install-owner', $originalToken); }
    $config = "<?php\n// Keep this comment and the original credentials.\n";
    foreach (['g_dbName' => $name, 'g_dbUserName' => $settings['user'], 'g_dbPassword' => $settings['password'],
        'g_dbType' => $driver, 'g_dbHost' => $settings['host'], 'g_dbPort' => $settings['port'], 'g_server_secret' => 'kept-secret'] as $key => $value) {
        $config .= '$'.$key.' = '.var_export($value, true).";\n";
    }
    file_put_contents($private.'/config.php', $config);
    file_put_contents($private.'/installation.json', json_encode(['release' => 'original-boundaries', 'archive_sha256' => 'original-digest', 'driver' => $driver, 'polygons' => 1]));
    file_put_contents($private.'/app/vendor/autoload.php', '<?php');
    file_put_contents($public.'/index.php', '<?php echo "old endpoint";');
    file_put_contents($private.'/notes.txt', 'keep private notes'); file_put_contents($public.'/extra.txt', 'keep public file');
    copy(dirname(__DIR__).'/update.sh', $private.'/update.sh');
    foreach (['update.php', 'Setup.php'] as $file) { copy(dirname(__DIR__).'/tools/'.$file, $private.'/tools/'.$file); }
    $refreshWork = $work.'/refresh-run'; mkdir($refreshWork, 0700);
    $existing = deployExistingInstallation($private, $public, $settings);
    $group = posix_getegid();
    $serviceUrl = 'https://example.com/nested/refresh-service/';
    $snapshot = static function() use ($connection, $driver): array {
        $polygon = $driver === 'pgsql' ? "encode(polygon, 'hex')" : 'HEX(polygon)';
        return $connection->query('SELECT id, tzname, east, west, north, south, '.$polygon.' AS polygon FROM timezones ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    };
    $rowsBefore = $snapshot();
    try {
        deployRefresh($refreshWork, $settings, $private, $public, $group, $serviceUrl, $existing,
            static function(): void { throw new RuntimeException('Fixture Composer failure'); });
        throw new RuntimeException('Expected failed refresh.');
    } catch (RuntimeException $error) { deployCheck(true, str_contains($error->getMessage(), 'Fixture Composer failure'), 'Failed package install refuses publication'); }
    // Simulate interruption between the two directory publications, then run the shell's recovery entry point.
    $refreshState = json_decode(file_get_contents($refreshWork.'/deployment.json'), true);
    rename($private, $refreshState['private_backup']); rename($refreshState['private_stage'], $private);
    deployCleanup($refreshWork);
    deployCheck($config, file_get_contents($private.'/config.php'), 'Interrupted refresh restores original configuration');
    deployCheck('<?php echo "old endpoint";', file_get_contents($public.'/index.php'), 'Interrupted refresh preserves original endpoint');
    deployCheck($originalToken, file_get_contents($private.'/.lgv-tz-install-owner'), 'Interrupted refresh restores original ownership');
    deployCheck($rowsBefore, $snapshot(), 'Failed refresh leaves polygons unchanged');

    // The real installer loads only the staged application's autoloader. Avoid the test checkout taking precedence.
    class_exists(LGV_TZ_Lookup_Query::class); class_exists(Composer\InstalledVersions::class);
    $fixtureAutoloader->unregister();

    // Build the package directories first, then use the real current source and one known location.
    $installFixture = static function(string $app, string $driver) use ($options, $work): void {
        $locationFixture = '<?php $test_locations_param_array = [["title" => "Fixture location", "params" => ["lng" => 2, "lat" => 2], "result" => "Asia/Tokyo"]];';
        if (isset($options['real-composer'])) {
            $source = $work.'/fixture-package';
            if (!is_dir($source)) {
                mkdir($source, 0755);
                copy(dirname(__DIR__).'/composer.json', $source.'/composer.json');
                copy(dirname(__DIR__).'/LICENSE', $source.'/LICENSE');
                deployCopyTree(dirname(__DIR__).'/src', $source.'/src');
                file_put_contents($source.'/src/TestLocations.php', $locationFixture);
            }
            LGV_TZ_Lookup_Setup::install($app, $driver, $source);
            return;
        }
        $package = $app.'/vendor/littlegreenviper/lgv_tz_lookup';
        mkdir($package, 0755, true);
        deployCopyTree(dirname(__DIR__).'/src', $package.'/src');
        file_put_contents($package.'/src/TestLocations.php', $locationFixture);
        file_put_contents($app.'/vendor/autoload.php', '<?php require '.var_export(dirname(__DIR__).'/vendor/autoload.php', true).';');
        Composer\InstalledVersions::reload(['root' => ['name' => 'fixture/application', 'install_path' => $app],
            'versions' => [LGV_TZ_Lookup_Setup::PACKAGE => ['install_path' => $package]]]);
    };
    $refreshAnswers = [$driver, $settings['host'], (string)$settings['port'], $name, $settings['user'], $settings['password']];
    if ($driver === 'pgsql') { $refreshAnswers[] = $settings['admin_database']; }
    $refreshAnswers = array_merge($refreshAnswers, ['n', $work.'/web', 'https://example.com/nested', 'refresh-service', $private, posix_getgrgid($group)['name']]);
    $terminal = fopen('php://memory', 'w+b'); fwrite($terminal, implode("\n", $refreshAnswers)."\n"); rewind($terminal);
    ob_start();
    try { deployRun($refreshWork, true, $terminal, $installFixture); $report = ob_get_contents(); }
    finally { ob_end_clean(); fclose($terminal); }
    deployCheck($config, file_get_contents($private.'/config.php'), 'Refresh preserves exact configuration bytes and secret');
    deployCheck('original-boundaries', json_decode(file_get_contents($private.'/installation.json'), true)['release'], 'Refresh retains boundary release');
    deployCheck($rowsBefore, $snapshot(), 'Successful refresh leaves polygons unchanged');
    deployCheck(42, (int)$connection->query('SELECT value FROM unrelated')->fetchColumn(), 'Refresh preserves unrelated data');
    deployCheck('keep private notes', file_get_contents($private.'/notes.txt'), 'Refresh preserves additional private files');
    deployCheck('keep public file', file_get_contents($public.'/extra.txt'), 'Refresh preserves additional public files');
    deployCheck(true, str_contains($report, 'Test request: '.$serviceUrl.'?test&secret=kept-secret'), 'Refresh prints full test URL with existing secret');
    deployCheck(false, is_file($refreshWork.'/deployment.json'), 'Successful refresh removes recovery record');
    deployCheck([], glob($work.'/.lgv-tz-previous-*'), 'Successful refresh removes private backup');
    deployCheck([], glob($work.'/web/.lgv-tz-previous-*'), 'Successful refresh removes public backup');
    $process = proc_open([PHP_BINARY, $public.'/index.php', 'll=2,2', 'secret=kept-secret'],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
    $response = stream_get_contents($pipes[1]); fclose($pipes[1]);
    deployCheck(0, proc_close($process), 'Refreshed public entry point runs');
    deployCheck('Asia/Tokyo', $response, 'Refreshed endpoint uses preserved private config and existing polygons');

    $refreshedEndpoint = file_get_contents($public.'/index.php');
    $existing = deployExistingInstallation($private, $public, $settings);
    // A real deployment loads one Composer application per PHP process. The fixture path can test failures again in-process.
    if (!isset($options['real-composer'])) {
        $badFixture = static function(string $app, string $driver) use ($installFixture): void {
            $installFixture($app, $driver);
            file_put_contents($app.'/vendor/littlegreenviper/lgv_tz_lookup/src/TestLocations.php',
                '<?php $test_locations_param_array = [["params" => ["lng" => 2, "lat" => 2], "result" => "Wrong/Zone"]];');
        };
        try {
            deployRefresh($refreshWork, $settings, $private, $public, $group, $serviceUrl, $existing, $badFixture);
            throw new RuntimeException('Expected lookup-validation failure.');
        } catch (RuntimeException $error) { deployCheck(true, str_contains($error->getMessage(), 'known-location tests failed'), 'Refuse code that fails existing boundary tests'); }
        deployCleanup($refreshWork);
        deployCheck($refreshedEndpoint, file_get_contents($public.'/index.php'), 'Failed validation preserves the working public entry point');
        deployCheck($rowsBefore, $snapshot(), 'Failed validation leaves populated boundaries unchanged');
        deployCheck($config, file_get_contents($private.'/config.php'), 'Failed validation preserves configuration');
    }
    $differentSettings = $settings; $differentSettings['database'] .= '_different';
    try { deployExistingInstallation($private, $public, $differentSettings); throw new RuntimeException('Expected configuration refusal.'); }
    catch (RuntimeException $error) { deployCheck(true, str_contains($error->getMessage(), 'settings differ'), 'Refuse refresh with different database settings'); }
    echo 'Passed '.$checks.' '.$driver." deployment recovery checks.\n";
} finally {
    $connection = null; $statement = null; $database = null; $snapshot = null;
    $admin->exec('DROP DATABASE '.deployIdentifier($name, $driver));
    deployRemoveTree($work);
}
