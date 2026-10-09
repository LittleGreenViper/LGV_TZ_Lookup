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
    No boundaries are downloaded. The test database and working directories are removed afterward.
*/
declare(strict_types=1);
require dirname(__DIR__).'/tools/deploy.php';
$options = getopt('', ['driver:']);
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
    $answers = array_merge($answers, ['n', $work.'/web', 'service', $work.'/installer-private', posix_getgrgid(posix_getegid())['name']]);
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
    echo 'Passed '.$checks.' '.$driver." deployment recovery checks.\n";
} finally {
    $connection = null; $statement = null;
    $admin->exec('DROP DATABASE '.deployIdentifier($name, $driver));
    deployRemoveTree($work);
}
