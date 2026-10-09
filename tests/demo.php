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
    \brief These are the fast checks for the demo's reference geometry and cleanup guards.

    Run composer test:demo after installing development dependencies. Small, known geometries check holes, ocean
    fallback, boundary offsets, and decoding-block transitions. Cleanup checks invoke a separate worker with test
    records that are refused before any database operation. No download or running MySQL server is required.
*/
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/demo/SystemTests.php';

/***************************************************************************************************************************/
/** \brief This counts successful assertions in the fast demo checks. */
$checks = 0;
/***************************************************************************************************************************/
/**
    \brief This compares one test result with its expected value.
    \throws RuntimeException if the values differ, including a description of the failed check.
*/
function demoCheck($expected,       ///< The independently specified value expected from the check.
                    $actual,        ///< The value returned by the operation under test.
                    string $label   ///< The description included in a failure message.
                    ): void {
    global $checks;
    ++$checks;
    if ($expected !== $actual) {
        throw new RuntimeException($label.': expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}
/***************************************************************************************************************************/
/**
    \brief This constructs the decoded Feature fields needed by the reference collector.
    \returns: A Polygon geometry and its properties.tzid value.
*/
function demoFeature(string $zone,  ///< The zone label for the test geometry.
                    array $rings    ///< The exterior ring, followed by any hole rings.
                    ): array {
    return ['properties' => ['tzid' => $zone], 'geometry' => ['type' => 'Polygon', 'coordinates' => $rings]];
}

$source = new DemoSourceGeometry();
$outer = [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]];
$hole = [[3, 3], [7, 3], [7, 7], [3, 7], [3, 3]];
$source->observe(demoFeature('Zone/Outer', [$outer, $hole]));
$source->observe(demoFeature('Zone/Inner', [$hole]));
$source->observe(demoFeature('Etc/GMT', [[[-10, -10], [20, -10], [20, 20], [-10, 20], [-10, -10]]]));
demoCheck(['Zone/Outer'], $source->zonesAt(1, 1), 'Reference interior');
demoCheck(['Zone/Inner'], $source->zonesAt(5, 5), 'Reference honors holes and enclaves');
demoCheck(['Etc/GMT'], $source->zonesAt(-5, -5), 'Reference ocean fallback');
demoCheck([], $source->zonesAt(30, 30), 'Reference outside coverage');
demoCheck(15, $source->vertices, 'Expected stored outer-ring vertices');
demoCheck(240, $source->polygonBytes, 'Expected stored polygon size');
demoCheck(hash('sha256', pack('d*', 0, 0, 10, 0, 10, 10, 0, 10, 0, 0)), $source->shapes[1]['sha256'], 'Reference storage hash');
demoCheck(['east' => 10, 'west' => 0, 'north' => 10, 'south' => 0], $source->shapes[1]['bounds'], 'Source bounding box');

$cases = $source->casesForLargest(1);
demoCheck(true, count($cases) >= 8, 'Both sides of a boundary are sampled');
$firstEdge = [];
foreach ($cases as $case) {
    if (($case['pair'] ?? '') === '1:0') { $firstEdge[$case['side']] = $case; }
}
demoCheck(['Etc/GMT'], $firstEdge[-1]['expected'], 'South side of the square falls in the ocean');
demoCheck(['Zone/Outer'], $firstEdge[1]['expected'], 'North side of the square falls inside the zone');
demoCheck(true, abs($firstEdge[-1]['params']['lat'] * 111.32 + 5) < 1e-8, 'Outside probe is five kilometers from the edge');
demoCheck(true, abs($firstEdge[1]['params']['lat'] * 111.32 - 5) < 1e-8, 'Inside probe is five kilometers from the edge');
// Analytic circle tests verify the independent ray-casting oracle across decoding-block boundaries.
$circle = '';
$vertices = 2053;
for ($i = 0; $i < $vertices; ++$i) {
    $angle = 2 * M_PI * $i / $vertices;
    $circle .= pack('d2', 5 * cos($angle), 5 * sin($angle));
}
foreach ([0, 1022, 1023, 1024, 2047, 2048, 2052] as $index) {
    $angle = 2 * M_PI * $index / $vertices;
    demoCheck(true, DemoSourceGeometry::ringContains($circle, 4.9 * cos($angle), 4.9 * sin($angle)), 'Circle interior across block boundary');
    demoCheck(false, DemoSourceGeometry::ringContains($circle, 5.1 * cos($angle), 5.1 * sin($angle)), 'Circle exterior across block boundary');
}
demoCheck(false, DemoSourceGeometry::ringContains('', 0, 0), 'Empty ring');

// A corrupted cleanup record must never name a user's database; large downloads are still discarded first.
$directory = sys_get_temp_dir().'/lgv-tz-demo-check-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
try {
    file_put_contents($directory.'/boundaries.zip', 'temporary archive');
    file_put_contents($directory.'/boundaries.json', 'temporary boundary data');
    file_put_contents($directory.'/database.json', json_encode(['database' => 'production']));
    $process = proc_open([PHP_BINARY, dirname(__DIR__).'/demo/demo.php', 'cleanup', $directory],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    demoCheck(1, proc_close($process), 'Refuse to drop an unrelated database');
    demoCheck(true, str_contains($error, 'refusing to drop'), 'Cleanup refusal explains its reason');
    demoCheck(false, is_file($directory.'/boundaries.zip'), 'Archive removed on failed cleanup');
    demoCheck(false, is_file($directory.'/boundaries.json'), 'GeoJSON removed on failed cleanup');
    demoCheck(true, is_file($directory.'/database.json'), 'Cleanup record retained for investigation');

    file_put_contents($directory.'/boundaries.zip', 'temporary archive');
    file_put_contents($directory.'/boundaries.json', 'temporary boundary data');
    file_put_contents($directory.'/database.json', json_encode(['database' => 'lgv_tz_demo_'.str_repeat('a', 24)]));
    $environment = getenv();
    $environment['LGV_TZ_DEMO_PORT'] = '0';
    $process = proc_open([PHP_BINARY, dirname(__DIR__).'/demo/demo.php', 'cleanup', $directory],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
    stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    demoCheck(1, proc_close($process), 'Database cleanup error has a failing status');
    demoCheck(true, str_contains($error, 'LGV_TZ_DEMO_PORT'), 'Database configuration error is reported');
    demoCheck(false, is_file($directory.'/boundaries.zip'), 'Archive removed despite database error');
    demoCheck(false, is_file($directory.'/boundaries.json'), 'GeoJSON removed despite database error');
    demoCheck(true, is_file($directory.'/database.json'), 'Recovery record survives database error');
} finally {
    foreach (glob($directory.'/*') as $file) { unlink($file); }
    rmdir($directory);
}
echo 'Passed '.$checks." demo reference and cleanup checks.\n";
