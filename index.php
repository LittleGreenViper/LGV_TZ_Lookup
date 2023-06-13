<?php

declare(strict_types = 1);

set_time_limit(180);

require_once __DIR__.'/LGV_TZ_Lookup_Listener.class.php';

$stream = fopen('combined-with-oceans.json', 'r');

$listener = new LGV_TZ_Lookup_Listener();

try {
    $parser = new \JsonStreamingParser\Parser($stream, $listener);
    $time = microtime(true);
    $parser->parse();
    fclose($stream);
    
    
    $seconds = round((microtime(true) - $time) * 100) / 100;
    
    echo "This took $seconds seconds to run.";
    
} catch (Exception $e) {
    fclose($stream);
    throw $e;
}


