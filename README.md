<div style="float:right"><a href="https://github.com/LittleGreenViper/LGV_TZ_Lookup">
<img style="width:32px" src="img/GitHub-Mark-64px.png" />
</a></div>
<div style="float:left">
<img src="img/icon.png" />
</div>
<div style="clear:both"></div>

# LGV_TZ_Lookup
A Server and PHP Library for Matching Long/Lat to Timezone

## Overview
This project is a fairly simple PHP project, designed to accept the GeoJSON output of [the Timezone Boundary Builder Project](https://github.com/evansiroky/timezone-boundary-builder), and provide a simple API, for matching longitude/latitude locations with timezones.

Use the lookup classes directly in a PHP application through Composer, or run the standalone HTTP server described below. Both use the same preloaded boundary database and lookup implementation.

Send in a long/lat, and get back a string, with [the standard TZ time zone designator](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones) of the timezone that covers that point.

[This is the GitHub repo for this project](https://github.com/LittleGreenViper/LGV_TZ_Lookup)

## What Problem Does This Solve?
Unfortunately, time zones are not a simple "I'm at this longitude, so it must be this time." They are political constructs.

Here's why we can't just do a simple longitude match:

![Time Zones Of the World](img/World_Time_Zones_Map.png)
[_Image Source: Wikimedia Commons_](https://commons.wikimedia.org/wiki/File:World_Time_Zones_Map.png)

We address this by using the rendered result of [this great project](https://github.com/evansiroky/timezone-boundary-builder), which is an effort to build a "living document" map of all the world timezones, as a shapefile (a file that can project polygons over a digital map), and locating a geographic point, within those shapes.

## How This Works
We provide a very basic PHP server that builds a simple database from the data in the massive shapefile (The [GeoJSON](https://geojson.org) variant that results from the timezone boundary builder. It can be found in any of [the project releases](https://github.com/evansiroky/timezone-boundary-builder/releases)). The database is deliberately "dumb," with a view towards making the project as flexible as possible, and lookups fast and easy.

Each timezone is described in [a GeoJSON polygon](https://datatracker.ietf.org/doc/html/rfc7946#section-3.1.6) (or [multipolygon](https://datatracker.ietf.org/doc/html/rfc7946#section-3.1.7)).

> NOTE: We are making _huge_ assumptions about the file. We assume that the polygons are very basic, "closed" polygons, and that multipolygons are simply aggregations of simple polygons (as opposed to making "holes," and whatnot).

We build a database of polygons (breaking up multipolygons), with what we term a "domain rect." This is a rectangle that encloses the entire polygon, regardless of the shape of the polygon.

The "domain rect" is used for a fast "triage" lookup. Its vertices are indexed in the database, so comparisons are zippy. We can quickly find the timezones that may contain our location, and ignore the rest.

In some cases, the domain rect "triage" may return only one result, so we got it in one. In other cases, we can then do a simple ["Winding Number"](https://en.m.wikipedia.org/wiki/Winding_number) lookup of the location, using the un-indexed polygon data for that timezone, and figure out which polygon actually has it. We return the first one.

From a usage standpoint, you simply send in a longitude/latitude pair, as a simple [HTTP GET](https://www.w3schools.com/tags/ref_httpmethods.asp), and you will receive a "raw" string response, with the [TZ](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones) name of the timezone that applies to the location.

`http`_[_`s`_]_`://`_&lt;YOUR SERVER URL TO THE src DIRECTORY>[_`/index.php`_]_`?ll=`&lt;LONGITUDE>,&lt;LATITUDE>

`https://tz.example.com?ll=-73.123,44.456`

The long/lat is sent as a comma-separated pair of floating-point numbers that represent degrees of longitude and latitude.

## Dependencies
Lookup requires PHP 8.0 or later, the PDO and PDO MySQL extensions (`ext-pdo` and `ext-pdo_mysql`), and a populated MySQL boundary database. The standalone server is designed for your classic ["LAMP"](https://en.wikipedia.org/wiki/LAMP_(software_bundle)) hosting.

This project uses [the streaming JSON parser](https://github.com/salsify/jsonstreamingparser), in order to parse [this file](https://github.com/evansiroky/timezone-boundary-builder/releases/download/2023b/timezones-with-oceans.geojson.zip) (a current release, at the time of this writing), which is [a GeoJSON file](https://geojson.org), containing the calculated timezones, and is created by [this project](https://github.com/evansiroky/timezone-boundary-builder).

Otherwise, it is a very basic [PHP](https://php.net) project (tested against [PHP 8.2](https://www.php.net/releases/8.2/en.php), at the time of this writing).

The initial release is built for [MySQL](https://www.mysql.com) (tested against [MySQL 5.7](https://downloads.mysql.com/archives/community/)), but uses [PHP PDO](https://www.php.net/manual/en/book.pdo.php), and has [an absurdly simple database schema](https://github.com/LittleGreenViper/LGV_TZ_Lookup/blob/5cb4aafac824b330c9181a2186ff3c89aac784f6/src/Sources/LGV_TZ_Lookup_Database.class.php#L65), so it can be expanded to other databases fairly easily.

The [streaming JSON parser](https://github.com/salsify/jsonstreamingparser) is required only for boundary-file loading. It is an optional dependency of the Composer library; lookup installations do not install it.

### Batteries Not Included
Well...that's not _strictly_ true. You'll need to download the GeoJSON file from [the Timezone Boundary Builder Project releases](https://github.com/evansiroky/timezone-boundary-builder/releases). It's a big file, and may be updated, as timezones change. You can use either of the files (with or without oceans), but the project tests against the oceans variant.

## Composer Library

The package name is `littlegreenviper/lgv_tz_lookup`. Its root `composer.json` registers the existing `LGV_TZ_Lookup_*` classes for autoloading. Requiring your application's `vendor/autoload.php` makes the lookup API available without starting the HTTP server or loading the JSON parser.

### Install From GitHub

Release `1.3.0` includes the Composer library. Run these commands in **your application's directory**:

```bash
composer config repositories.lgv-tz-lookup vcs https://github.com/LittleGreenViper/LGV_TZ_Lookup.git
composer require littlegreenviper/lgv_tz_lookup:^1.3
```

This uses a [Composer VCS repository](https://getcomposer.org/doc/05-repositories.md#vcs), so Packagist registration is not required. Tags `1.2.0` and earlier predate the Composer library manifest. Once the package is registered on Packagist, applications can omit the repository configuration.

To try a local checkout before pushing, use a path repository instead:

```bash
composer config repositories.lgv-tz-lookup path /absolute/path/to/LGV_TZ_Lookup
composer require littlegreenviper/lgv_tz_lookup:@dev
```

### Look Up a Time Zone

The `timezones` table must already have been populated using the standalone loader or imported from an existing installation. Lookup itself requires only `SELECT` access to that table. It does not download boundary data or create the table.

```php
<?php

require __DIR__.'/vendor/autoload.php';

$database = new LGV_TZ_Lookup_Database(
    'tz_database',       // Database name.
    'tz_reader',         // Database user.
    'your-db-password',  // Database password.
    'mysql',
    '127.0.0.1',
    3306
);
$lookup = new LGV_TZ_Lookup_Query($database);

echo $lookup->get_tz(-77.036543, 38.895037); // America/New_York (Washington, DC).
```

Pass longitude first, then latitude, as numeric degrees within `[-180, 180]` and `[-90, 90]`. The result is a time zone name, or an empty string when no match is found. You can reuse the database and lookup objects for multiple coordinates. Pass connection settings from your application's configuration; the standalone server's separate config file and HTTP secret are not used by this API. Creating a library database connection preserves your application's execution time limit.

### Optional Boundary Loading

The library's runtime dependencies are PHP, PDO, and PDO MySQL. Applications that also need the loader can add its parser explicitly:

```bash
composer require salsify/json-streaming-parser:"8.3.*"
```

`LGV_TZ_Lookup_Loader` then becomes usable through the same autoloader. Initial database loading and subsequent boundary updates can also be handled separately with the standalone CLI instructions below. The GeoJSON boundary file is obtained separately and is not bundled in the library.

For development in a repository checkout, running `composer install` at the repository root installs the optional parser as a development dependency. Run `composer test` for regression checks and `composer test:composer` for the autoloading and optional-loader checks. When this library is installed into another application, its development dependencies are not installed.

## Turnkey Command-line Demo

If you'd like to try the Composer package, we have a simple command-line demo. You'll need PHP and a running MySQL server. The demo takes care of fetching Composer, installing the package, downloading the boundary data, loading a test database, and running the tests.

You run it from the repository root, like so:

```bash
./demo/run.sh
```

That's the whole command. There are no command-line arguments, and you don't need to set up the HTTP server first.

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

These are pretty big files. On October 9, 2026, the archive was about 53 MiB, and the extracted JSON was about 175 MiB. You'll need temporary disk space for both, as well as space for the MySQL table. Loading can take a while; progress messages tell you which part of the run is underway.

### What Gets Tested?

First, we check what was loaded. Every stored polygon's name, domain rect, point count, and packed exterior-ring data are compared with the decoded GeoJSON. MySQL calculates the polygon hashes, so we can check the contents without bringing all of the large blobs back into PHP.

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
| PHP memory | Loading/reference peaks, and additional memory needed by an individual lookup. MySQL and the separate Composer process are outside these measurements. |
| Polygon work | Rows and bytes actually evaluated across lookup calls. A polygon read more than once is counted each time. |
| Large-polygon coverage | How many selected polygons were decoded, with their IDs, timezone names, point counts, and packed sizes. |
| Boundary crossings | How many sampled pairs fall in different timezones, including named-to-named crossings. |

The lookup calls reuse one database connection. These timings describe the PHP/MySQL lookup API; an HTTP request or a fresh connection will add its own time.

Loading memory includes the compact source geometry retained for independent checks. We free that reference before measuring lookup memory. PHP 8.2 and later can reset the memory peak between phases and calls; older versions report lifetime peaks instead. Actual times and memory use will depend on the host, the selected polygons, and the boundary release.

To save a run's output, while preserving its exit status, you can redirect it to a small report file:

```bash
./demo/run.sh > /tmp/lgv-tz-demo-results.txt 2>&1
```

### Initial Setup

The script uses POSIX `sh` and PHP, with no Homebrew-specific paths or GNU-only commands. It requires PHP 8.0 or later with `pdo_mysql`, `curl`, `zip`, `mbstring`, and `ctype` enabled, and `proc_open` available. MySQL must be running, and the configured user must be able to create and drop a temporary database, create its tables, and insert and read rows. A web server, the MySQL command-line client, and an existing project configuration file are not needed.

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
| `LGV_TZ_DEMO_HOST` | `localhost` | MySQL host. |
| `LGV_TZ_DEMO_PORT` | `3306` | MySQL port for TCP connections; use a host such as `127.0.0.1` to force TCP instead of the `localhost` socket. |
| `LGV_TZ_DEMO_USER` | `root` | User with permissions for the temporary database. |
| `LGV_TZ_DEMO_PASSWORD` | Empty | MySQL password. |
| `LGV_TZ_DEMO_PACKAGE_VERSION` | `^1.3` | Composer library version constraint. |
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
LGV_TZ_DEMO_PACKAGE_VERSION=1.3.0 ./demo/run.sh
```

The boundary download still uses the latest published release. Selecting a package version does not select an older shapefile.

### What Gets Cleaned Up?

The ZIP and extracted JSON are deleted as soon as loading finishes, including a failed load. When the worker exits, the shell starts a separate cleanup process to drop that run's database. Only after database cleanup succeeds does it remove the rest of the temporary application, including Composer and its cache.

This cleanup also runs after Ctrl+C, an ordinary worker error, or a PHP loader memory-limit failure. Those paths were tested on macOS. The script uses POSIX `sh` and PHP for Linux/Unix portability; the complete run has been verified on the Homebrew setup.

If MySQL becomes unavailable during cleanup, the large downloads are still deleted, and the working directory is retained with `database.json`. The error message prints that directory. Once MySQL is available again, you can retry cleanup with the same connection settings:

```bash
saved_demo_directory='<WORKING DIRECTORY PRINTED BY THE DEMO>'
php demo/demo.php cleanup "$saved_demo_directory" && rm -rf "$saved_demo_directory"
```

Use the directory printed by the demo. The cleanup worker reads its database name from the record and requires the generated `lgv_tz_demo_` naming pattern. If cleanup still fails, the record and working directory remain available for another attempt.

### The Demo Files

| File | Purpose |
| --- | --- |
| [demo/run.sh](demo/run.sh) | The command you run. Creates the private working directory and handles exit/interruption cleanup. |
| [demo/demo.php](demo/demo.php) | The PHP worker. Installs the package, obtains and loads data, runs tests, and reports metrics. Its separate cleanup mode removes downloads and the database. |
| [demo/SystemTests.php](demo/SystemTests.php) | The independent source-geometry reference, generated test cases, and polygon audit counters. |
| [tests/demo.php](tests/demo.php) | Small reference-geometry and cleanup-guard checks for development. |

If you are working on the demo itself, install the development dependencies at the repository root and run the small checks:

```bash
composer install
composer test:demo
```

Those checks cover holes, ocean fallback, boundary offsets, decoder block transitions, and cleanup guards. They do not download the shapefile or need a running MySQL server. Run `./demo/run.sh` for the complete Composer/database system test.

## Standalone Server Implementation

### Initial Installation
Once you have a server available, install the contents of the [`src` subdirectory](https://github.com/LittleGreenViper/LGV_TZ_Lookup/tree/main/src) into a place of your choosing, accessible via HTTP. You should have a URI that points to the [`index.php` file](https://github.com/LittleGreenViper/LGV_TZ_Lookup/blob/main/src/index.php) in the [`src` directory](https://github.com/LittleGreenViper/LGV_TZ_Lookup/tree/main/src).

### Database Setup
You will need to set up a MySQL database, with a user with basic full permissions.

### Config File
A requirement for the server is a configuration file. It should generally be placed outside the HTTP-accesible directory tree, and you will need to modify the line in the [`index.php`](https://github.com/LittleGreenViper/LGV_TZ_Lookup/blob/f9914c89e8484522732100ea82f8b1cab8c667f6/src/index.php#L51) file that looks like this:

`define("__CONFIG_FILE_", __DIR__.'/../../../../TZInfo/config.php');`

To point to the configuration file.

The contents of the configuration file will look like this:

```
<?php 
    $g_dbName = "<DATABASE NAME>";
    $g_dbUserName = "<DATABASE USERNAME>";
    $g_dbPassword = "<DATABASE USER PASSWORD>";
    $g_dbType = "<DATABASE TYPE>";
    $g_dbHost = "<DATABASE HOST>";
    $g_dbPort = "<DATABASE PORT>";
    $g_server_secret = "<SERVER SECRET>";
```

with each of the strings changed to match the configuration. Here's an example:

```
<?php 
    $g_dbName = "HostingHash_TZDB";
    $g_dbUserName = "tzUser";
    $g_dbPassword = "swordfish";
    $g_dbType = "mysql";
    $g_dbHost = "127.0.0.1";
    $g_dbPort = "3306";
    $g_server_secret = "Shh-Dont-Tell-Anyone";
```

That `$g_server_secret` is important, if you don't want "just anyone" accessing the server. If it is set to a string, then every call to the server (including the command line) will need to have a `secret=<SERVER SECRET>` query argument added to the regular query, like so:

#### HTTP Request:
`https://tz.example.com/index.php?secret=Shh-Dont-Tell-Anyone&ll=-73.123,44.456`

#### Command Line:
`?> php`_&lt;PATH TO THE src DIRECTORY>_`/index.php secret=Shh-Dont-Tell-Anyone load`

### Loader Dependency
For a standalone server installed by copying `src`, install the loader's JSON parser in that directory:

```(bash)
$> cd <YOUR src DIRECTORY>
$> composer install
```

That will set up a `vendor` subdirectory inside `src`. A full repository checkout can instead use `composer install` at the repository root; the loader supports both layouts.

>NOTE: The dependency is only required for the load and setup. It is not required for subsequent queries.

### The Data File
You'll need to fetch the GeoJSON data file from [the Boundary Builder Project Releases Directory](https://github.com/evansiroky/timezone-boundary-builder/releases). Get the latest release, and look for files named `timezones.geojson.zip`, or `timezones-with-oceans.geojson.zip`.

They are pretty big files.

Unzip the file into the `src` directory.

>NOTE: We have the project initially set up for the `combined-with-oceans.json` file. If you want to use the `combined.json` file, instead, then you should edit the [`index.php`](https://github.com/LittleGreenViper/LGV_TZ_Lookup/blob/f9914c89e8484522732100ea82f8b1cab8c667f6/src/index.php#LL113C14-L113C14) file at the line that looks like this:

```(php)
    $stream = fopen("$path/combined-with-oceans.json", 'r');

```

so that it looks like this:

```(php)
    $stream = fopen("$path/combined.json", 'r');

```

>CAUTION: If you do this, some of the tests won't pass!

### The Initial Load and Database Setup
You don't need to "prime" the database. The server will take care of creating the table.

However, the initial load can only be done via command-line, so you'll need to SSH into the server, and run the load from there. You use the syntax indicated above:

```(bash)
$> php <YOUR src DIRECTORY>/index.php secret=<SERVER SECRET> load
```

This can take a while.

Upon success, the script emits a simple `1`. If there was a problem, it emits a `0`.

Once that's done, the standalone server is ready to go. In a server installed directly from `src`, you can delete the JSON file and `src/vendor` after loading; restore them before another load. Composer applications keep their own `vendor` directory for the library's autoloader.

You won't need to run `load` very often, so it shouldn't be in a `cron` job, or anything.

All interactions from then on, are done via HTTP.

### Testing
We have a set of simple tests that can be run. They send in known coordinates, and ensure that the server returns the proper response.

You run the tests, like so:

`https://tz.example.com`_[_`/index.php`_]_`?`_[_`secret=`&lt;SERVER SECRET&gt;`&`_]_`test`

You will get a simple HTML page, with each of the tests, listed. If the test passes, the title will be green. If it fails, the title will be red, and there will be a list of links to failures, at the top of the page.

Everything should be green (unless you swapped out the JSON file, in which case, you'll need to look for the failures, and validate the tests).

### Query Performance

Queries test the existing packed polygon data in blocks of 1,024 points, rather than expanding entire candidate polygons into nested PHP arrays. Polygon rows are read sequentially without MySQL result buffering, and their cursor is closed when a match is found. Named-zone precedence and the existing single-candidate shortcut are retained. Reads also avoid unnecessary transaction round trips, and ocean fallback does not fetch named polygons again.

The database schema and polygon encoding are unchanged, so existing installations do not need a database reload for this query update. Loading the boundary file still uses the existing loader.

On October 7, 2026, a local PHP 8.5.10/MySQL 26.7.0 benchmark used the supplied boundary file, all 200 existing test locations, and 2,000 deterministic random locations. Each version performed 6,600 lookups, with a new database connection for each lookup:

| Measurement | Original (`0e7894a`) | Optimized |
| --- | ---: | ---: |
| Average lookup, including connection | 2.394 ms | 0.882 ms |
| 95th-percentile lookup | 15.657 ms | 5.532 ms |
| Peak additional PHP memory per lookup | 138.750 MiB | 7.724 MiB |
| Peak total PHP memory used | 142.252 MiB | 11.385 MiB |

All 200 expected locations passed and all 2,200 results matched the original implementation. The optimized run completed with `memory_limit=16M`; the original was measured with `memory_limit=1G`. These are local CLI measurements, excluding HTTP/network request overhead and boundary-file loading. Timing depends on the host, database configuration, and locations queried. Raw measurements and the boundary-file checksum are in [`tests/benchmarks/query-2026-10-07.json`](tests/benchmarks/query-2026-10-07.json).

Run geometry, lookup-precedence, and PDO regression checks without a MySQL server:

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

## Generating the HTML Documentation

The HTML documentation uses this README as its starting page, with links to the library, demo, server setup, and performance notes. The API reference includes private/static members, and the source browser includes the package, demo, tests, and benchmark tools.

You can rebuild it from the project root, like so:

```bash
./generate-docs.sh
```

You'll need Doxygen and PHP. The script looks for Doxygen in your PATH, and also recognizes the installed macOS Doxygen application. If you have it somewhere else, set `DOXYGEN` to its executable path. Graphviz's `dot` is optional; when available, it adds diagrams to the reference pages. The configuration and HTML layout were verified with Doxygen 1.18.0.

The build uses [docs/Doxyfile](docs/Doxyfile), and writes the generated pages to `docs/html`. It first builds in a temporary directory, copies the README images, and checks local page links, anchors, styles, scripts, and images. Existing generated documentation is replaced after those steps succeed. Old generated pages are removed as part of that replacement.

For a local preview, you can serve the docs directory:

```bash
python3 -m http.server 8000 --directory docs
```

Open [http://localhost:8000/](http://localhost:8000/). The documentation uses static browser-side search and navigation, so it doesn't need the project's PHP server or MySQL database.

For GitHub Pages, commit the generated `docs` directory and select **Deploy from a branch**, using the **/docs** publishing folder, in the repository's Pages settings. The `.nojekyll` markers preserve generated filenames, and `docs/index.html` opens the documentation landing page. See [GitHub's publishing-source instructions](https://docs.github.com/en/pages/getting-started-with-github-pages/configuring-a-publishing-source-for-your-github-pages-site) for the settings.

> NOTE: If you use Doxywizard directly, use `docs` as the working directory. The root shell script handles the complete build, image copying, link checks, and stale-page cleanup.

## License
This is an [MIT-Licensed](https://opensource.org/license/mit/) project.
