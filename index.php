<?php

declare(strict_types = 1);

set_time_limit(180);

require_once __DIR__.'/Sources/LGV_TZ_Lookup_Loader.class.php';
require_once __DIR__.'/../../../TZInfo/config.php';

$stream = fopen('combined-with-oceans.json', 'r');

try {
    $db_object = new LGV_TZ_Lookup_Database($g_dbName, $g_dbUserName, $g_dbPassword);
    $listener = new LGV_TZ_Lookup_Loader($db_object);
    $parser = new \JsonStreamingParser\Parser($stream, $listener);
    $parser->parse();
    fclose($stream);
} catch (Exception $e) {
    fclose($stream);
    throw $e;
}
