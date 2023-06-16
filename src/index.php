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
    \brief This is the main entrypoint for the TZ Lookup Utility.
    
    It needs to import a configuration file, with database information.

    The configuration file needs to declare the following:
    
    $g_dbName = "<DATABASE NAME>";
    $g_dbUserName = "<DATABASE USERNAME>";
    $g_dbPassword = "<DATABASE USER PASSWORD>";
    $g_dbType = "<DATABASE TYPE>";
    $g_dbHost = "<DATABASE HOST>";
    $g_dbPort = "<DATABASE PORT>";
    
    If this is left blank, or omitted, then anyone can access the server (may not be good). Otherwise, they need to include "&secret=<SERVER SECRET>", when calling.
    
    $g_server_secret = "<SERVER SECRET>";
    
    The server can be told to re-initialize the database, but that should only be done via command line.
    
    Otherwise, a simple long/lat HTTP query will result in a string, containing the time zone that has that location.
*/

declare(strict_types = 1);

// This is the location of the config file. It should generally be located outside the HTTP directory. Change the path below, to wherever you put the file.
define("__CONFIG_FILE_", __DIR__.'/../../../../TZInfo/config.php');

// The class that we use to make queries.
require_once __DIR__.'/Sources/LGV_TZ_Lookup_Query.class.php';

// The class that we use to initialize the database.
require_once __DIR__.'/Sources/LGV_TZ_Lookup_Loader.class.php';

// The class that we use to run tests.
require_once __DIR__.'/LGV_TZ_Lookup_Test.php';

$queryString = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : (isset($_SERVER['argv'][1]) ? implode('&', array_slice($_SERVER['argv'], 1)) : '');

echo call_server($queryString, "cli" == php_sapi_name());

/***************************************************************************************************************************/
/**
    This is the "home function" of the server.
    
    \returns: The text of the run. Successful calls will result in a simple string. A successful load will result in 1, and other calls may return HTML.
*/
function call_server(   $inQuery,   ///< This is an ampersand-concatenated list of parameters, either from the query, or from the arguments.
                        $inIsCLI    ///< This is true, if we are calling this from the command-line.
                    ) {
    include __CONFIG_FILE_;
    $ret = "";
    
    if (!empty($inQuery)) {
        $queries = [];
        $path = $inIsCLI ? dirname($_SERVER['argv'][0]) : "";
        if (!empty($inQuery)) {
            $queryArray = explode('&', $inQuery);
    
            foreach ($queryArray as $param) {
                // Now, see if we have a bunch of parameters.
                $key = trim($param);
                $value = NULL;

                $parts = explode('=', $param, 2);
        
                if (1 < count($parts)) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                }

                if (!empty($key)) {
                    if (empty($value)) {
                        $value = true;
                    }

                    $queries[$key] = $value;
                }
            }
        }
        if (!empty($queries)) {
            if (!isset($g_server_secret) || empty($g_server_secret) || (isset($queries['secret']) && ($g_server_secret == $queries['secret']))) {
                $db_object = new LGV_TZ_Lookup_Database($g_dbName, $g_dbUserName, $g_dbPassword, $g_dbType, $g_dbHost, $g_dbPort);
                if (isset($queries['test'])) {  // Test trumps all
                    $ret = '<html><head><style>.pass{color:green}.fail{color:red}</style></head><body>'.test_server().'</body></html>';
                } elseif (isset($queries['load'])) {
                    if ($inIsCLI) {
                        set_time_limit(180);    // It can take a couple of minutes to load.
                        $stream = fopen("$path/combined-with-oceans.json", 'r');

                        try {
                            $listener = new LGV_TZ_Lookup_Loader($db_object);
                            $parser = new \JsonStreamingParser\Parser($stream, $listener);
                            $parser->parse();
                            fclose($stream);
                            $ret = '1';
                        } catch (Exception $e) {
                            fclose($stream);
                            $ret = '0';
                        }
                    } else {
                        $ret = 'Only Available Through CLI';
                        header('HTTP/1.1 403 Not Authorized');
                    }
                } elseif (!empty($queries['ll'])) {
                    $long_lat = explode(',', $queries['ll']);
                    if (2 == count($long_lat) && isset($long_lat[0]) && isset($long_lat[1])) {
                        $lng = floatval($long_lat[0]);
                        $lat = floatval($long_lat[1]);
                
                        // Adjust for overflow.
                        while (-180 > $lng) { $lng += 360; }
                        while (180 < $lng) { $lng -= 360; }
                        while (-90 > $lat) { $lat += 180; }
                        while (90 < $lat) { $lat -= 180; }
                    
                        $lookup = new LGV_TZ_Lookup_Query($db_object);
                        $ret = $lookup->get_tz($lng, $lat);
                        if (empty($ret)) {
                            return $ret;
                        }
                    } else {
                        $ret = 'Malformed Query';
                        header('HTTP/1.1 400 Malformed Query');
                    }
                }
            } elseif (isset($g_server_secret) && (!isset($queries['secret']) || ($g_server_secret != $queries['secret']))) {
                $ret = 'Ah - Ah - Aaaah! You didn\'t say the magic word!';
                if ("cli" != php_sapi_name()) {
                    header('HTTP/1.1 403 Not Authorized');
                }
            } else {
                $ret = 'Illegal Query';
                if ("cli" != php_sapi_name()) {
                    header('HTTP/1.1 400 Illegal Query');
                }
            }
        } else {
            if (!$inIsCLI) {
                $ret = 'Illegal Query';
                header('HTTP/1.1 400 Illegal Query');
            }
        }
    }
    
    if (empty($ret)) {
        if ($inIsCLI) {
            $secret_note = isset($g_server_secret) && !empty($g_server_secret) ? "\n\n\tNOTE: This server has a \"secret\" set\n\n\tThis means that you will need to provide an additional string in each call. Please contact the administrator of this server to obtain the secret." : "";
            $ret = "Usage: \$> php ".__FILE__." load\n\n\tInitializes the database from the GeoJSON file.\n\tIt will return 1, if successful, 0, if not. It may take some time.$secret_note\n";
        } else {
            $s = (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS'])) ? "s" : "";
            $secret_note = isset($g_server_secret) && !empty($g_server_secret) ? "<em><h4>NOTE: This server has a \"secret\" set</h4><p>This means that you will need to provide an additional string in each call. Please contact the administrator of this server to obtain the secret.</p></em>" : "";
            $ret = "<html><head><title>Using LGV_TZ_Lookup</title></head><body><pre>Usage: http$s://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."?ll=<em style='color:gray'>&lt;LONG&gt;,&lt;LAT&gt;</em>\n\n\tThis is the standard query. Provide the requested long/lat, as comma-delimited floating-point numbers, between -180 and 180.\n\tThe response will be <a href='https://en.wikipedia.org/wiki/List_of_tz_database_time_zones'>the TZ name</a> of the timezone that contains that point. It will be a simple string.</pre>$secret_note</body></html>";
        }
    }
    
    return $ret;
}