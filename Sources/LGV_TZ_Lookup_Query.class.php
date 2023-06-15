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

require_once __DIR__.'/LGV_TZ_Lookup_Database.class.php';

/***************************************************************************************************************************/
/**
    This class is a lookup class.
    
    It queries the database, and returns the timezone that corresponds to the provided longitude, latitude pair.
 */
class LGV_TZ_Lookup_Query {
    /***********************************************************************************************************************/
    /**
        The database object we're accessing.
     */
    var $db_object;
    
    /***********************************************************************************************************************/
    /**
        The constructor.
     */
    public function __construct($inDBObject ///< An initialized database instance for this handler.
                                ) {
        $this->db_object = $inDBObject;
    }
    
    /***********************************************************************************************************************/
    /**
        This queries the database, and returns the TZ.
        
        \returns: A string, with the timezone. Empty, if none.
     */
    public function get_tz( $in_lng,    ///< The longitude 
                            $in_lat     ///< The latitude
                        ) {
        // This does a fast lookup, using the domain rect (the "blunt instrument" rect that we created, when we stored the polygon).
        $tzIDs = $this->db_object->get_tz_ids($in_lng, $in_lat);
        
        // We filter out the "Etc" timezones, crammed at the end.
        $filtered_ids = array_filter($tzIDs, 'LGV_TZ_Lookup_Query::_filter_out_etc');
        
        // If we only have one, then w00t! We send that back.
        if (1 == count($filtered_ids)) {
            return array_values($filtered_ids)[0]['tzname'];
        } else {    // Otherwise, we have to look into each polygon, in a bit more detail, and return the first match.
            $idMap = array_map('LGV_TZ_Lookup_Query::_convert_to_ids', $filtered_ids);
            $entities = $this->db_object->get_tz_entities($idMap);
            
            foreach($entities as $entity) {
                if(self::_wn_PnPoly([$in_lng, $in_lat], $entity['polygon'])) {
                    return $entity['tzname'];
                }
            }
        }
        
        return "";
    }
    
    /***********************************************************************************************************************/
    /**
        This function is courtesy of San Zhujun, via [this gist](https://gist.github.com/zhujunsan/81d6a2f05d590f618a5ad36f25666fc2).
        
        \returns: -1 if to the right, 1, if to the left, and 0, if on the vertex.
     */
    private static function _isLeft($polygon_point_0,   ///< The first vertex endpoint.
                                    $polygon_point_1,   ///< The second vertex endpoint.
                                    $test_point         ///< The point to test against the vertex.
                                    ) {
        return (($polygon_point_1[1] - $polygon_point_0[1]) * ($test_point[0] - $polygon_point_0[0]) - ($test_point[1] - $polygon_point_0[1]) * ($polygon_point_1[0] - $polygon_point_0[0]));
    }

    /***********************************************************************************************************************/
    /**
        This function is courtesy of San Zhujun, via [this gist](https://gist.github.com/zhujunsan/81d6a2f05d590f618a5ad36f25666fc2).
        
        It's a basic ["winding number" algortithm](https://en.wikipedia.org/wiki/Winding_number), for testing whether or not a point is inside a polygon.
        
        I have modified it to use arrays of long/lat, as opposed to the Point class he defined, in his example.
        
        \returns: True, if the point is inside the polygon.
     */
    private static function _wn_PnPoly( $point,     ///< The point we are testing.
                                        $polygon    ///< The polygon we are testing against.
                                        ) {
        $wn = 0;
        $n = count($polygon);

                                                                                            // loop through all edges of the polygon
        for ($i = 0; $i < $n; $i++) {                                                       // edge from polygon[i] to  polygon[i+1]
            if ($polygon[$i][0] <= $point[0]) {                                             // start y <= point[0]
                if ($polygon[($i + 1) % $n][0] > $point[0])                                 // an upward crossing
                    if (self::_isLeft($polygon[$i], $polygon[($i + 1) % $n], $point) > 0)   // point left of  edge
                        ++$wn;                                                              // have  a valid up intersect
            } else {                                                                        // start y > point[0] (no test needed)
                if ($polygon[($i + 1) % $n][0] <= $point[0])                                // a downward crossing
                    if (self::_isLeft($polygon[$i], $polygon[($i + 1) % $n], $point) < 0)   // point right of  edge
                        --$wn;                                                              // have  a valid down intersect
            }
        }
        
        return 0 != $wn;
    }
    
    /***********************************************************************************************************************/
    /**
        This is a filter callback that excludes the "Etc" timezones.
        
        \returns: True, if it is not an "Etc/" timezone.
     */
    private static function _filter_out_etc($in_id  ///< The ID/TZ Name pair to check.
                                            ) {
        return !str_starts_with($in_id['tzname'], "Etc/");
    }
    
    /***********************************************************************************************************************/
    /**
        This is a map callback, to extract the ID numbers.
        
        \returns: The ID integer of the element.
     */
    private static function _convert_to_ids($in_id  ///< The ID/TZ Name pair to convert.
                                            ) {
        return intval($in_id['id']);
    }
}