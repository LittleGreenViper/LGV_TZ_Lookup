<?php
// This is only a testing index file, to be replaced upon release.

/**
    The configuration file needs to declare the following:
    
    $g_dbName = "<DATABASE NAME>";
    $g_dbUserName = "<DATABASE USERNAME>";
    $g_dbPassword = "<DATABASE USER PASSWORD>";
    
    If this is left blank, or omitted, then anyone can access the server (may not be good). Otherwise, they need to include "&secret=<SERVER SECRET>", when calling.
    
    $g_server_secret = "<SERVER SECRET>";

*/

declare(strict_types = 1);

set_time_limit(180);

require_once __DIR__.'/Sources/LGV_TZ_Lookup_Query.class.php';
require_once __DIR__.'/Sources/LGV_TZ_Lookup_Loader.class.php';
require_once __DIR__.'/../../../TZInfo/config.php';

$queryString = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : NULL;

if (!empty($queryString) || ("cli" == php_sapi_name())) {
    $queries = [];
    $path = "";
    if (!empty($queryString)) {
        $queryArray = explode('&', $queryString);
    
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
    } elseif (0 < $_SERVER['argc']) {
        $path = dirname($_SERVER['argv'][0]);
    }

    if (!empty($queries)) {
        if (!isset($g_server_secret) || empty($g_server_secret) || (isset($queries['secret']) && ($g_server_secret == $queries['secret']))) {
            $db_object = new LGV_TZ_Lookup_Database($g_dbName, $g_dbUserName, $g_dbPassword);
            if (isset($queries['load'])) {
                if ("cli" == php_sapi_name()) {
                    $stream = fopen("$path/combined-with-oceans.json", 'r');

                    try {
                        $listener = new LGV_TZ_Lookup_Loader($db_object);
                        $parser = new \JsonStreamingParser\Parser($stream, $listener);
                        $parser->parse();
                        fclose($stream);
                        echo('1');
                    } catch (Exception $e) {
                        fclose($stream);
                        echo('0');
                    }
                } else {
                    echo('Only Available Through CLI');
                    header('HTTP/1.1 403 Not Authorized');
                }
            } elseif (!empty($queries['ll'])) {
                $long_lat = explode(',', $queries['ll']);
                if (2 == count($long_lat) && !empty($long_lat[0]) && !empty($long_lat[1])) {
                    $lng = floatval($long_lat[0]);
                    $lat = floatval($long_lat[1]);
                
                    $lookup = new LGV_TZ_Lookup_Query($db_object);
                    echo($lookup->get_tz($lng, $lat));
                } else {
                    echo('Malformed Query');
                    header('HTTP/1.1 400 Malformed Query');
                }
            }
        } elseif (isset($g_server_secret) && (!isset($queries['secret']) || ($g_server_secret != $queries['secret']))) {
            echo('Ah - Ah - Aaaah! You didn\'t say the magic word!');
            if ("cli" != php_sapi_name()) {
                header('HTTP/1.1 403 Not Authorized');
            }
        } else {
            echo('Illegal Query');
            if ("cli" != php_sapi_name()) {
                header('HTTP/1.1 400 Illegal Query');
            }
        }
    } else {
        if ("cli" == php_sapi_name()) {
            echo("Usage: \$> php ".__FILE__." load\n\n\tInitializes the database from the GeoJSON file.\n\tIt will return 1, if successful, 0, if not. It may take some time.\n");
        } else {
            echo('Illegal Query');
            header('HTTP/1.1 400 Illegal Query');
        }
    }
} else {
    $s = (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS'])) ? "s" : "";
    echo("<pre>Usage: http$s://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."?ll=<em style='color:gray'>&lt;LONG&gt;,&lt;LAT&gt;</em>\n\n\tThis is the standard query. Provide the requested long/lat, as comma-delimited floating-point numbers, between -180 and 180.\n\tThe response will be <a href='https://en.wikipedia.org/wiki/List_of_tz_database_time_zones'>the TZ name</a> of the timezone that contains that point. It will be a simple string.</pre>");
}