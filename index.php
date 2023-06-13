<?php

declare(strict_types=1);

set_time_limit(300);

require_once __DIR__.'/LGV_TZ_Lookup_Listener.class.php';

$stream = fopen('combined-with-oceans.json', 'r');

$listener = new LGV_TZ_Lookup_Listener();

try {
    $parser = new \JsonStreamingParser\Parser($stream, $listener);
    $parser->parse();
    fclose($stream);
} catch (Exception $e) {
    fclose($stream);
    throw $e;
}


