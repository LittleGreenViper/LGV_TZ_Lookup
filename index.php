<?php
// This is only a testing index file, to be replaced upon release.

declare(strict_types = 1);

set_time_limit(180);

require_once __DIR__.'/Sources/LGV_TZ_Lookup_Loader.class.php';
require_once __DIR__.'/../../../TZInfo/config.php';

$queryString = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : NULL;
if (!empty($queryString)) {
    $queries = [];
    
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

    if (!empty($queries)) {
        $db_object = new LGV_TZ_Lookup_Database($g_dbName, $g_dbUserName, $g_dbPassword);
        if (isset($queries['load'])) {
            $stream = fopen('combined-with-oceans.json', 'r');

            try {
                $listener = new LGV_TZ_Lookup_Loader($db_object);
                $parser = new \JsonStreamingParser\Parser($stream, $listener);
                $parser->parse();
                fclose($stream);
                echo('Load Successful');
                header('HTTP/1.1 200 Load Successful');
            } catch (Exception $e) {
                fclose($stream);
                echo('Load Error');
                header('HTTP/1.1 500 Load Error');
            }
        } elseif (!empty($queries['ll'])) {
            $long_lat = explode(',', $queries['ll']);
            if (2 == count($long_lat) && !empty($long_lat[0]) && !empty($long_lat[1])) {
                $lng = floatval($long_lat[0]);
                $lat = floatval($long_lat[1]);
            } else {
                echo('Malformed Query');
                header('HTTP/1.1 400 Malformed Query');
            }
        }
    } else {
        echo('Illegal Query');
        header('HTTP/1.1 400 Illegal Query');
    }
} else {
    echo('Empty Query');
    header('HTTP/1.1 400 Empty Query');
}