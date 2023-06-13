<?php

declare(strict_types = 1);

set_time_limit(180);

require_once __DIR__.'/Sources/LGV_TZ_Lookup_Listener.class.php';

$stream = fopen('combined-with-oceans.json', 'r');

try {
    require_once __DIR__.'/../../../TZInfo/config.php';
    $db_object = new LGV_TZ_Lookup_Database($g_dbName, $g_dbUserName, $g_dbPassword);
    $listener = new LGV_TZ_Lookup_Listener($db_object);
    $parser = new \JsonStreamingParser\Parser($stream, $listener);
    $time = microtime(true);
    $parser->parse();
    fclose($stream);
    
    $seconds = intval(round(microtime(true) - $time));
    $minutes = intval($seconds / 60);
    $seconds = intval($seconds - ($minutes * 60));
    
    echo "This took $minutes minutes and $seconds seconds to run.";
    
} catch (Exception $e) {
    fclose($stream);
    throw $e;
}
