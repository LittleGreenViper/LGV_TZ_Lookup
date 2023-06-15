<?php
/***************************************************************************************************************************/
/**
    © Copyright 2023, [Little Green Viper Software Development LLC](https://littlegreenviper.com)
    
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
/**
This is a basic tester. It runs a list of long/lat pairs through the server, and compares the results, with the expected ones.

\returns: HTML of the results.
 */
function test_server() {
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
                                $inResult   ///< The expected result
                            ) {
        global $count;
        $count++;
        $queryString = _testllGen($inLng, $inLat);
        $result = call_server($queryString, false);
        $style = $result == $inResult ? "pass" : "fail";
        $ret = "<strong class=\"$style\" id=\"test-$count\">$inTitle</strong>";
        $ret .= "<ul><li>Longitude: $inLng</li><li>Latitude: $inLat</li>";
        $ret .= "<li>Result: &quot;$result&quot;</li></ul>";
        
        return $ret;
    }
    
    global $count;
    $ret = '';
    $count = 0;
    
    include __DIR__.'/TestLocations.php';   // This establishes the $test_locations_param_array
    
    foreach ($test_locations_param_array as $test) {
        $orig_longitude = isset($test['params']['lng']) ? $test['params']['lng'] : NULL;
        $orig_latitude = isset($test['params']['lat']) ? $test['params']['lat'] : NULL;
        
        $ret .= _callTestServer($test['title'], $orig_longitude, $orig_latitude, $test['result']);
    }

    return $ret;
};