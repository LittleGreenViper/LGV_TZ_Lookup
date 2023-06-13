<?php

declare(strict_types=1);

require_once __DIR__.'/vendor/autoload.php';

class LGV_TZ_Lookup_Entity {
    var $tzID;
    
    var $domainRect;
    
    var $polygon;

    public function __construct (
                                $inTZID,
                                $inDomainRect,
                                $inCoordinateArray
                                ) {
        $this->tzID = $inTZID;
        $this->domainRect = $inDomainRect;
        $this->polygon = $inCoordinateArray;
    }
}

class LGV_TZ_Lookup_Listener extends \JsonStreamingParser\Listener\GeoJsonListener {
    public static function update_coords($current, $next) {
        $current['east'] = max($current['east'], $next[0]);
        $current['west'] = min($current['west'], $next[0]);
        $current['north'] = max($current['north'], $next[1]);
        $current['south'] = min($current['south'], $next[1]);
        return $current;
    }

    public static function extract_entity($tzid, $coords_array ) {
        $domainRect = array_reduce($coords_array, 'LGV_TZ_Lookup_Listener::update_coords', ['east' => -1000, 'west' => 1000, 'north' => -1000, 'south' => 1000]);
    
        return new LGV_TZ_Lookup_Entity($tzid, $domainRect, $coords_array);
    }

    public static function listener_action($inItem) {
        $tzid = $inItem["properties"]["tzid"];
        $geometry = $inItem["geometry"]["coordinates"];
        if ("MultiPolygon" == $inItem["geometry"]["type"]) {
            foreach ($geometry as $coords) {
                self::process_entity(self::extract_entity($tzid, $coords[0]));
            }
        } else {
            self::process_entity(self::extract_entity($tzid, $geometry[0]));
        }
    }
    
    public static function process_entity($inEntity) {
        echo "<pre>$inEntity->tzID<br/><br/>".print_r($inEntity->domainRect, true)."</pre>";
    }

    public function __construct()
    {
        parent::__construct('LGV_TZ_Lookup_Listener::listener_action');
    }
}