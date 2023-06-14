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
    \brief This is a modified GeoJSON listener for [the streaming JSON parser](https://github.com/salsify/jsonstreamingparser).
           It will initialize and load the database, from the GeoJSON file.
 */
 
declare(strict_types = 1);

// Include the parser infrastructure.
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/LGV_TZ_Lookup_Database.class.php';
require_once __DIR__.'/LGV_TZ_Lookup_Entity.class.php';

/***************************************************************************************************************************/
/**
    This class is a mainly static class that will process the main data file, and initialize the database.

    It extends the streamer's GeoJSON listener class.
    
    It will not require any constructor data, and will set itself up.
 */
class LGV_TZ_Lookup_Loader extends \JsonStreamingParser\Listener\GeoJsonListener {
    /***********************************************************************************************************************/
    /**
        The database object we're accessing..
     */
    static $db_object;
    
    /***********************************************************************************************************************/
    /**
        The constructor.
     */
    public function __construct($inDBObject ///< An initialized database instance for this handler.
                                ) {
        self::$db_object = $inDBObject;
        
        parent::__construct('LGV_TZ_Lookup_Loader::listener_action');
        
        $inDBObject->reset_database();
    }

    /***********************************************************************************************************************/
    /**
        This is the action that is called by the JSON streamer.
     */
    public static function listener_action( $inItem ///< The element of the JSON file being parsed. This is one geographic area for the time zone.
                                        ) {
        $tzid = $inItem["properties"]["tzid"];
        $geometry = $inItem["geometry"]["coordinates"];
        if ("MultiPolygon" == $inItem["geometry"]["type"]) {
            foreach ($geometry as $coords) {
                self::_process_entity(self::_extract_entity($tzid, $coords[0]));
            }
        } elseif ("Polygon" == $inItem["geometry"]["type"]) {
            self::_process_entity(self::_extract_entity($tzid, $geometry[0]));
        }
    }
    
    /***********************************************************************************************************************/
    /**
        This processes one new entity, after we have parsed it.
     */
    private static function _process_entity($inEntity   ///< The new entity to be processed.
                                        ) {
        self::$db_object->store_entity($inEntity);
    }

    /***********************************************************************************************************************/
    /**
        This function simply compares the given long ([0], and lat ([1]) against the current values, and extends an edge, if necessary.
        
        This is designed to be a reduce handler, with the current value, and the new value, passed in, returning a new current value.
        
        \returns a new domain rect, as an associative array.
     */
    private static function _update_coords( $current,   ///< This is the current domain rect. It contains the maximums for East, West, North, and South, in an associative array.
                                            $next       ///< This is the next value of the long lat. It is a simple 2-element floating point array, with [0] being the longitude, and [1] being the latitude.
                                        ) {
        $current['east'] = max($current['east'], $next[0]);
        $current['west'] = min($current['west'], $next[0]);
        $current['north'] = max($current['north'], $next[1]);
        $current['south'] = min($current['south'], $next[1]);
        return $current;
    }

    /***********************************************************************************************************************/
    /**
        This function generates a new entity, based on the given time zone ID, and the polygon array.
    
        \returns: A new instance of LGV_TZ_Lookup_Entity.
     */
    private static function _extract_entity($tzid,          ///< This is [the standard TZ time zone designator](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones) that applies to this entity.
                                            $coords_array   ///< This is the exact polygon for this entitye.
                                        ) {
        $domainRect = array_reduce($coords_array, 'LGV_TZ_Lookup_Loader::_update_coords', ['east' => -1000, 'west' => 1000, 'north' => -1000, 'south' => 1000]);
    
        return new LGV_TZ_Lookup_Entity($tzid, $domainRect, $coords_array);
    }
}