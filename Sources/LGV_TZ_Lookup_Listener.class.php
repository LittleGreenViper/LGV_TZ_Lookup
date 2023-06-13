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
 */
 
declare(strict_types = 1);

// Include the parser infrastructure.
require_once __DIR__.'/../vendor/autoload.php';

/***************************************************************************************************************************/
/**
    This class is basically a struct, containing one entity.

    The entity is usually one multipolygon segment, as defined in the main data file. It may represent the entirety of a timezone,
    but is more likely to be just one part.
    
    It has a timezone ID, a detailed list of polygon points, and a "domain rect," which is a simple rect that encompasses all of the points.
    We use the domain rect to quickly triage comparisons, and find out where they apply, before applying a more expensive algorithm to do a more
    exact polygon match.
 */
class LGV_TZ_Lookup_Entity {
    /***********************************************************************************************************************/
    /**
    This is [the standard TZ time zone designator](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones) that applies to this entity.
     */
    var $tzID;
    
    /***********************************************************************************************************************/
    /**
    This is the domain rectangle that contains the East, West, North, and South boundaries of the polygon.
     */
    var $domainRect;
    
    /***********************************************************************************************************************/
    /**
    This is the exact polygon for the time zone.
    It is a simple 2-element floating point array, with the longitude being [0], and the latitude being [1].
     */
    var $polygon;

    /***********************************************************************************************************************/
    /**
    The default constructor. We simply pass in the values to be held by this instance.
     */
    function __construct (
                            $inTZID,            ///< This is [the standard TZ time zone designator](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones) that applies to this entity.
                            $inDomainRect,      ///< This is the domain rectangle that contains the East, West, North, and South boundaries of the polygon.
                            $inCoordinateArray  ///< This is the exact polygon for the time zone.
                        ) {
        $this->tzID = $inTZID;
        $this->domainRect = $inDomainRect;
        $this->polygon = $inCoordinateArray;
    }
}

/***************************************************************************************************************************/
/**
    This class is a mainly static class that will process the main data file.

    It extends the streamer's GeoJSON listener class.
    
    It will not require any constructor data, and will set itself up.
 */
class LGV_TZ_Lookup_Listener extends \JsonStreamingParser\Listener\GeoJsonListener {
    /***********************************************************************************************************************/
    /**
        The constructor. No parameters are necessary.
     */
    public function __construct()
    {
        parent::__construct('LGV_TZ_Lookup_Listener::listener_action');
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
    private static function _process_entity(  $inEntity   ///< The new entity to be processed.
                                        ) {
        echo "<pre>$inEntity->tzID<br/><br/>".print_r($inEntity->domainRect, true)."</pre>";
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
        $domainRect = array_reduce($coords_array, 'LGV_TZ_Lookup_Listener::_update_coords', ['east' => -1000, 'west' => 1000, 'north' => -1000, 'south' => 1000]);
    
        return new LGV_TZ_Lookup_Entity($tzid, $domainRect, $coords_array);
    }
}