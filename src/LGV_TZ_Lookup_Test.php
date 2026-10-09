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
    \brief This file has some basic query tests for the server.
 */
 
declare(strict_types = 1);

/***************************************************************************************************************************/
/** \brief Summarize request lookup timings and the PHP request's peak memory. */
function test_server_performance(array $nanoseconds, int|float $elapsed, int $peakMemory): string {
    sort($nanoseconds, SORT_NUMERIC);
    $samples = count($nanoseconds);
    if ($samples === 0) { return ''; }
    $middle = intdiv($samples, 2);
    $median = $samples % 2 ? $nanoseconds[$middle] : ($nanoseconds[$middle - 1] + $nanoseconds[$middle]) / 2;
    $p95 = $nanoseconds[(int)ceil($samples * 0.95) - 1];
    return '<section><h2>Performance</h2><ul>'.
        '<li>Lookups measured: '.$samples.'</li>'.
        sprintf('<li>Total test time: %.3f s</li>', $elapsed / 1e9).
        sprintf('<li>Average lookup: %.3f ms</li>', array_sum($nanoseconds) / $samples / 1e6).
        sprintf('<li>Median lookup: %.3f ms</li>', $median / 1e6).
        sprintf('<li>95th-percentile lookup: %.3f ms</li>', $p95 / 1e6).
        sprintf('<li>Slowest lookup: %.3f ms</li>', $nanoseconds[$samples - 1] / 1e6).
        sprintf('<li>PHP request peak memory: %.2f MiB</li>', $peakMemory / 1048576).
        '</ul><p>Lookup timings include configuration loading, authentication, the database connection, and the query. '.
        'They exclude HTTP transport and browser rendering. Peak memory includes this PHP request and its test results; '.
        'database server memory is outside this measurement.</p></section>';
}

/***************************************************************************************************************************/
/**
This is a basic tester. It runs a list of long/lat pairs through the server, and compares the results, with the expected ones.

\returns: HTML of the results.
 */
function test_server() {
    $started = hrtime(true);
    /***********************************************************************************************************************/
    /**
    This is a simple generator for query strings, based on the given long/lat.

    \returns: the query string.
     */
    function _testllGen($inLng, ///< The longitude to use
                        $inLat  ///< The latitude to use
                        ) {
        include __CONFIG_FILE_;
        
        $queryString = isset($g_server_secret) ? "secret=$g_server_secret" : "";
        
        if (isset($inLng) && isset($inLat)) {
            if (!empty($queryString)) {
                $queryString .= '&';
            }
            $queryString .= "ll=$inLng,$inLat";
        }
        
        return $queryString;
    }
    
    /***********************************************************************************************************************/
    /**
    This runs the test, and returns the HTML for the results.
    
    \returns: the test result, as HTML.
     */
    function _callTestServer(   $inTitle,   ///< The title to display
                                $inLng,     ///< The longitude to use
                                $inLat,     ///< The latitude to use
                                $inResult,   ///< The expected result
                                array &$timings ///< Lookup duration samples in nanoseconds.
                            ) {
        global $count;
        global $failures;
        $count++;
        $queryString = _testllGen($inLng, $inLat);
        $lookupStarted = hrtime(true);
        $result = call_server($queryString, false);
        $duration = hrtime(true) - $lookupStarted;
        $timings[] = $duration;
        $style = $result == $inResult ? "pass" : "fail";
        $failAddendum = "";
        if ($result != $inResult) {
            $failure = ['title' => $inTitle, 'id' => "test-$count"];
            $failures[] = $failure;
            $failAddendum = ' <em class="fail">(Expected &quot;'.htmlspecialchars($inResult).'&quot;)</em>';
        }
        $ret = "<strong class=\"$style\" id=\"test-$count\">$inTitle</strong>";
        $ret .= "<ul><li>Longitude: $inLng</li><li>Latitude: $inLat</li>";
        $ret .= "<li>Result: &quot;$result&quot;$failAddendum</li>";
        $ret .= sprintf('<li>Lookup time: %.3f ms</li></ul>', $duration / 1e6);
        
        return $ret;
    }
    
    global $failures;
    global $count;
    $ret = '';
    $count = 0;
    $failures = [];
    $timings = [];
    
    include __DIR__.'/TestLocations.php';   // This establishes the $test_locations_param_array
    
    foreach ($test_locations_param_array as $test) {
        $orig_longitude = isset($test['params']['lng']) ? $test['params']['lng'] : NULL;
        $orig_latitude = isset($test['params']['lat']) ? $test['params']['lat'] : NULL;
        
        $ret .= _callTestServer($test['title'], $orig_longitude, $orig_latitude, $test['result'], $timings);
    }
    
    if (!empty($failures)) {
        $failureText = '<h2 class="fail">'.count($failures).' Test Failures!</h2><ul>';
        foreach ($failures as $failure) {
            $failureText .= '<li><a href="#'.$failure['id'].'">'.htmlspecialchars($failure['title']).'</a></li>';
        }
        $failureText .= "</ul>";
        
        $ret = $failureText.$ret;
    } else {
        $ret = '<h2 class="pass">All Tests ('.$count.') Passed!</h2>'.$ret;
    }
    
    return $ret.test_server_performance($timings, hrtime(true) - $started, memory_get_peak_usage(true));
};
