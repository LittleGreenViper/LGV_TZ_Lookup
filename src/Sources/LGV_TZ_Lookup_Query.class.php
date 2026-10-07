<?php
/***************************************************************************************************************************/
/**
    © Copyright 2023-2026, [Little Green Viper Software Development LLC](https://littlegreenviper.com)
    
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

        $named = [];
        $ocean = [];
        foreach ($tzIDs as $candidate) {
            if (str_starts_with($candidate['tzname'], 'Etc/')) {
                $ocean[] = $candidate;
            } else {
                $named[] = $candidate;
            }
        }

        // Preserve the existing single-candidate shortcut and named-zone precedence.
        if (1 == count($named)) {
            return $named[0]['tzname'];
        }
        $timezone = $this->_find_in_polygons($named, $in_lng, $in_lat);
        if ('' !== $timezone) {
            return $timezone;
        }
        if (1 == count($tzIDs)) {
            return $tzIDs[0]['tzname'];
        }

        // Named polygons have already failed; only fetch the ocean polygons now.
        return $this->_find_in_polygons($ocean, $in_lng, $in_lat);
    }

    /***********************************************************************************************************************/
    /**
        Fetch compact polygons and stop at the first match, without expanding all candidates into PHP point arrays.
     */
    private function _find_in_polygons($candidates, $longitude, $latitude) {
        if (empty($candidates)) {
            return '';
        }
        $ids = array_column($candidates, 'id');
        foreach ($this->db_object->get_tz_polygons($ids) as $entity) {
            if (self::_wn_PackedPoly($longitude, $latitude, $entity['polygon'])) {
                return $entity['tzname'];
            }
            unset($entity);
        }
        return '';
    }

    /***********************************************************************************************************************/
    /**
        The winding-number algorithm courtesy of San Zhujun (https://gist.github.com/zhujunsan/81d6a2f05d590f618a5ad36f25666fc2),
        operating on the existing packed native-double format with the same edge/vertex comparisons.
        Decode 1,024 points at a time: per-point unpack calls are expensive, while unpacking an entire large polygon
        creates a large PHP hash table. Carry the preceding point across blocks, including the closing edge.
     */
    private static function _wn_PackedPoly($longitude, $latitude, $polygon) {
        $bytes = strlen($polygon);
        if ($bytes < 32) {
            return false;
        }
        $last = unpack('dx/dy', $polygon, $bytes - 16);
        $previousX = $last['x'];
        $previousY = $last['y'];
        $winding = 0;
        for ($offset = 0; $offset < $bytes; $offset += 16384) {
            $count = min(2048, intdiv($bytes - $offset, 8));
            $coordinates = unpack('d'.$count, $polygon, $offset);
            for ($i = 1; $i < $count; $i += 2) {
                $x = $coordinates[$i];
                $y = $coordinates[$i + 1];
                if ($previousX <= $longitude) {
                    if ($x > $longitude &&
                        ($y - $previousY) * ($longitude - $previousX) - ($latitude - $previousY) * ($x - $previousX) > 0) {
                        ++$winding;
                    }
                } elseif ($x <= $longitude &&
                    ($y - $previousY) * ($longitude - $previousX) - ($latitude - $previousY) * ($x - $previousX) < 0) {
                    --$winding;
                }
                $previousX = $x;
                $previousY = $y;
            }
        }
        return 0 != $winding;
    }
}
