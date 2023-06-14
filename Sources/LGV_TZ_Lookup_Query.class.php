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
class LGV_TZ_Lookup_Query {
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
    }
    
    /***********************************************************************************************************************/
    /**
        This queries the database, and returns the TZ.
        
        \returns: A string, with the timezone. Empty, if none.
     */
    public function get_tz( $in_lng,    ///< The longitude 
                            $in_lat     ///< The latitude
                        ) {
        $tzIDs = self::$db_object->get_tz_ids($in_lng, $in_lat);
        
        $filtered_ids = array_filter($tzIDs, 'LGV_TZ_Lookup_Query::_filter_out_etc');
        
        if (1 == count($filtered_ids)) {
            return array_values($filtered_ids)[0]['tzname'];
        } else {
            $idMap = array_map('LGV_TZ_Lookup_Query::_convert_to_ids', $filtered_ids);
            $entities = self::$db_object->get_tz_entities($idMap);
        }
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
        This is a filter callback that excludes the "Etc" timezones.
        
        \returns: True, if it is not an "Etc/" timezone.
     */
    private static function _convert_to_ids($in_id  ///< The ID/TZ Name pair to convert.
                                            ) {
        return intval($in_id['id']);
    }
}