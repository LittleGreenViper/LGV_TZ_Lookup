<?php
/**
    © Copyright 2023-2026, Little Green Viper Software Development LLC.
    MIT License; see LICENSE.
    \file
    \brief Verify the built-in server test report and performance summaries without a database server.
*/
declare(strict_types=1);
require dirname(__DIR__).'/src/LGV_TZ_Lookup_Test.php';
require dirname(__DIR__).'/src/TestLocations.php';
$expectedLookups = [];
foreach ($test_locations_param_array as $case) {
    $expectedLookups[$case['params']['lng'].','.$case['params']['lat']] = $case['result'];
}
$forceFailure = in_array('--failure', $argv, true);
$calls = 0;
$checks = 0;

/** \brief Exercise the report with known lookup results, including one optional failure. */
function call_server(string $query, bool $cli): string {
    global $expectedLookups, $forceFailure, $calls;
    parse_str($query, $arguments);
    if (($arguments['secret'] ?? '') !== 'fixture-secret') { throw new RuntimeException('Test requests lost their configured secret.'); }
    ++$calls;
    return $forceFailure && $calls === 1 ? 'Wrong/Zone' : $expectedLookups[$arguments['ll']];
}

/** \brief Assert one report property. */
function reportCheck(bool $value, string $label): void {
    global $checks;
    ++$checks;
    if (!$value) { throw new RuntimeException($label); }
}

$summary = test_server_performance(range(1000000, 20000000, 1000000), 1000000000, 8 * 1048576);
foreach (['Lookups measured: 20', 'Total test time: 1.000 s', 'Average lookup: 10.500 ms',
    'Median lookup: 10.500 ms', '95th-percentile lookup: 19.000 ms', 'Slowest lookup: 20.000 ms',
    'PHP request peak memory: 8.00 MiB'] as $text) {
    reportCheck(str_contains($summary, $text), 'Incorrect performance statistic: '.$text);
}
reportCheck(test_server_performance([], 0, 0) === '', 'Empty report has no statistics.');
reportCheck(str_contains(test_server_performance([3000000, 1000000, 2000000], 0, 0), 'Median lookup: 2.000 ms'), 'Odd sample median.');
$config = tempnam(sys_get_temp_dir(), 'lgv-tz-report-');
file_put_contents($config, '<?php $g_server_secret = "fixture-secret";');
define('__CONFIG_FILE_', $config);
try {
    $html = test_server();
    reportCheck($calls === 200, 'All locations are measured.');
    reportCheck(str_contains($html, $forceFailure ? '1 Test Failures!' : 'All Tests (200) Passed!'), 'Location results are retained.');
    reportCheck(substr_count($html, 'Lookup time: ') === 200, 'Each location reports elapsed time.');
    reportCheck(str_contains($html, 'Lookups measured: 200'), 'Summary measures every result, including failures.');
    reportCheck(preg_match('/Total test time: [0-9]+\.[0-9]{3} s/', $html) === 1, 'Total time is reported in seconds.');
    reportCheck(str_contains($html, 'PHP request peak memory:'), 'Memory is reported.');
    reportCheck(!str_contains($html, 'fixture-secret'), 'Report does not disclose the secret.');
    echo 'Passed '.$checks.' server report checks'.($forceFailure ? ' with a failing location' : '').".\n";
} finally { unlink($config); }
