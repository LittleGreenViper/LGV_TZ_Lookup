<?php

declare(strict_types=1);

set_time_limit(300);

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/LGV_TZ_Lookup_Listener.php';

$stream = fopen('small-test.json', 'r');
function update_coords($current, $next) {
    $current['east'] = max($current['east'], $next[0]);
    $current['west'] = min($current['west'], $next[0]);
    $current['north'] = max($current['north'], $next[1]);
    $current['south'] = min($current['south'], $next[1]);
    return $current;
}

function extract_container_coords($coords_array, $previous = ['east' => -1000, 'west' => 1000, 'north' => -1000, 'south' => 1000]) {
    return array_reduce($coords_array, 'update_coords', $previous);
}
    
$listener = new LGV_TZ_Lookup_Listener(function ($item): void {
    $tzid = $item["properties"]["tzid"];
    $geometry = $item["geometry"]["coordinates"];
    
    $gather = ['east' => -1000, 'west' => 1000, 'north' => -1000, 'south' => 1000];
    echo "<pre>$tzid</pre>";
    if ("MultiPolygon" == $item["geometry"]["type"]) {
        echo "<pre>MultiPolygon</pre>";
        foreach ($geometry as $coords) {
            $gather = extract_container_coords($coords[0], $gather);
        }
    } else {
        echo "<pre>Polygon</pre>";
        $gather = extract_container_coords($geometry[0], $gather);
    }

    echo "<pre>".print_r($gather, true)."</pre>";
});


try {
    $parser = new \JsonStreamingParser\Parser($stream, $listener);
    $parser->parse();
    fclose($stream);
} catch (Exception $e) {
    fclose($stream);
    throw $e;
}


