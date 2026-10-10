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
    \brief This installs a tested HTTP timezone service, with private configuration and a default server secret.

    deploy.sh supplies a private working directory. The worker asks for settings through the terminal, installs
    the Composer package, loads current boundaries, and checks known locations before publishing a small endpoint.
    A separate cleanup process removes downloads and rolls back an incomplete installation after errors or OOM.
    Database ownership is verified with a random guard token before cleanup can remove a newly loaded table.
*/

declare(strict_types=1);
require_once __DIR__.'/Setup.php';

/***************************************************************************************************************************/
/** \brief This quotes a database identifier for the selected backend. */
function deployIdentifier(string $name, string $driver): string {
    $quote = $driver === 'pgsql' ? '"' : '`';
    return $quote.str_replace($quote, $quote.$quote, $name).$quote;
}

/***************************************************************************************************************************/
/** \brief This saves recovery state atomically, with credentials readable only by the installer user. */
function deploySave(string $work, array $state): void {
    $file = $work.'/deployment.json';
    if (false === file_put_contents($file.'.new', json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) ||
        !chmod($file.'.new', 0600) || !rename($file.'.new', $file)) {
        throw new RuntimeException('Could not record installation recovery information.');
    }
}

/***************************************************************************************************************************/
/** \brief This opens the configured application or administrative database. \throws PDOException on failure. */
function deployConnection(array $settings, bool $administrative = false): PDO {
    $driver = $settings['driver'];
    $dsn = $driver.':host='.$settings['host'].';port='.$settings['port'];
    if (!$administrative || $driver === 'pgsql') {
        $dsn .= ';dbname='.($administrative ? $settings['admin_database'] : $settings['database']);
    }
    $dsn .= $driver === 'mysql' ? ';charset=utf8mb4' : ';options=--client_encoding=UTF8';
    return new PDO($dsn, $settings['user'], $settings['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/***************************************************************************************************************************/
/** \brief This prevents two installers from loading the same database concurrently. */
function deployLock(PDO $connection): void {
    $sql = $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
        ? "SELECT pg_try_advisory_lock(hashtext('LGV_TZ_Lookup.install'))"
        : "SELECT GET_LOCK(CONCAT('lgv_tz_', SHA2(DATABASE(), 224)), 0)";
    if (!$connection->query($sql)->fetchColumn()) {
        throw new RuntimeException('Another timezone installation is using this database.');
    }
}

/***************************************************************************************************************************/
/** \brief This checks for an existing table without attempting to create or replace it. */
function deployTableExists(PDO $connection, string $table): bool {
    $schema = $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? 'current_schema()' : 'DATABASE()';
    $statement = $connection->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '.$schema.' AND table_name = ?');
    $statement->execute([$table]);
    return (int)$statement->fetchColumn() > 0;
}

/***************************************************************************************************************************/
/**
    \brief This asks for a setting, with a default and optional password hiding.
    \throws RuntimeException if terminal input ends, or password echo cannot be disabled.
*/
function deployAsk($terminal, string $label, string $default = '', bool $hidden = false): string {
    fwrite(STDOUT, $label.($default === '' ? '' : ' ['.$default.']').': ');
    $mode = null;
    if ($hidden && stream_isatty($terminal)) {
        $process = proc_open(['stty', '-g'], [0 => $terminal, 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
        if (false === $process) { throw new RuntimeException('Could not read terminal settings.'); }
        $mode = trim(stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        if (proc_close($process) !== 0 || $mode === '') { throw new RuntimeException('Could not read terminal settings.'); }
        $process = proc_open(['stty', '-echo'], [0 => $terminal, 1 => STDOUT, 2 => STDERR], $pipes);
        if (false === $process || proc_close($process) !== 0) { throw new RuntimeException('Could not hide password input.'); }
    }
    try {
        if (stream_isatty($terminal)) {
            // fgets() can retry an interrupted terminal read indefinitely. Poll readiness so Ctrl+C can unwind
            // through the finally block and restore password echo before the shell starts cleanup.
            do {
                $read = [$terminal]; $write = $except = null;
                $ready = @stream_select($read, $write, $except, 0, 250000);
            } while ($ready === false || $ready === 0);
        }
        $answer = fgets($terminal);
        if (false === $answer) { throw new RuntimeException('Installation input ended before setup was complete.'); }
        $answer = $hidden ? rtrim($answer, "\r\n") : trim($answer);
        return $answer === '' ? $default : $answer;
    } finally {
        if ($mode !== null) {
            $process = proc_open(['stty', $mode], [0 => $terminal, 1 => STDOUT, 2 => STDERR], $pipes);
            if (is_resource($process)) { proc_close($process); }
            fwrite(STDOUT, "\n");
        }
    }
}

/***************************************************************************************************************************/
/** \brief Build the browser URL from the selected web directory's URL and the new service subdirectory. */
function deployServiceUrl(string $webUrl, string $endpoint): string {
    $webUrl = trim($webUrl);
    $parts = parse_url($webUrl);
    if (filter_var($webUrl, FILTER_VALIDATE_URL) === false || !is_array($parts) ||
        !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) ||
        isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) ||
        str_contains($webUrl, '\\')) {
        throw new InvalidArgumentException('Enter the full http:// or https:// URL of the web directory, without credentials, a query, or a fragment.');
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $endpoint)) {
        throw new InvalidArgumentException('Use a simple service directory name.');
    }
    return rtrim($webUrl, '/').'/'.$endpoint.'/';
}

/***************************************************************************************************************************/
/** \brief This removes a directory tree without following symlinks. */
function deployRemoveTree(string $directory): void {
    if (!is_dir($directory) || is_link($directory)) { return; }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $ok = $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        if (!$ok) { throw new RuntimeException('Could not remove '.$file->getPathname()); }
    }
    if (!rmdir($directory)) { throw new RuntimeException('Could not remove '.$directory); }
}

/***************************************************************************************************************************/
/** \brief Check an empty directory without following links. Hidden files also count as contents. */
function deployDirectoryIsEmpty(string $path): bool {
    clearstatcache(true, $path);
    return is_dir($path) && !is_link($path) && !(new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS))->valid();
}

/***************************************************************************************************************************/
/** \brief Recognize the exact original directory, including after it is moved aside for publication. */
function deploySameDirectory(string $path, array $original): bool {
    clearstatcache(true, $path);
    if (!is_dir($path) || is_link($path)) { return false; }
    $stat = lstat($path);
    return $stat !== false &&
        $stat['dev'] === $original['device'] && $stat['ino'] === $original['inode'];
}

/***************************************************************************************************************************/
/** \brief Record existing empty destinations so fresh installation can publish into them and roll back exactly. */
function deployEmptyDirectories(array $destinations): array {
    $empty = [];
    foreach ($destinations as $key => $path) {
        if (!file_exists($path) && !is_link($path)) { continue; }
        if (is_link($path) || !is_dir($path)) {
            throw new RuntimeException('Installation destination must be a new or empty directory, not a file or link: '.$path);
        }
        if (deployDirectoryIsEmpty($path)) {
            $stat = lstat($path);
            $empty[$key] = ['device' => $stat['dev'], 'inode' => $stat['ino'],
                'backup' => dirname($path).'/.lgv-tz-empty-'.bin2hex(random_bytes(12))];
        }
    }
    return $empty;
}

/***************************************************************************************************************************/
/** \brief Publish fresh staged code, retaining original empty directories until installation is committed. */
function deployPublishDirectories(array $state): void {
    foreach (['private', 'public'] as $key) {
        $path = $state[$key];
        $original = $state['empty_directories'][$key] ?? null;
        if ($original !== null) {
            if (!deploySameDirectory($path, $original) || !deployDirectoryIsEmpty($path)) {
                throw new RuntimeException('The selected empty directory changed during installation; refusing to replace '.$path);
            }
            if (file_exists($original['backup']) || is_link($original['backup']) || !rename($path, $original['backup'])) {
                throw new RuntimeException('Could not retain the original empty directory for recovery: '.$path);
            }
        } elseif (file_exists($path) || is_link($path)) {
            throw new RuntimeException('Installation destination appeared during setup; refusing to replace '.$path);
        }
        if (!rename($state[$key.'_stage'], $path)) {
            throw new RuntimeException('Could not publish the installation directories.');
        }
    }
}

/***************************************************************************************************************************/
/** \brief Restore the original empty directories on failure, or remove their empty backups after success. */
function deployEmptyDirectoryCleanup(array $state): void {
    foreach ($state['empty_directories'] ?? [] as $key => $original) {
        $backup = $original['backup'];
        if (!file_exists($backup) && !is_link($backup)) { continue; }
        if (!deploySameDirectory($backup, $original)) {
            throw new RuntimeException('Original directory identity differs; refusing recovery for '.$backup);
        }
        if (!empty($state['published'])) {
            if (!deployDirectoryIsEmpty($backup) || !rmdir($backup)) {
                throw new RuntimeException('Could not remove the original empty-directory backup: '.$backup);
            }
        } else {
            if (file_exists($state[$key]) || is_link($state[$key]) || !rename($backup, $state[$key])) {
                throw new RuntimeException('Could not restore the original empty directory: '.$state[$key]);
            }
        }
    }
}

/***************************************************************************************************************************/
/** \brief Verify an existing installer-owned pair of directories and its unchanged database configuration. */
function deployExistingInstallation(string $private, string $public, array $settings): array {
    foreach ([$private, $public] as $directory) {
        if (!is_dir($directory) || is_link($directory) || !is_file($directory.'/.lgv-tz-install-owner') ||
            is_link($directory.'/.lgv-tz-install-owner')) {
            throw new RuntimeException('Existing directories are not a matching installer-owned service; choose new paths.');
        }
    }
    $token = trim(file_get_contents($private.'/.lgv-tz-install-owner'));
    if (!preg_match('/^[a-f0-9]{64}$/D', $token) || !hash_equals($token, trim(file_get_contents($public.'/.lgv-tz-install-owner')))) {
        throw new RuntimeException('Installation directory ownership differs; refusing to refresh.');
    }
    foreach (['app', 'tools'] as $directory) {
        if (!is_dir($private.'/'.$directory) || is_link($private.'/'.$directory)) {
            throw new RuntimeException('Existing installation contains an unsupported linked directory; refusing to refresh.');
        }
    }
    if (!is_file($public.'/index.php') || is_link($public.'/index.php')) {
        throw new RuntimeException('Existing public entry point is missing or linked; refusing to refresh.');
    }
    foreach (['config.php', 'installation.json', 'app/vendor/autoload.php', 'update.sh', 'tools/update.php', 'tools/Setup.php'] as $file) {
        if (!is_file($private.'/'.$file) || is_link($private.'/'.$file)) {
            throw new RuntimeException('Existing installation is incomplete; refusing to refresh.');
        }
    }
    $configuration = (static function(string $file): array {
        require $file;
        return ['settings' => ['database' => $g_dbName ?? '', 'user' => $g_dbUserName ?? '', 'password' => $g_dbPassword ?? '',
            'driver' => strtolower($g_dbType ?? 'mysql'), 'host' => $g_dbHost ?? '127.0.0.1',
            'port' => (int)($g_dbPort ?? (($g_dbType ?? 'mysql') === 'pgsql' ? 5432 : 3306))],
            'secret' => $g_server_secret ?? ''];
    })($private.'/config.php');
    foreach (['database', 'user', 'password', 'driver', 'host', 'port'] as $key) {
        if ($settings[$key] !== $configuration['settings'][$key]) {
            throw new RuntimeException('Database settings differ from the existing private configuration; use the original settings to refresh.');
        }
    }
    $metadata = json_decode(file_get_contents($private.'/installation.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($metadata) || ($metadata['driver'] ?? '') !== $settings['driver'] || !is_string($configuration['secret'])) {
        throw new RuntimeException('Existing installation metadata is invalid; refusing to refresh.');
    }
    return ['token' => $token, 'metadata' => $metadata, 'secret' => $configuration['secret']];
}

/***************************************************************************************************************************/
/** \brief Copy an installation for staging, preserving additional files, permissions, and symbolic links. */
function deployCopyTree(string $source, string $destination): void {
    $mode = fileperms($source) & 0777;
    if (!mkdir($destination, $mode) || !chmod($destination, $mode)) { throw new RuntimeException('Could not stage the installation.'); }
    foreach (new DirectoryIterator($source) as $entry) {
        if ($entry->isDot()) { continue; }
        $target = $destination.'/'.$entry->getFilename();
        if ($entry->isLink()) { $ok = symlink(readlink($entry->getPathname()), $target); }
        elseif ($entry->isDir()) { deployCopyTree($entry->getPathname(), $target); continue; }
        else { $ok = copy($entry->getPathname(), $target) && chmod($target, $entry->getPerms() & 0777); }
        if (!$ok) { throw new RuntimeException('Could not stage '.$entry->getFilename()); }
    }
}

/***************************************************************************************************************************/
/** \brief Generate the public entry point with the chosen private configuration and package paths. */
function deployEndpointCode(string $private, string $relativePackage, string $licenseHeader): string {
    return $licenseHeader.
        "define('__CONFIG_FILE_', ".var_export($private.'/config.php', true).");\n".
        "ini_set('display_errors', '0');\ntry {\n    require ".var_export($private.'/app/vendor/autoload.php', true).";\n".
        '    require '.var_export($private.$relativePackage.'/src/index.php', true).";\n} catch (Throwable \$error) {\n".
        "    http_response_code(503);\n    error_log('Timezone service: '.\$error->getMessage());\n    echo 'Timezone service unavailable.';\n}\n";
}

/***************************************************************************************************************************/
/** \brief Restore prior directories after an interrupted refresh, or remove backups after publication. Never changes database tables. */
function deployRefreshCleanup(string $work, array $state): void {
    $verify = static function(string $path, string $token): void {
        if (is_link($path) || !is_dir($path) || !is_file($path.'/.lgv-tz-install-owner') || is_link($path.'/.lgv-tz-install-owner') ||
            !hash_equals($token, trim(file_get_contents($path.'/.lgv-tz-install-owner')))) {
            throw new RuntimeException('Refresh directory ownership differs; refusing recovery for '.$path);
        }
    };
    foreach (['private', 'public'] as $key) {
        $backup = $state[$key.'_backup'];
        if (!file_exists($backup) && !is_link($backup)) { continue; }
        $verify($backup, $state['previous_token']);
        if (empty($state['published'])) {
            if (file_exists($state[$key]) || is_link($state[$key])) {
                $verify($state[$key], $state['token']);
                deployRemoveTree($state[$key]);
            }
            if (!rename($backup, $state[$key])) { throw new RuntimeException('Could not restore the prior installation.'); }
        } else {
            $verify($state[$key], $state['token']);
            deployRemoveTree($backup);
        }
    }
    foreach (['private_stage', 'public_stage'] as $key) {
        if (!file_exists($state[$key]) && !is_link($state[$key])) { continue; }
        $verify($state[$key], $state['token']);
        deployRemoveTree($state[$key]);
    }
    unlink($work.'/deployment.json');
}

/***************************************************************************************************************************/
/** \brief Refresh owned installed code using existing polygons, retaining the configuration and secret. */
function deployRefresh(string $work, array $settings, string $private, string $public, int $group,
    string $serviceUrl, array $existing, callable $install): void {
    $connection = deployConnection($settings);
    deployLock($connection);
    if (deployTableExists($connection, 'lgv_tz_install_guard')) {
        throw new RuntimeException('An installation recovery table remains; finish its cleanup before refreshing.');
    }
    $count = (int)$connection->query('SELECT COUNT(*) FROM timezones')->fetchColumn();
    if ($count === 0) { throw new RuntimeException('The existing boundary table is empty; refusing to refresh.'); }
    $suffix = bin2hex(random_bytes(12));
    $state = ['refresh' => true, 'token' => bin2hex(random_bytes(32)), 'previous_token' => $existing['token'],
        'private' => $private, 'public' => $public, 'private_stage' => dirname($private).'/.lgv-tz-install-'.$suffix,
        'public_stage' => dirname($public).'/.lgv-tz-install-'.$suffix,
        'private_backup' => dirname($private).'/.lgv-tz-previous-'.$suffix,
        'public_backup' => dirname($public).'/.lgv-tz-previous-'.$suffix, 'published' => false];
    deploySave($work, $state);
    foreach (['private', 'public'] as $key) {
        // Establish ownership before copying, so cleanup can always identify even an incomplete stage.
        if (!mkdir($state[$key.'_stage'], 0700) ||
            file_put_contents($state[$key.'_stage'].'/.lgv-tz-install-owner', $state['token']) === false) {
            throw new RuntimeException('Could not create refresh staging directories.');
        }
        foreach (new DirectoryIterator($state[$key]) as $entry) {
            if ($entry->isDot() || $entry->getFilename() === '.lgv-tz-install-owner') { continue; }
            $target = $state[$key.'_stage'].'/'.$entry->getFilename();
            if ($entry->isLink()) { $ok = symlink(readlink($entry->getPathname()), $target); }
            elseif ($entry->isDir()) { deployCopyTree($entry->getPathname(), $target); continue; }
            else { $ok = copy($entry->getPathname(), $target) && chmod($target, $entry->getPerms() & 0777); }
            if (!$ok) { throw new RuntimeException('Could not stage the existing installation.'); }
        }
    }
    $privateStage = $state['private_stage'];
    $publicStage = $state['public_stage'];
    echo "Refreshing installed code; keeping the existing boundaries, configuration, and secret.\n";
    deployRemoveTree($privateStage.'/app');
    mkdir($privateStage.'/app', 0755);
    $install($privateStage.'/app', $settings['driver'], dirname(__DIR__));
    $package = Composer\InstalledVersions::getInstallPath(LGV_TZ_Lookup_Setup::PACKAGE);
    $database = new LGV_TZ_Lookup_Database($settings['database'], $settings['user'], $settings['password'],
        $settings['driver'], $settings['host'], $settings['port']);
    require $package.'/src/TestLocations.php';
    $lookup = new LGV_TZ_Lookup_Query($database);
    $failures = 0;
    foreach ($test_locations_param_array as $case) {
        $lng = $case['params']['lng']; $lat = $case['params']['lat'];
        while ($lng < -180) { $lng += 360; } while ($lng > 180) { $lng -= 360; }
        while ($lat < -90) { $lat += 180; } while ($lat > 90) { $lat -= 180; }
        if ($lookup->get_tz($lng, $lat) !== $case['result']) { ++$failures; }
    }
    if ($failures) { throw new RuntimeException($failures.' known-location tests failed; the existing installation was retained.'); }
    foreach (['update.php', 'Setup.php'] as $file) {
        if (!copy(__DIR__.'/'.$file, $privateStage.'/tools/'.$file)) { throw new RuntimeException('Could not refresh updater code.'); }
    }
    if (!copy(dirname(__DIR__).'/update.sh', $privateStage.'/update.sh')) { throw new RuntimeException('Could not refresh the updater command.'); }
    $source = file_get_contents(__FILE__);
    $license = explode("/***************************************************************************************************************************/\n/**\n    \\file", $source, 2)[0];
    $relativePackage = substr($package, strlen($privateStage));
    if (file_put_contents($publicStage.'/index.php', deployEndpointCode($private, $relativePackage, $license)) === false) {
        throw new RuntimeException('Could not write the refreshed public entry point.');
    }
    foreach (['composer.phar', 'composer.sha256'] as $file) { if (is_file($privateStage.'/app/'.$file)) { unlink($privateStage.'/app/'.$file); } }
    foreach (['composer-cache', 'composer-home'] as $dir) { if (is_dir($privateStage.'/app/'.$dir)) { deployRemoveTree($privateStage.'/app/'.$dir); } }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($privateStage.'/app', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($files as $file) {
        if (!$file->isLink() && !chmod($file->getPathname(), $file->isDir() || ($file->getPerms() & 0111) ? 0755 : 0644)) {
            throw new RuntimeException('Could not set refreshed code permissions.');
        }
    }
    if (!chgrp($privateStage, $group) || !chgrp($privateStage.'/config.php', $group) ||
        !chmod($privateStage, 0750) || !chmod($privateStage.'/config.php', 0640) ||
        !chmod($privateStage.'/update.sh', 0755) || !chmod($publicStage, 0755) || !chmod($publicStage.'/index.php', 0644)) {
        throw new RuntimeException('Could not set refreshed installation permissions.');
    }
    $metadata = $existing['metadata'];
    $metadata['service_url'] = $serviceUrl;
    $metadata['known_location_checks'] = count($test_locations_param_array);
    if (file_put_contents($privateStage.'/installation.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
        throw new RuntimeException('Could not write refreshed installation metadata.');
    }
    foreach (['private', 'public'] as $key) {
        if (!rename($state[$key], $state[$key.'_backup']) || !rename($state[$key.'_stage'], $state[$key])) {
            throw new RuntimeException('Could not publish refreshed code; recovery will restore the prior installation.');
        }
    }
    $state['published'] = true;
    deploySave($work, $state);
    deployRefreshCleanup($work, $state);
    $secretQuery = $existing['secret'] === '' ? '' : '&secret='.rawurlencode($existing['secret']);
    echo 'Refreshed installed code; retained '.$count.' polygons; '.count($test_locations_param_array)." known locations passed.\n";
    echo 'Public endpoint: '.$public.'/index.php'."\nPrivate configuration: ".$private.'/config.php'."\n";
    echo 'Service URL: '.$serviceUrl."\n";
    echo 'Example request: '.$serviceUrl.'?ll=-77.036543,38.895037'.$secretQuery."\n";
    echo 'Test request: '.$serviceUrl.'?test'.$secretQuery."\n";
}

/***************************************************************************************************************************/
/**
    \brief This removes installer downloads, and rolls back only installation-owned resources on failure.
    A completed deployment retains its database and files. The guard token authorizes removal of an incomplete
    timezones table; an unrelated table or installation is never adopted. Failed cleanup retains recovery state.
*/
function deployCleanup(string $work): void {
    foreach (['boundaries.zip', 'boundaries.json'] as $file) {
        if (is_file($work.'/'.$file) && !unlink($work.'/'.$file)) { throw new RuntimeException('Could not remove downloaded '.$file); }
    }
    $file = $work.'/deployment.json';
    if (!is_file($file)) { return; }
    $state = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!empty($state['refresh'])) { deployRefreshCleanup($work, $state); return; }
    if (!empty($state['guard_created'])) {
        $connection = deployConnection($state['settings']);
        deployLock($connection);
        if (deployTableExists($connection, 'lgv_tz_install_guard')) {
            $statement = $connection->prepare('SELECT token FROM lgv_tz_install_guard');
            $statement->execute();
            $token = $statement->fetchColumn();
            $statement->closeCursor();
            $statement = null;
            if (!is_string($token) || !hash_equals($state['token'], $token)) {
                throw new RuntimeException('Installation ownership differs; refusing to remove database tables.');
            }
            if (empty($state['published'])) { $connection->exec('DROP TABLE IF EXISTS timezones'); }
            $connection->exec('DROP TABLE lgv_tz_install_guard');
        } elseif (empty($state['published'])) {
            throw new RuntimeException('Installation ownership record is missing; refusing to remove database tables.');
        }
        $state['guard_created'] = false;
        deploySave($work, $state);
        $connection = null;
    }
    if (empty($state['published']) && !empty($state['database_created'])) {
        deployConnection($state['settings'], true)->exec('DROP DATABASE '.deployIdentifier($state['settings']['database'], $state['settings']['driver']));
        $state['database_created'] = false;
        deploySave($work, $state);
    }
    $paths = [$state['private_stage'], $state['public_stage']];
    if (empty($state['published'])) { $paths = array_merge($paths, [$state['private'], $state['public']]); }
    foreach ($paths as $path) {
        if (!is_dir($path)) { continue; }
        foreach ($state['empty_directories'] ?? [] as $key => $original) {
            // An original destination not yet moved for publication was never owned by this installation.
            if ($path === $state[$key] && deploySameDirectory($path, $original)) { continue 2; }
        }
        $marker = $path.'/.lgv-tz-install-owner';
        if (!is_file($marker) || !hash_equals($state['token'], trim(file_get_contents($marker)))) {
            throw new RuntimeException('Directory ownership differs; refusing to remove '.$path);
        }
        deployRemoveTree($path);
    }
    deployEmptyDirectoryCleanup($state);
    unlink($file);
}

/***************************************************************************************************************************/
/**
    \brief This gathers settings, loads and tests the Composer package, then publishes the endpoint.
    The selected web directory must exist. New installations accept new or empty destinations.
    Existing installer-owned directories can be refreshed without reloading boundaries;
    private files must be outside the web document root. Existing timezones tables are refused before loading.
    \throws Exception if setup, validation, or publication fails.
*/
function deployRun(string $work, bool $noSecret = false, $terminal = null, ?callable $install = null): void {
    $started = hrtime(true);
    foreach (['curl', 'zip', 'mbstring', 'ctype', 'pdo', 'posix'] as $extension) {
        if (!extension_loaded($extension)) { throw new RuntimeException('The PHP '.$extension.' extension is required.'); }
    }
    if (!function_exists('proc_open')) { throw new RuntimeException('PHP proc_open is required.'); }
    $terminal = $terminal ?? @fopen('/dev/tty', 'r+');
    if (!is_resource($terminal)) { throw new RuntimeException('Run the installer from an interactive terminal.'); }
    echo "LGV_TZ_Lookup server installation\n\n";
    $driver = strtolower(deployAsk($terminal, 'Database driver (mysql or pgsql)', 'mysql'));
    if (!in_array($driver, ['mysql', 'pgsql'], true) || !extension_loaded('pdo_'.$driver)) {
        throw new RuntimeException('Choose mysql or pgsql, with its PHP PDO extension enabled.');
    }
    $settings = ['driver' => $driver, 'host' => deployAsk($terminal, 'Database host', 'localhost')];
    $port = deployAsk($terminal, 'Database port', $driver === 'pgsql' ? '5432' : '3306');
    if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) { throw new RuntimeException('Port must be between 1 and 65535.'); }
    $settings['port'] = (int)$port;
    $settings['database'] = deployAsk($terminal, 'Database name', 'tz_lookup');
    $maxName = $driver === 'pgsql' ? 63 : 64;
    if (!preg_match('/^[a-zA-Z0-9_-]{1,'.$maxName.'}$/D', $settings['database'])) {
        throw new RuntimeException('Use a database name containing letters, numbers, underscores, or hyphens.');
    }
    $settings['user'] = deployAsk($terminal, 'Database user', $driver === 'pgsql' ? (getenv('USER') ?: get_current_user()) : 'root');
    $settings['password'] = deployAsk($terminal, 'Database password (input hidden; empty is allowed)', '', true);
    $settings['admin_database'] = $driver === 'pgsql' ? deployAsk($terminal, 'Administrative database (used if creating a database)', 'postgres') : '';
    $create = strtolower(deployAsk($terminal, 'Create the database if it does not exist? (y/n)', 'y')) === 'y';
    $webRoot = realpath(deployAsk($terminal, 'Existing web directory (filesystem path; parent of the new service)', getcwd()));
    if (false === $webRoot || !is_dir($webRoot) || !is_writable($webRoot)) { throw new RuntimeException('The web directory must be an existing writable directory.'); }
    $webUrl = deployAsk($terminal, 'Public URL of that directory (e.g. https://example.com/path)');
    $endpoint = deployAsk($terminal, 'Service subdirectory (appended to the path and URL above)', 'timezone');
    $serviceUrl = deployServiceUrl($webUrl, $endpoint);
    $private = deployAsk($terminal, 'Private application directory (new or empty; outside the web root)', dirname($webRoot).'/lgv-tz-server');
    $parent = realpath(dirname($private));
    if (false === $parent || !is_writable($parent) || in_array(basename($private), ['.', '..', ''], true)) {
        throw new RuntimeException('The private application directory needs an existing writable parent.');
    }
    $private = $parent.'/'.basename($private);
    $sourceRoot = realpath(dirname(__DIR__));
    if ($private === $sourceRoot || str_starts_with($private, $sourceRoot.'/')) {
        throw new RuntimeException('Install private files outside the source checkout to avoid copying the installation into itself.');
    }
    if ($webRoot === '/' || $private === $webRoot || str_starts_with($private, $webRoot.'/')) {
        throw new RuntimeException('The private application directory must be outside the web document root.');
    }
    $public = $webRoot.'/'.$endpoint;
    $emptyDirectories = deployEmptyDirectories(['private' => $private, 'public' => $public]);
    $existing = (file_exists($private) && !isset($emptyDirectories['private'])) ||
        (file_exists($public) && !isset($emptyDirectories['public']))
        ? deployExistingInstallation($private, $public, $settings) : null;
    $groupName = deployAsk($terminal, 'Group that runs PHP (for private-file access)', posix_getgrgid(posix_getegid())['name']);
    $group = posix_getgrnam($groupName);
    if (false === $group) { throw new RuntimeException('The PHP group does not exist.'); }
    $install = $install ?? [LGV_TZ_Lookup_Setup::class, 'install'];
    if ($existing !== null) {
        deployRefresh($work, $settings, $private, $public, $group['gid'], $serviceUrl, $existing, $install);
        return;
    }
    $secret = !$noSecret && strtolower(deployAsk($terminal, 'Require a server secret? (y/n)', 'y')) !== 'n' ? bin2hex(random_bytes(32)) : '';
    $token = bin2hex(random_bytes(32));
    $privateStage = $parent.'/.lgv-tz-install-'.bin2hex(random_bytes(12));
    $publicStage = $webRoot.'/.lgv-tz-install-'.bin2hex(random_bytes(12));
    $state = ['settings' => $settings, 'token' => $token, 'private' => $private, 'public' => $public,
        'private_stage' => $privateStage, 'public_stage' => $publicStage, 'database_created' => false,
        'guard_created' => false, 'published' => false, 'empty_directories' => $emptyDirectories];
    deploySave($work, $state);
    foreach ([$privateStage, $publicStage] as $path) {
        if (!mkdir($path, 0700) || false === file_put_contents($path.'/.lgv-tz-install-owner', $token)) {
            throw new RuntimeException('Could not create installation staging directories.');
        }
    }
    if (!chgrp($privateStage, $group['gid'])) { throw new RuntimeException('Could not assign the PHP group.'); }
    try { $connection = deployConnection($settings); }
    catch (PDOException $error) {
        $missing = $driver === 'pgsql' ? ($error->errorInfo[0] ?? '') === '08006' &&
            str_contains($error->getMessage(), 'database "'.$settings['database'].'" does not exist')
            : (int)($error->errorInfo[1] ?? 0) === 1049;
        if (!$create || !$missing) { throw $error; }
        $admin = deployConnection($settings, true);
        $admin->exec('CREATE DATABASE '.deployIdentifier($settings['database'], $driver));
        $state['database_created'] = true;
        deploySave($work, $state);
        $admin = null;
        $connection = deployConnection($settings);
    }
    deployLock($connection);
    if (deployTableExists($connection, 'timezones') || deployTableExists($connection, 'lgv_tz_install_guard')) {
        throw new RuntimeException('This database already contains timezone or installer tables; choose an empty database.');
    }
    $connection->exec('CREATE TABLE lgv_tz_install_guard (token VARCHAR(64) PRIMARY KEY)');
    $statement = $connection->prepare('INSERT INTO lgv_tz_install_guard VALUES (?)');
    $statement->execute([$token]);
    $statement->closeCursor();
    $state['guard_created'] = true;
    deploySave($work, $state);
    mkdir($privateStage.'/app', 0755);
    $install($privateStage.'/app', $driver, dirname(__DIR__));
    $package = Composer\InstalledVersions::getInstallPath(LGV_TZ_Lookup_Setup::PACKAGE);
    echo "Downloading the latest timezone boundaries...\n";
    $release = LGV_TZ_Lookup_Setup::latestBoundaries($work);
    $asset = $release['asset'];
    LGV_TZ_Lookup_Setup::download($asset['browser_download_url'], $work.'/boundaries.zip');
    $checksum = LGV_TZ_Lookup_Setup::verifyBoundaries($work.'/boundaries.zip', $asset);
    LGV_TZ_Lookup_Setup::extract($work.'/boundaries.zip', $work.'/boundaries.json');
    $database = new LGV_TZ_Lookup_Database($settings['database'], $settings['user'], $settings['password'], $driver, $settings['host'], $settings['port']);
    echo 'Loading boundary release '.$release['version']."...\n";
    $stream = fopen($work.'/boundaries.json', 'rb');
    try { (new JsonStreamingParser\Parser($stream, new LGV_TZ_Lookup_Loader($database)))->parse(); }
    finally { fclose($stream); unlink($work.'/boundaries.zip'); unlink($work.'/boundaries.json'); }
    if ($driver === 'pgsql') { $connection->exec('ANALYZE timezones'); }
    $count = (int)$connection->query('SELECT COUNT(*) FROM timezones')->fetchColumn();
    if ($count === 0) { throw new RuntimeException('No polygons were loaded.'); }
    require $package.'/src/TestLocations.php';
    $lookup = new LGV_TZ_Lookup_Query($database);
    $failures = 0;
    $testStart = hrtime(true);
    foreach ($test_locations_param_array as $case) {
        $lng = $case['params']['lng']; $lat = $case['params']['lat'];
        while ($lng < -180) { $lng += 360; } while ($lng > 180) { $lng -= 360; }
        while ($lat < -90) { $lat += 180; } while ($lat > 90) { $lat -= 180; }
        if ($lookup->get_tz($lng, $lat) !== $case['result']) { ++$failures; echo 'FAIL '.$case['title']."\n"; }
    }
    if ($failures) { throw new RuntimeException($failures.' known-location tests failed; the service was not published.'); }
    $lookupSeconds = (hrtime(true) - $testStart) / 1e9;
    $source = file_get_contents(__FILE__);
    $licenseHeader = explode("/***************************************************************************************************************************/\n/**\n    \\file", $source, 2)[0];
    $config = $licenseHeader;
    foreach (['g_dbName' => $settings['database'], 'g_dbUserName' => $settings['user'], 'g_dbPassword' => $settings['password'],
        'g_dbType' => $driver, 'g_dbHost' => $settings['host'], 'g_dbPort' => $settings['port'], 'g_server_secret' => $secret] as $key => $value) {
        $config .= '$'.$key.' = '.var_export($value, true).";\n";
    }
    file_put_contents($privateStage.'/config.php', $config);
    if (!chmod($privateStage.'/config.php', 0640) || !chgrp($privateStage.'/config.php', $group['gid'])) {
        throw new RuntimeException('Could not set private configuration permissions for the PHP group.');
    }
    mkdir($privateStage.'/tools', 0755);
    foreach (['update.php', 'Setup.php'] as $file) {
        if (!copy(__DIR__.'/'.$file, $privateStage.'/tools/'.$file) || !chmod($privateStage.'/tools/'.$file, 0644)) {
            throw new RuntimeException('Could not install the boundary updater.');
        }
    }
    if (!copy(dirname(__DIR__).'/update.sh', $privateStage.'/update.sh') || !chmod($privateStage.'/update.sh', 0755)) {
        throw new RuntimeException('Could not install the boundary update command.');
    }
    $relativePackage = substr($package, strlen($privateStage));
    $endpointCode = deployEndpointCode($private, $relativePackage, $licenseHeader);
    file_put_contents($publicStage.'/index.php', $endpointCode);
    chmod($publicStage.'/index.php', 0644);
    chmod($publicStage.'/.lgv-tz-install-owner', 0644);
    foreach (['composer.phar', 'composer.sha256'] as $file) { unlink($privateStage.'/app/'.$file); }
    foreach (['composer-cache', 'composer-home'] as $dir) { deployRemoveTree($privateStage.'/app/'.$dir); }
    // Only code and manifests live below app. Make them readable through the private directory's selected PHP group.
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($privateStage.'/app', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($files as $file) {
        if ($file->isLink()) { continue; }
        $mode = $file->isDir() || ($file->getPerms() & 0111) ? 0755 : 0644;
        if (!chmod($file->getPathname(), $mode)) { throw new RuntimeException('Could not set installed code permissions.'); }
    }
    chmod($privateStage.'/app', 0755);
    file_put_contents($privateStage.'/installation.json', json_encode(['release' => $release['version'], 'archive_sha256' => $checksum,
        'polygons' => $count, 'driver' => $driver, 'known_location_checks' => count($test_locations_param_array),
        'service_url' => $serviceUrl], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($privateStage, 0750);
    chmod($publicStage, 0755);
    deployPublishDirectories($state);
    $state['published'] = true;
    deploySave($work, $state);
    printf("\nInstalled %d polygons; %d/%d known locations passed (%.3f ms mean lookup).\n", $count, count($test_locations_param_array), count($test_locations_param_array), $lookupSeconds * 1000 / count($test_locations_param_array));
    printf("Installation: %.2f s; PHP peak %.2f MiB.\n", (hrtime(true) - $started) / 1e9, memory_get_peak_usage() / 1048576);
    echo 'Public endpoint: '.$public."/index.php\nPrivate configuration: ".$private."/config.php\n";
    echo 'Service URL: '.$serviceUrl."\n";
    echo 'Boundary updater: '.$private."/update.sh (use --check to report the latest release).\n";
    echo $secret === '' ? "Server secret: disabled.\n" : 'Server secret: '.$secret."\n";
    $secretQuery = $secret === '' ? '' : '&secret='.rawurlencode($secret);
    echo 'Example request: '.$serviceUrl.'?ll=-77.036543,38.895037'.$secretQuery."\n";
    echo 'Test request: '.$serviceUrl.'?test'.$secretQuery."\n";
    echo "Boundary downloads deleted. The installed database is retained.\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) { return; }
if (PHP_SAPI !== 'cli' || $argc < 3 || !in_array($argv[1], ['run', 'cleanup'], true) || !is_dir($argv[2])) {
    fwrite(STDERR, "Run ./deploy.sh [--no-secret].\n"); exit(2);
}
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    foreach ([SIGHUP, SIGINT, SIGTERM] as $signal) {
        pcntl_signal($signal, static function(int $signal): void { throw new RuntimeException('Installation interrupted by signal '.$signal.'.'); });
    }
}
try {
    if ($argv[1] === 'cleanup') { deployCleanup($argv[2]); }
    else { deployRun($argv[2], ($argv[3] ?? '') === '--no-secret'); }
} catch (Throwable $error) {
    fwrite(STDERR, 'Installation error: '.$error->getMessage().PHP_EOL); exit(1);
}
