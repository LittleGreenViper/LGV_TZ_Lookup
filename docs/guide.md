# Detailed Usage Guide

Start with the [README](../README.md) for installation and a basic lookup. This guide covers initial boundary loading, server setup, maintenance, and development. Unless a command shows an installed server path, run it from the repository root.

- [Loading boundaries for a library application](#loading-boundaries-for-a-library-application)
- [Composer details](#composer-details)
- [Server deployment](#deploying-a-server)
- [Upgrading an existing installation](#upgrading-an-existing-installation)
- [Updating boundary data](#updater-details)
- [Command-line demo and metrics](#turnkey-command-line-demo)
- [Step-by-step server installation (MySQL or PostgreSQL)](#step-by-step-server-installation)
- [Exactly what to copy to the server](#step-4-copy-the-application-files)
- [Performance and database tests](#query-performance)
- [Building the documentation](#generating-the-html-documentation)
- [How the lookup works](#how-the-lookup-works)

## Loading Boundaries for a Library Application

Create a MySQL or PostgreSQL database and give the loading user permission to create tables and indexes and insert rows. Lookup users need only `SELECT` access to the populated `timezones` table.

Install the optional parser in your application's directory:

```bash
composer require salsify/json-streaming-parser:"8.3.*"
```

Download `timezones-with-oceans.geojson.zip` from the [Timezone Boundary Builder releases](https://github.com/evansiroky/timezone-boundary-builder/releases/latest), and extract `combined-with-oceans.json`. Run a PHP loading script in your application:

```php
<?php
require __DIR__.'/vendor/autoload.php';

$database = new LGV_TZ_Lookup_Database(
    'tz_database', 'tz_loader', 'your-db-password', 'mysql', '127.0.0.1'
);
$stream = fopen(__DIR__.'/combined-with-oceans.json', 'rb');
if ($stream === false) {
    throw new RuntimeException('Could not open the boundary file.');
}
try {
    $loader = new LGV_TZ_Lookup_Loader($database);
    (new JsonStreamingParser\Parser($stream, $loader))->parse();
} finally {
    fclose($stream);
}
```

Use `pgsql` as the driver for PostgreSQL. The loader creates the schema and uses the same packed polygon format on both databases; PostgreSQL needs no PostGIS. A MySQL SQL dump cannot be imported directly into PostgreSQL.

Loading replaces the `timezones` table. Use this script for initial setup, and the updater below for an existing database. Allow enough PHP memory and temporary disk space for the large boundary file; the command-line tools default to a `1G` memory limit. Once loading succeeds, you can delete the GeoJSON file. Keep your application's Composer `vendor` directory for autoloading.

## Composer Details

The package registers the `LGV_TZ_Lookup_*` classes for autoloading. Lookup does not start an HTTP server, load the JSON parser, or change your application's execution time limit. Reuse the database and query objects for multiple locations.

The GitHub VCS repository configuration in the README allows installation without Packagist registration. Tags `1.2.0` and earlier predate Composer packaging. To try a local checkout before publishing a release, run these commands in your application's directory:

```bash
composer config repositories.lgv-tz-lookup path /absolute/path/to/LGV_TZ_Lookup
composer require littlegreenviper/lgv_tz_lookup:@dev
```

The loader's parser is optional for applications that only query. In a development checkout, `composer install` at the repository root installs it as a development dependency. Run `composer test` and `composer test:composer` for regression and autoloading checks.

PostgreSQL polygon reads use single-row fetching on PHP 8.5 and later, and a server cursor on earlier releases. Both keep polygon fetching bounded. Finishing a lookup early releases its reader without committing or cancelling an application's transaction.

## Deploying a Server

From a checkout, run:

```bash
./deploy.sh
```

[deploy.sh](../deploy.sh) is the shell entrypoint; [tools/deploy.php](../tools/deploy.php) performs the installation and recovery.

The installer asks for the database driver, host, port, name, user, and password. Password input is hidden. For a new installation, use a database with no `timezones` table, or let the installer create a new database if the user has permission. For a rerun over an installed service, use the existing settings and paths as described under [upgrading an existing installation](#upgrading-an-existing-installation).

### Choosing the Web Directory and Service Subdirectory

The installer asks for an **existing web directory on disk**, the **public URL that maps to that directory**, and a **new service subdirectory**. It creates the service subdirectory inside the selected directory and appends that same name to the URL. A command-line installer cannot infer your domain or URL prefix from a filesystem path, so enter the matching URL explicitly.

The web server's **document root** is the directory mapped to the site's root URL. For example, `/var/www/html` might map to `https://example.com/`. You can select that root or an existing served directory beneath it. If you select a nested directory, include its full URL prefix in the URL answer. The suggested filesystem path is your current working directory; check it before accepting it.

For example, these answers reproduce a shared-hosting layout with a checkout in a nested web directory:

| Installer setting | Example answer |
| --- | --- |
| Existing web directory (filesystem path) | `/home/account/public_html/recovrr/timezones` |
| Public URL of that directory | `https://example.com/recovrr/timezones` |
| New service subdirectory | `timezone` |
| Private application directory | `/home/account/timezones` |
| Group that runs PHP | `account` |

The result is:

```text
Public endpoint: /home/account/public_html/recovrr/timezones/timezone/index.php
Private configuration: /home/account/timezones/config.php
Service URL: https://example.com/recovrr/timezones/timezone/
```

Accepting the default `timezone` **adds a `/timezone/` directory**. To create a service at `https://example.com/recovrr/timezones/`, select `/home/account/public_html/recovrr` as the existing directory, enter `https://example.com/recovrr` as its public URL, and enter `timezones` as the service subdirectory. For a new installation, the resulting service directory and private application directory must both be new. Version 1.4.2 can refresh existing directories only when both belong to the same previous installer deployment; it refuses unrelated directories or a source checkout as the service target.

The private application directory must be outside the site's actual document root, such as outside all of `public_html`, including when you selected a nested web directory. The installer writes the chosen private configuration path into the generated public entry point automatically. Use the printed **Service URL** and **Test request** to access the installed service. The checkout's `src/index.php` is a separate entry point with its own configuration default.

### Completing the Installation

By default, it creates a random 256-bit server secret. The final output gives you that secret and an example request. It also saves the secret in the private `config.php`, with the database settings. You can answer `n` to the secret prompt, or start with:

```bash
./deploy.sh --no-secret
```

The installer uses Composer to install a copy of the checkout, downloads the latest full timezone boundaries with oceans, loads them, and runs all 200 known-location tests. Only after those tests pass does it publish `index.php` in your selected service directory. The final output includes complete lookup and test URLs, preserving the public URL prefix you entered. For the example above:

```text
Example request: https://example.com/recovrr/timezones/timezone/?ll=-77.036543,38.895037&secret=<GENERATED SECRET>
Test request: https://example.com/recovrr/timezones/timezone/?test&secret=<GENERATED SECRET>
```

If the secret is disabled, omit that query argument. A missing or incorrect secret receives HTTP 403. The final report includes polygon count, test results, mean lookup latency, installation time, and PHP peak memory.

The browser test report includes a Performance section with the lookup count, total test time, average, median, 95th-percentile and slowest lookup times, and PHP request peak memory. Each location also shows its lookup time. Browser-test lookup timings include configuration loading, authentication, a new database connection, and the query; the installer's known-location loop reuses its connection, so its mean timing measures different work. HTTP transport and browser rendering are outside these server-side timings. PHP request memory includes the generated results page and excludes the database server's memory.

The compressed and extracted boundaries are deleted after loading. **The installed database is retained.** An interrupted or failed installation removes its temporary files and rolls back its own tables or newly created database. A random ownership marker prevents cleanup from adopting unrelated tables or directories. If cleanup cannot reach the database, it retains a private recovery directory and prints its location. Retry with:

```bash
php tools/deploy.php cleanup '<RECOVERY DIRECTORY>'
```

You'll need PHP 8.0 or later with PDO and the selected database driver, `curl`, `zip`, `mbstring`, `ctype`, and `posix`; `proc_open` and the Unix `stty` command must be available. The web server's PHP must also have the selected PDO driver. The installer works with your existing PHP/database services; it does not install or reconfigure them. Choose the correct PHP group so the web worker can read the private configuration and Composer code.

`LGV_TZ_DEPLOY_PHP` selects the CLI PHP executable; `LGV_TZ_DEPLOY_MEMORY_LIMIT` defaults to `1G`; `TMPDIR` selects the temporary storage directory. Prompts still read from your terminal when standard input is a pipe.

A server without a checkout can download the published installer with:

```bash
curl -fsSL https://raw.githubusercontent.com/LittleGreenViper/LGV_TZ_Lookup/main/deploy.sh | sh
```

That form also needs command-line `curl` and `tar`. It fetches the installer from `main`, then performs the same prompted installation. To disable the secret, end the command with `sh -s -- --no-secret`.

## Upgrading an Existing Installation

Use **the 1.4.2 or later checkout's `deploy.sh`** on the server. Leave the populated database, the installed public directory, and the private directory—including `config.php`—in place. Refresh mode recognizes the matching `.lgv-tz-install-owner` records in both directories and checks that the entered database settings match the private configuration. It preserves that configuration file byte for byte and keeps the existing secret, including when `--no-secret` is supplied; that option controls new installations only.

1. Transfer the updated source checkout, or update your existing source checkout. This is the directory containing `deploy.sh`, `composer.json`, `src/`, and `tools/`; keep it separate from the installed private application and public service subdirectory.
2. From the updated checkout, run `./deploy.sh` as the same deployment user. Enter the existing database settings. Answer `n` to creating a database.
3. Enter the same existing web directory, its corresponding public URL, the same service subdirectory, and the same private application directory. Choose the PHP group used by the original deployment. On a rerun, there is no new-secret prompt.
4. The installer prints `Refreshing installed code; keeping the existing boundaries, configuration, and secret.` It installs and tests the updated package against the existing polygons, then publishes the refreshed code and updater. It does not download boundaries, reload the table, or change database contents.
5. Wait for `Refreshed installed code` and use the printed complete **Test request** URL. Check the test results and the Performance section. Use the private `update.sh` separately when you want to refresh boundary data.

For the shared-hosting layout above, use these directory answers on every rerun:

```text
Existing web directory: /home/account/public_html/recovrr/timezones
Public URL of that directory: https://example.com/recovrr/timezones
Service subdirectory: timezone
Private application directory: /home/account/timezones
```

This refreshes `/home/account/public_html/recovrr/timezones/timezone/index.php`. It preserves `/home/account/timezones/config.php` and prints `https://example.com/recovrr/timezones/timezone/` as the service URL.

Code is prepared and tested in staging directories before publication. Additional public/private files are carried forward. The directory switch can briefly interrupt requests. If copying, dependency installation, or lookup validation fails, the old service remains. If publication is interrupted, the shell's cleanup restores the prior directories without altering the database. If cleanup cannot finish, it prints a recovery directory; retry using the **updated checkout**:

```bash
php tools/deploy.php cleanup '<RECOVERY DIRECTORY>'
```

Manual installations without the installer ownership records cannot use this refresh mode. Follow the manual copy instructions to update those installations while preserving their configuration and populated database.

## Updater Details

Version `1.4.1` adds [update.sh](../update.sh). From a checkout, report the latest Timezone Boundary Builder shapefile release with:

```bash
./update.sh --check
```

This prints `Latest boundary release: <VERSION>` and exits. It downloads only the small release metadata; it does not need a configuration file, the JSON parser, or a database connection. Use the reported version to decide whether to reload your data.

To download and load that latest release into an existing database, supply the same private PHP configuration used by the server:

```bash
./update.sh /absolute/path/to/config.php
```

New deployments install the updater beside `config.php`. On those servers, run:

```bash
/var/www/lgv-tz-server/update.sh --check
/var/www/lgv-tz-server/update.sh
```

The second command defaults to the configuration beside the script. It always reloads the latest full `timezones-with-oceans.geojson.zip` release, even if you have loaded it before. The boundary release version is separate from this project's `1.4.1` version. Existing Composer installations need library version `1.4.1` or later and the optional streaming parser; repository checkouts and manual server copies use the single Composer installation at their root.

The updater prints the latest version before loading, verifies the downloaded archive's size and SHA-256 digest when supplied, streams the extracted GeoJSON through the existing loader, and reports the loaded version and polygon count. It loads a staging table first, then replaces `timezones` atomically. Failed downloads, malformed files, and empty loads retain the existing table. The database user needs permissions to create, insert, rename, and drop tables. Other application tables and the server configuration are preserved.

The ZIP, extracted GeoJSON, and release metadata are removed after completion or failure, including Ctrl+C and a PHP memory-limit failure. If the database cannot be reached to remove a staging table, the large downloads are still deleted and a small private recovery directory is retained. Retry cleanup once the database is available:

```bash
php tools/update.php cleanup '<RECOVERY DIRECTORY>'
```

For deployed servers, use the installed private `tools/update.php`. Successful recovery removes its staging record; the now-empty recovery directory can be deleted. Updates and the installer share a database lock so they cannot run concurrently against the same database.

You'll need PHP 8.0 or later with `curl`; loading also needs `zip`, `pdo_mysql` or `pdo_pgsql`, `mbstring`, `ctype`, and `salsify/json-streaming-parser:8.3.*`. `LGV_TZ_UPDATE_PHP` selects the PHP executable, `LGV_TZ_UPDATE_MEMORY_LIMIT` defaults to `1G`, and `TMPDIR` selects temporary storage. The updater is a local command and does not require the HTTP server secret.

Developers can run `composer test:update:mysql` or `composer test:update:postgres` for small fixture and recovery checks in an isolated database. Use `php tests/update.php --driver=mysql --live` (or `pgsql`) to exercise the installed command with the actual latest download and all 200 known locations. These checks use the same `LGV_TZ_TEST_*` connection settings as the database integration tests and delete their test databases afterward.

## Turnkey Command-line Demo

If you'd like to try the Composer package, we have a simple command-line demo. You'll need PHP and a running MySQL or PostgreSQL server. The demo takes care of fetching Composer, installing the package, downloading the boundary data, loading a test database, and running the tests.

You run it from the repository root, like so:

```bash
./demo/run.sh
```

That's the whole command. There are no command-line arguments, and you don't need to set up the HTTP server first.

For PostgreSQL, using the current checkout through Composer, run:

```bash
LGV_TZ_DEMO_DRIVER=pgsql LGV_TZ_DEMO_PACKAGE_PATH=. ./demo/run.sh
```

On your initial Homebrew PostgreSQL setup, the demo uses your shell username, port `5432`, and an empty password. It connects to the existing `postgres` database for administration, and creates a separate database for the test. The user needs `CREATEDB` permission. Override the connection settings below if your installation differs.

`LGV_TZ_DEMO_PACKAGE_PATH` installs a copy of that checkout into the temporary Composer application. This tests local changes through Composer before they are released. If you omit it, the demo installs the GitHub release selected by `LGV_TZ_DEMO_PACKAGE_VERSION`.

> NOTE: This is a temporary installation. The demo creates its own database and working directory, and removes them when it finishes. Your existing server configuration, databases, and downloaded boundary file are left alone.

### What Happens During a Run?

The demo installs the released library into a temporary Composer application. The tests exercise that installed package. The version that was installed, and the boundary release being tested, are printed at the start of the run.

Here's what it does:

1. Download a temporary Composer executable and verify its checksum. Your installed Composer is not changed or required.
2. Find the latest [Timezone Boundary Builder release](https://github.com/evansiroky/timezone-boundary-builder/releases/latest), download `timezones-with-oceans.geojson.zip`, verify its checksum when published, and stream its GeoJSON file to disk.
3. Load that file with the Composer-installed `LGV_TZ_Lookup_Loader` into a newly created database named `lgv_tz_demo_<random ID>`, retaining compact source geometry for independent checks.
4. Delete both the downloaded ZIP and the extracted GeoJSON immediately after loading.
5. Validate stored geometry against the source and run both the installed package's known-location tests and generated boundary/interior tests through `LGV_TZ_Lookup_Query`. Print sample results, any failures, and performance metrics.
6. Drop the temporary database and delete the temporary application, Composer executable, and Composer cache when the script exits.

The boundary file is the [GeoJSON](https://geojson.org) variant, with oceans included. That's the format the loader uses. We look up the latest published release on each run, so you don't need to edit a download URL when the boundaries are updated.

These are pretty big files. On October 9, 2026, the archive was about 53 MiB, and the extracted JSON was about 175 MiB. You'll need temporary disk space for both, as well as space for the database table. Loading can take a while; progress messages tell you which part of the run is underway.

### What Gets Tested?

First, we check what was loaded. Every stored polygon's name, domain rect, point count, and packed exterior-ring data are compared with the decoded GeoJSON. The database server calculates the polygon hashes, so we can check the contents without bringing all of the large blobs back into PHP.

Next, we run the package's known-location tests. These are the manually selected tests described below, many of which are near places where timezones abut.

We then generate additional tests for the ten largest polygons. "Largest," here, means the most points to decode. A simple rectangle might cover a large area, but it won't exercise the lookup code like a coastline with hundreds of thousands of points.

For each selected polygon, we sample edges around its contour and test points about 5 kilometers (3.1 miles) to each side of the boundary. We also test interior points, edges near the decoder's block transitions, and the edge that closes the polygon. The expected timezones are calculated from the downloaded GeoJSON, using an independent ray-casting test that accounts for holes.

Sometimes, both offset points land in the same timezone, because the boundary winds around them. We report actual crossings separately. Where source polygons overlap, any containing named timezone is accepted; an `Etc/` ocean timezone is used if there is no containing named zone.

Finally, we check that the generated tests actually caused the package to decode every selected large polygon. A name returned by the domain-rect shortcut would not, by itself, demonstrate that the large polygon reader was exercised.

> NOTE: These tests check the stored data and sample complex boundaries. They aren't an exhaustive test of every possible long/lat. The number of generated tests can vary with the boundary release.

### Reading the Results

The demo prints a few passing locations, every failing location, and a summary for each group. For example, here's an excerpt from the October 9, 2026 run, using package `1.3.0` and boundary release `2026d`:

```text
Source/storage validation: 1355 polygons checked, 0 failures.
Known location: 200/200 passed.
Generated boundary: 260/260 passed.
Generated interior: 30/30 passed.
Largest polygons decoded in generated tests: 10/10
Boundary pairs with distinct source zones: 112/130 (112 named-to-named)
```

That run checked all 1,355 stored polygons and passed all 490 lookup tests. The largest polygon had 193,004 points. Both downloaded boundary files, the database, and the temporary Composer application were removed afterward.

The script returns an exit status of `0` only if all checks and cleanup succeed. A setup error, failed lookup, storage mismatch, missing large-polygon coverage, or cleanup problem results in a nonzero status. You can use that status in a command-line build or test job.

A `FAIL` line includes the location's long/lat, expected timezone names, and the name returned by the package. A `FAIL coverage` line means the chosen tests did not exercise a selected large polygon. A `Demo error` message means setup or a worker operation could not complete.

> NOTE: Timezones are political constructs, and the latest boundaries can change. A failing known-location test may need to be reviewed against the new data. Generated expectations are calculated from the current download on each run.

### The Metrics

After the test summaries, you'll get a metrics report:

| Measurement | What It Tells You |
| --- | --- |
| Phase durations | Seconds spent setting up Composer, obtaining the data, extracting it, loading it, preparing the reference checks, and running lookups. |
| Total before cleanup | Elapsed time for the worker through the test report. Cleanup is timed separately. |
| Data sizes | Compressed ZIP size, extracted GeoJSON size, and the stored polygon payload, in MiB. The payload size excludes database indexes and server overhead. |
| Lookup latency | Mean, median, 95th percentile, and slowest API call, in milliseconds. The known locations and generated large-shape tests have separate summaries. |
| Lookup throughput | Calls per second, using only the accumulated time spent in the lookup API. Downloading, loading, and reference calculations are outside this figure. |
| PHP memory | Loading/reference peaks, and additional memory needed by an individual lookup. The database server, native client buffers, and the separate Composer process are outside these measurements. |
| Polygon work | Rows and bytes actually evaluated across lookup calls. A polygon read more than once is counted each time. |
| Large-polygon coverage | How many selected polygons were decoded, with their IDs, timezone names, point counts, and packed sizes. |
| Boundary crossings | How many sampled pairs fall in different timezones, including named-to-named crossings. |

The lookup calls reuse one database connection. These timings describe the PHP/database lookup API; an HTTP request or a fresh connection will add its own time.

Loading memory includes the compact source geometry retained for independent checks. We free that reference before measuring lookup memory. PHP 8.2 and later can reset the memory peak between phases and calls; older versions report lifetime peaks instead. Actual times and memory use will depend on the host, the selected polygons, and the boundary release.

To save a run's output, while preserving its exit status, you can redirect it to a small report file:

```bash
./demo/run.sh > /tmp/lgv-tz-demo-results.txt 2>&1
```

### Initial Setup

The script uses POSIX `sh` and PHP, with no Homebrew-specific paths or GNU-only commands. It requires PHP 8.0 or later with `pdo_mysql` or `pdo_pgsql`, plus `curl`, `zip`, `mbstring`, and `ctype` enabled, and `proc_open` available. The selected database server must be running, and the configured user must be able to create and drop a temporary database, create its tables, and insert and read rows. A web server, a database command-line client, and an existing project configuration file are not needed.

On macOS, Homebrew PHP provides these extensions. If needed, install and start the services with:

```bash
brew install php mysql
brew services start mysql
```

The initial Homebrew MySQL setup uses `root` with an empty password. Those are the demo's defaults, so that setup can run `./demo/run.sh` immediately.

If your MySQL setup uses another user or a password, provide the settings through environment variables. For example:

```bash
export LGV_TZ_DEMO_HOST=127.0.0.1
export LGV_TZ_DEMO_USER=demo_user
export LGV_TZ_DEMO_PASSWORD='<YOUR DATABASE PASSWORD>'
./demo/run.sh
```

There is no demo config file. The same environment settings are used for loading, querying, and cleanup. A `localhost` connection may use PHP's configured MySQL socket; use `127.0.0.1` for a TCP connection, including when selecting a different port.

### Changing the Demo Settings

These are the available settings:

| Variable | Default | Purpose |
| --- | --- | --- |
| `LGV_TZ_DEMO_DRIVER` | `mysql` | Database backend: `mysql` or `pgsql`. |
| `LGV_TZ_DEMO_HOST` | `localhost` | Database host. |
| `LGV_TZ_DEMO_PORT` | `3306` / `5432` | Port for MySQL / PostgreSQL. For MySQL, use `127.0.0.1` to select TCP. |
| `LGV_TZ_DEMO_USER` | `root` / Shell username | MySQL / PostgreSQL user with permissions for the temporary database. |
| `LGV_TZ_DEMO_PASSWORD` | Empty | Database password. |
| `LGV_TZ_DEMO_ADMIN_DATABASE` | `postgres` | Existing PostgreSQL database used for CREATE/DROP administration. |
| `LGV_TZ_DEMO_PACKAGE_VERSION` | `^1.4` | Composer library version constraint for GitHub installs. |
| `LGV_TZ_DEMO_PACKAGE_PATH` | Unset | Install a local checkout through Composer instead of a release. |
| `LGV_TZ_DEMO_LARGEST_SHAPES` | `10` | Number of largest polygons to probe (1-100). |
| `LGV_TZ_DEMO_MEMORY_LIMIT` | `1G` | PHP memory limit for loading the large GeoJSON file. |
| `LGV_TZ_DEMO_PHP` | `php` | PHP executable name or absolute path. |
| `TMPDIR` | System temporary directory | Location for temporary files; allow space for the compressed and extracted boundaries. |

For example, this tests the twenty largest polygons:

```bash
LGV_TZ_DEMO_LARGEST_SHAPES=20 ./demo/run.sh
```

This selects a particular released package version:

```bash
LGV_TZ_DEMO_PACKAGE_VERSION=1.4.1 ./demo/run.sh
```

The boundary download still uses the latest published release. Selecting a package version does not select an older shapefile.

### What Gets Cleaned Up?

The ZIP and extracted JSON are deleted as soon as loading finishes, including a failed load. When the worker exits, the shell starts a separate cleanup process to drop that run's database. Only after database cleanup succeeds does it remove the rest of the temporary application, including Composer and its cache.

This cleanup also runs after Ctrl+C, an ordinary worker error, or a PHP loader memory-limit failure. Those paths were tested on macOS. The script uses POSIX `sh` and PHP for Linux/Unix portability; the complete run has been verified on the Homebrew setup.

If the database server becomes unavailable during cleanup, the large downloads are still deleted, and the working directory is retained with `database.json`. The error message prints that directory. Once the database server is available again, you can retry cleanup with the same connection settings:

```bash
saved_demo_directory='<WORKING DIRECTORY PRINTED BY THE DEMO>'
php demo/demo.php cleanup "$saved_demo_directory" && rm -rf "$saved_demo_directory"
```

Use the directory printed by the demo. The cleanup worker reads its database name from the record and requires the generated `lgv_tz_demo_` naming pattern. If cleanup still fails, the record and working directory remain available for another attempt.

### The Demo Files

| File | Purpose |
| --- | --- |
| [demo/run.sh](../demo/run.sh) | The command you run. Creates the private working directory and handles exit/interruption cleanup. |
| [demo/demo.php](../demo/demo.php) | The PHP worker. Installs the package, obtains and loads data, runs tests, and reports metrics. Its separate cleanup mode removes downloads and the database. |
| [demo/SystemTests.php](../demo/SystemTests.php) | The independent source-geometry reference, generated test cases, and polygon audit counters. |
| [tests/demo.php](../tests/demo.php) | Small reference-geometry and cleanup-guard checks for development. |

If you are working on the demo itself, install the development dependencies at the repository root and run the small checks:

```bash
composer install
composer test:demo
```

Those checks cover holes, ocean fallback, boundary offsets, decoder block transitions, and cleanup guards. They do not download the shapefile or need a running database server. Run `./demo/run.sh` for the complete Composer/database system test.

## Step-by-Step Server Installation

Follow the same installation steps for **MySQL or PostgreSQL**; choose the matching database commands in step 3 and settings in step 5. These examples put the database on the same server as PHP, the private application at `/var/www/lgv-tz-server`, and the public endpoint at `/var/www/html/timezone`. Substitute your own paths and hostname throughout.

The prompted [installer](#deploying-a-server) automates copying, configuration, loading, and verification. With that route, check the prerequisites in step 1, prepare the database credentials in step 3, run `./deploy.sh` on the destination server, and follow [the web directory and service subdirectory instructions](#choosing-the-web-directory-and-service-subdirectory). The installer prints the final service URL and writes the private configuration path automatically. The complete numbered steps below describe **manual installation**, including the files to transfer and the public entry point to create yourself.

### Step 1: Check the Server Prerequisites

Install and start a MySQL or PostgreSQL server, and have an existing web server configured to execute PHP. Install PHP 8.0+ for both command-line use and the web server, with these extensions:

| Requirement | MySQL | PostgreSQL |
| --- | --- | --- |
| PHP database extension | `pdo_mysql` | `pdo_pgsql` |
| PHP configuration value | `mysql` | `pgsql` |
| Default database port | `3306` | `5432` |
| Other PHP extensions | PDO, `curl`, `zip`, `mbstring`, `ctype` | Same |

On the server, check the command-line PHP installation:

```bash
php --version
php -m
php -r 'echo "PDO drivers: ".implode(", ", PDO::getAvailableDrivers()).PHP_EOL;'
```

The driver list must include `mysql` or `pgsql`, as appropriate. Check that the web server's PHP uses the same version and database extension; it may use a different PHP configuration from the command line. Have command-line `unzip` available for the initial boundary extraction. The application installer and updater use the PHP `zip` extension.

### Step 2: Prepare Dependencies on Your Development Machine

Prepare the dependencies **on your development machine, from the repository root**:

```bash
composer install
```

This creates the root `composer.lock` and `vendor/`. The parser is a development dependency of the library, but is needed on a server that loads or updates boundaries, so include the dependencies from this normal install. `composer install --no-dev` omits the parser. The preparation machine needs Composer and PHP 8.0+ with PDO, `pdo_sqlite`, `mbstring`, and `ctype`; the manual runtime server does not need SQLite or an installed Composer executable.

### Step 3: Create the Database and Application User on the Server

Use a new database named `tz_database` and a user named `tz_user`, or substitute your own names consistently. Replace `your-db-password` with your chosen database password. If your existing installation already has a populated `timezones` table, preserve that database, user, and configuration, and skip steps 3 and 7.

**MySQL:** open an administrative session, for example:

```bash
mysql --user=root --password
```

On systems where the administrative account uses operating-system authentication, use `sudo mysql` instead. Run:

```sql
CREATE DATABASE tz_database CHARACTER SET utf8mb4;
CREATE USER 'tz_user'@'127.0.0.1' IDENTIFIED BY 'your-db-password';
GRANT SELECT, INSERT, CREATE, DROP, ALTER, INDEX
    ON tz_database.* TO 'tz_user'@'127.0.0.1';
```

Exit with `exit`. This account is for PHP connecting over TCP to `127.0.0.1`, matching the configuration below. For a database on another machine, grant access from the PHP server's address and use the database server's address in `config.php`. See [MySQL account and privilege setup](https://dev.mysql.com/doc/refman/8.0/en/creating-accounts.html) and [account host names](https://dev.mysql.com/doc/refman/8.0/en/account-names.html).

**PostgreSQL:** open an administrative session. On a typical Linux installation:

```bash
sudo -u postgres psql
```

If your installation uses a different administrator, connect with that account instead. Run:

```sql
CREATE ROLE tz_user LOGIN PASSWORD 'your-db-password';
CREATE DATABASE tz_database OWNER tz_user;
```

Exit with `\q`. The application role owns the new database and the tables it creates. See [PostgreSQL role creation](https://www.postgresql.org/docs/current/sql-createrole.html) and [database ownership](https://www.postgresql.org/docs/current/sql-createdatabase.html).

**For either database, test the application credentials over TCP before continuing.** Run the command for your backend; enter the password when prompted:

```bash
# MySQL
mysql --host=127.0.0.1 --port=3306 --user=tz_user --password tz_database

# PostgreSQL
psql --host=127.0.0.1 --port=5432 --username=tz_user --password --dbname=tz_database
```

You should reach the database prompt. Exit with `exit` for MySQL or `\q` for PostgreSQL. If PostgreSQL rejects the TCP connection, check the server's [host authentication configuration](https://www.postgresql.org/docs/current/auth-pg-hba-conf.html) for this user and database before continuing.

### Step 4: Copy the Application Files

Create your private application directory outside the web document root, and your public service directory. For the example paths, run on the server:

```bash
sudo mkdir -p /var/www/lgv-tz-server /var/www/html/timezone
```

Here `/var/www/html` is the existing web document root, and `timezone` is the service subdirectory. If that root is served at `https://example.com/`, the service URL is `https://example.com/timezone/`. Adapt the filesystem directory and its matching URL together for a nested site. This manual layout uses the same parent-directory-plus-service-subdirectory relationship as the installer.

Copy **only these repository items**, keeping the relative paths, into `/var/www/lgv-tz-server/` (or your chosen private directory outside the web document root):

| Copy from the repository root | Purpose |
| --- | --- |
| `composer.json` and `composer.lock` | Dependency manifest and installed version record. |
| `vendor/` (entire directory) | Composer autoloader and streaming JSON parser. |
| `src/Sources/` (entire directory) | All five lookup, database, entity, PDO, and loader classes. |
| `src/index.php` | Service handler and command-line initial loader. |
| `src/LGV_TZ_Lookup_Test.php` and `src/TestLocations.php` | Required by the handler for its built-in location tests. |
| `update.sh` | Boundary update command. |
| `tools/update.php` and `tools/Setup.php` | Updater and download/extraction helpers. |
| `LICENSE` | License for the copied project code. |

To transfer that exact set over SSH, run these commands **on your development machine, from the repository root**, replacing `deploy-user` and `your-server`:

```bash
tar -czf /tmp/lgv-tz-server-files.tar.gz \
    composer.json composer.lock LICENSE vendor \
    src/Sources src/index.php src/LGV_TZ_Lookup_Test.php src/TestLocations.php \
    update.sh tools/update.php tools/Setup.php
scp /tmp/lgv-tz-server-files.tar.gz deploy-user@your-server:/tmp/
```

Then extract it **on the server**:

```bash
sudo tar -xzf /tmp/lgv-tz-server-files.tar.gz -C /var/www/lgv-tz-server
```

Create the two server-specific files described below: private `config.php` and public `index.php`. The complete manual layout is:

```text
/var/www/lgv-tz-server/                 # Outside the web document root
    composer.json
    composer.lock
    LICENSE
    config.php                        # Create with your database settings
    vendor/                           # The one Composer dependency directory
    src/
        index.php
        LGV_TZ_Lookup_Test.php
        TestLocations.php
        Sources/                      # All five .class.php files
    update.sh
    tools/
        update.php
        Setup.php

/var/www/html/timezone/                # Public service directory
    index.php                         # Create using the wrapper below
```

The root repository `index.php` is a redirect for browsing a checkout; the public file in this layout is the wrapper below. Omit `.git/`, `docs/`, `demo/`, `tests/`, `spec/`, `img/`, `icon.png`, `README.md`, `CHANGELOG.md`, `deploy.sh`, `generate-docs.sh`, and the other `tools/` files from this manual runtime copy. Boundary ZIP/GeoJSON files are temporary loading inputs. An existing server with a populated `timezones` table does not need another initial load; use the updater when you want fresh boundaries.

Keep the relative `src/` and `vendor/` layout intact. Run Composer only in the application root if you later regenerate dependencies. For an existing installation that used `src/composer.json`, `src/composer.lock`, or `src/vendor/`, remove those obsolete copies after placing the root dependencies and updating the entrypoint paths.

### Step 5: Create the Private Configuration

Create `/var/www/lgv-tz-server/config.php` on the server with the database name, user, and password from step 3. Start with this MySQL example:

```php
<?php
$g_dbName = 'tz_database';
$g_dbUserName = 'tz_user';
$g_dbPassword = 'your-db-password';
$g_dbType = 'mysql';
$g_dbHost = '127.0.0.1';
$g_dbPort = 3306;
$g_server_secret = 'your-server-secret';
```

For PostgreSQL, change only these two lines:

```php
$g_dbType = 'pgsql';
$g_dbPort = 5432;
```

Choose a server secret for `$g_server_secret`. You can generate one on the server with:

```bash
php -r 'echo bin2hex(random_bytes(32)).PHP_EOL;'
```

In the copied `/var/www/lgv-tz-server/src/index.php`, replace the existing `__CONFIG_FILE_` definition with the path to your private configuration:

```php
define('__CONFIG_FILE_', '/var/www/lgv-tz-server/config.php');
```

A nonempty server secret must be supplied as the `secret` query parameter. An empty or omitted secret allows public access. The Composer lookup API uses your application's settings and does not use this HTTP secret.

### Step 6: Create the Public Endpoint and Set Permissions

Create `/var/www/html/timezone/index.php` with these contents, changing the private path if needed:

```php
<?php
ini_set('display_errors', '0');
try {
    require '/var/www/lgv-tz-server/vendor/autoload.php';
    require '/var/www/lgv-tz-server/src/index.php';
} catch (Throwable $error) {
    http_response_code(503);
    error_log('Timezone service: '.$error->getMessage());
    echo 'Timezone service unavailable.';
}
```

Allow the web server's PHP user to traverse the private directory and read the code, `vendor/`, and `config.php`. For example, with PHP running in group `www-data`, run these commands as the deployment user, substituting the actual PHP group if different:

```bash
sudo chown -R "$(id -un):www-data" /var/www/lgv-tz-server
sudo chmod 0750 /var/www/lgv-tz-server
find /var/www/lgv-tz-server/vendor /var/www/lgv-tz-server/src /var/www/lgv-tz-server/tools -type d -exec chmod 0755 {} +
find /var/www/lgv-tz-server/vendor /var/www/lgv-tz-server/src /var/www/lgv-tz-server/tools -type f -exec chmod 0644 {} +
chmod 0640 /var/www/lgv-tz-server/config.php
chmod 0755 /var/www/lgv-tz-server/update.sh
sudo chmod 0755 /var/www/html/timezone
sudo chmod 0644 /var/www/html/timezone/index.php
```

The public directory must be served by your existing PHP-enabled web server. Keep configuration passwords and the server secret when replacing code on an existing installation.

### Step 7: Load the Initial Boundaries

On the server, open the [latest Timezone Boundary Builder release](https://github.com/evansiroky/timezone-boundary-builder/releases/latest) and download the asset named **`timezones-with-oceans.geojson.zip`** into `/var/www/lgv-tz-server/src/`. Extract it there:

```bash
cd /var/www/lgv-tz-server/src
unzip timezones-with-oceans.geojson.zip
```

Confirm that `combined-with-oceans.json` is in that directory. The copied root `vendor/` already includes the parser. Run the private handler directly, replacing `your-server-secret` with the value in `config.php`:

```bash
php -d memory_limit=1G /var/www/lgv-tz-server/src/index.php secret=your-server-secret load
```

The loader creates the `timezones` table and indexes. **Wait for it to print `1` before continuing.** A `0` means a caught loading error; an exception or PHP error also means the load did not finish. Check the configuration, PHP extensions, input filename, permissions, and memory limit before retrying. This command replaces the table and is available only through the CLI.

After a successful initial load, delete the downloaded ZIP and GeoJSON:

```bash
rm /var/www/lgv-tz-server/src/timezones-with-oceans.geojson.zip /var/www/lgv-tz-server/src/combined-with-oceans.json
```

**Keep the root `vendor/` directory** for autoloading and future updates. If you use the boundaries without oceans, change the input filename in `src/index.php` to `combined.json`; some known-location tests will then have different results.

### Step 8: Verify the Service

Visit this URL, substituting your hostname and server secret:

```text
https://tz.example.com/timezone/?ll=-77.036543,38.895037&secret=your-server-secret
```

The response should be the plain text **`America/New_York`**. Then run the built-in known-location tests:

```text
https://tz.example.com/timezone/?test&secret=your-server-secret
```

The tests return an HTML page with green passes and red failures. Review failures against the boundary release you loaded, especially if you chose a different dataset variant. A 403 response indicates an incorrect or missing secret. A 503 response indicates a PHP/configuration/database error; check the web server's PHP error log.

The Performance section reports total test time, average, median, 95th-percentile and slowest lookup times, and PHP request peak memory. Each location has its own lookup duration. Use the same endpoint and boundary database when comparing releases. Timings include the per-location database connection and query; HTTP/network and browser time are excluded.

### Step 9: Update Boundary Data Later

For either backend, run the copied updater on the server as the deployment user. It defaults to the private `config.php` at the application root:

```bash
/var/www/lgv-tz-server/update.sh --check   # Report the latest boundary release.
/var/www/lgv-tz-server/update.sh           # Download and replace the boundaries.
```

The updater uses the database backend from `config.php`, retains the existing table if loading fails, and deletes its downloaded files. See [updater details](#updater-details) for cleanup recovery. Keep `config.php`, the root Composer files, `vendor/`, and the copied updater when refreshing the application code.

## Query Performance

Queries test the existing packed polygon data in blocks of 1,024 points, rather than expanding entire candidate polygons into nested PHP arrays. Polygon rows are read sequentially without MySQL result buffering, and their cursor is closed when a match is found. Named-zone precedence and the existing single-candidate shortcut are retained. Reads also avoid unnecessary transaction round trips, and ocean fallback does not fetch named polygons again.

The database schema and polygon encoding are unchanged, so existing installations do not need a database reload for this query update. Loading the boundary file still uses the existing loader.

On October 7, 2026, a local PHP 8.5.10/MySQL 26.7.0 benchmark used the supplied boundary file, all 200 existing test locations, and 2,000 deterministic random locations. Each version performed 6,600 lookups, with a new database connection for each lookup:

| Measurement | Original (`0e7894a`) | Optimized |
| --- | ---: | ---: |
| Average lookup, including connection | 2.394 ms | 0.882 ms |
| 95th-percentile lookup | 15.657 ms | 5.532 ms |
| Peak additional PHP memory per lookup | 138.750 MiB | 7.724 MiB |
| Peak total PHP memory used | 142.252 MiB | 11.385 MiB |

All 200 expected locations passed and all 2,200 results matched the original implementation. The optimized run completed with `memory_limit=16M`; the original was measured with `memory_limit=1G`. These are local CLI measurements, excluding HTTP/network request overhead and boundary-file loading. Timing depends on the host, database configuration, and locations queried. Raw measurements and the boundary-file checksum are in [`tests/benchmarks/query-2026-10-07.json`](../tests/benchmarks/query-2026-10-07.json).

Run geometry, lookup-precedence, and PDO regression checks without a database server:

```bash
php tests/regression.php
```

To reproduce the database-backed comparison, create an isolated local database. The benchmark accepts only MySQL database names beginning with `lgv_tz_benchmark_`. **`--build` replaces the `timezones` table in that benchmark database.** It does not read the server's production configuration.

```bash
mysql -u root -e 'CREATE DATABASE lgv_tz_benchmark_local;'
php -d memory_limit=1G tools/benchmark.php --mysql=lgv_tz_benchmark_local --build
php tests/regression.php --mysql=lgv_tz_benchmark_local

task_baseline_dir=$(mktemp -d)
git archive 0e7894a src/Sources | tar -x -C "$task_baseline_dir"
php -d memory_limit=1G tools/benchmark.php --mysql=lgv_tz_benchmark_local --source="$task_baseline_dir/src" --runs=3 --random=2000 --reconnect --results=/tmp/lgv-tz-baseline-results.json
php -d memory_limit=16M tools/benchmark.php --mysql=lgv_tz_benchmark_local --runs=3 --random=2000 --reconnect --expect=/tmp/lgv-tz-baseline-results.json
```

MySQL benchmark connections default to local `root` with no password, as installed by Homebrew. Override these defaults with `LGV_TZ_BENCH_USER`, `LGV_TZ_BENCH_PASSWORD`, `LGV_TZ_BENCH_HOST`, and `LGV_TZ_BENCH_PORT` environment variables when needed. Omit `--reconnect` to measure queries using one connection. `--expect` verifies every location against saved baseline results, and repeated runs must return consistent results.

If MySQL is unavailable, `--fixture=/tmp/lgv-tz.sqlite --build` creates an isolated SQLite fixture from the same boundary file; use that `--fixture` argument on subsequent benchmark runs. SQLite results help compare PHP processing, but do not measure production MySQL behavior. Per-lookup peak-memory accounting requires PHP 8.2 or later.

## Database Integration Tests

The focused tests create and remove their own databases. After installing development dependencies, run:

```bash
composer test:mysql
composer test:postgres
```

Run `composer test:deploy:mysql` and `composer test:deploy:postgres` to check installer rollback, preservation of unrelated data, and ownership refusals. These use small temporary fixtures and do not download boundaries.

Those deployment checks also verify refreshing a populated installation, preserving its configuration and secret, interrupted-publication recovery, and the complete printed URL. Add `--real-composer` to `php tests/deploy.php --driver=pgsql` (or `mysql`) to exercise an actual Composer installation of the small local package fixture. Run `composer test:server` for deterministic performance-summary checks and passing/failing location-report fixtures without a database server.

They cover loading and schema reset, binary values (including zero bytes), ocean/named-zone precedence, polygons crossing decoding blocks, early reader cleanup, error recovery, and caller-owned transactions. A 24 MiB binary fixture checks bounded PHP memory; PostgreSQL tests also check the native result-buffer size when the driver exposes it. Override connection defaults with `LGV_TZ_TEST_HOST`, `LGV_TZ_TEST_PORT`, `LGV_TZ_TEST_USER`, `LGV_TZ_TEST_PASSWORD`, and `LGV_TZ_TEST_ADMIN_DATABASE`.

The benchmark tool also accepts `--pgsql=lgv_tz_benchmark_NAME`. PostgreSQL connections default to your shell username and port `5432`, with the same `LGV_TZ_BENCH_*` overrides. Create an isolated database first; `--build` replaces its `timezones` table, just as with MySQL.

On October 9, 2026, PHP 8.5.10 with MySQL 26.7.0 and PostgreSQL 18.6 returned identical results for 200 known locations and 2,000 seeded random points, using boundary release `2026d`. Both ran within a `16M` PHP memory limit, reusing one connection per backend:

| Database | Mean Lookup | Peak Extra PHP Memory |
| --- | ---: | ---: |
| MySQL | 1.018 ms | 8.428 MiB |
| PostgreSQL | 0.821 ms | 8.414 MiB |

These are one local run, and are different from the fresh-connection benchmark above. PHP counters exclude native database-client buffers. The complete demo also passed all 490 checks and validated all 1,355 stored polygons on each backend. [Recorded measurements](../tests/benchmarks/postgres-2026-10-09.json) include the boundary checksum and test setup.

## Generating the HTML Documentation

The HTML documentation uses the short README as its starting page, with this guide for detailed setup, demo, and performance instructions. The API reference includes private/static members, and the source browser includes the package, demo, tests, and benchmark tools.

You can rebuild it from the project root, like so:

```bash
./generate-docs.sh
```

You'll need Doxygen and PHP. The script looks for Doxygen in your PATH, and also recognizes the installed macOS Doxygen application. If you have it somewhere else, set `DOXYGEN` to its executable path. Graphviz's `dot` is optional; when available, it adds diagrams to the reference pages. The configuration and HTML layout were verified with Doxygen 1.18.0.

The build uses [docs/Doxyfile](../docs/Doxyfile), and writes the generated pages to `docs/html`. It first builds in a temporary directory, copies the README images, and checks local page links, anchors, styles, scripts, and images. Existing generated documentation is replaced after those steps succeed. Old generated pages are removed as part of that replacement.

For a local preview, you can serve the docs directory:

```bash
python3 -m http.server 8000 --directory docs
```

Open [http://localhost:8000/](http://localhost:8000/). The documentation uses static browser-side search and navigation, so it doesn't need the project's PHP server or MySQL database.

For GitHub Pages, commit the generated `docs` directory and select **Deploy from a branch**, using the **/docs** publishing folder, in the repository's Pages settings. The `.nojekyll` markers preserve generated filenames, and `docs/index.html` opens the documentation landing page. See [GitHub's publishing-source instructions](https://docs.github.com/en/pages/getting-started-with-github-pages/configuring-a-publishing-source-for-your-github-pages-site) for the settings.

> NOTE: If you use Doxywizard directly, use `docs` as the working directory. The root shell script handles the complete build, image copying, link checks, and stale-page cleanup.

## How the Lookup Works

The library uses the [Timezone Boundary Builder](https://github.com/evansiroky/timezone-boundary-builder) GeoJSON boundaries. Time zones follow political borders, so longitude alone cannot determine them.

Loading splits multipolygons into individual polygons and stores each exterior ring with its enclosing rectangle. A lookup uses the indexed rectangles to find candidates, then uses a winding-number test when needed. A single-candidate shortcut avoids decoding that polygon. Named time zones take precedence over ocean fallback zones.

The loader stores exterior rings and does not preserve polygon holes. The implementation assumes the simple closed rings produced by the boundary dataset. The demo's independent geometry checks account for holes when generating reference expectations.
