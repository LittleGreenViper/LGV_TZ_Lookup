**1.4.2** *October 9, 2026*

- The server installer asks for the selected web directory's public URL and prints complete service, lookup, and test URLs, preserving nested URL prefixes; clarified the web directory and service subdirectory in the installation guide.
- Rerunning the installer against a matching installer-owned service refreshes the code and updater while preserving the populated boundary database, private configuration, and secret. Failed or interrupted refreshes restore the previous directories.
- Built-in location tests report total time, individual lookup times, average, median, 95th percentile, slowest lookup, and PHP request peak memory.

**1.4.1** *October 9, 2026*

- Added a simple boundary updater that reports the latest shapefile release, downloads and reloads the full GeoJSON boundaries with oceans, and deletes the downloaded files afterward.
- Added a `--check` mode to report the latest boundary version without loading it or connecting to the database.
- Updates load into a staging table and replace the live MySQL or PostgreSQL table only after parsing succeeds; failed loads retain existing boundaries.
- New server deployments include the updater beside the private configuration, with interruption cleanup and documentation.
- Simplified the README around the Composer lookup and main commands; moved detailed setup, demo metrics, and development instructions into a linked usage guide.
- Consolidated Composer files and dependencies at the repository root, removed the redundant `src` installation, and documented the exact files and directory layout for copying the service to a server.

**1.4.0** *October 9, 2026*

- Added PostgreSQL lookup/loading support alongside MySQL, with binary-safe storage, bounded polygon reads, and backend-selectable Composer demos and integration tests.
- Added one-command server deployment with prompted database settings, a random server secret enabled by default, private configuration, tested loading, and ownership-checked rollback.
- Added a portable command-line Composer demo that downloads the latest boundaries, loads a temporary MySQL or PostgreSQL database, runs location tests, and removes the data and database afterward.
- Added independent source/storage validation and boundary/interior probes for the largest polygons, with phase timings, lookup latency, memory, data-size and coverage metrics.
- Documented demo setup, results, metrics, cleanup recovery, and helper APIs, with matching MIT license notices.
- Improved HTML documentation coverage and navigation, with the README landing page and a root generate-docs.sh build script.

**1.3.0** *October 9, 2026*

- Added Composer library packaging and lookup usage instructions. Boundary-file loading remains optional.
- Queries no longer load the JSON parser, and library connections preserve the application's execution time limit.

**1.2.0** *October 7, 2026*

- Used an LLM to optimize the project for lookup performance and memory footprint.

**1.1.1** *February 21 2026*

- Changed "Jerusalem" in the tests, to "Hebron."

**1.1.0** *September 30 2023*

- Switched to using PHP to pack the polygon, as MySQL has discontinued their polygon function. It's for the better, anyway. This is a lot faster.

**1.0.2** *June 22 2023*

- No code changes. Added docs directory.

**1.0.1** *June 21, 2023*

- Minor tweak that probably won't change anything. If one zone is smaller than another, it is checked first.

**1.0.0** *June 16, 2023*

- Initial Release
