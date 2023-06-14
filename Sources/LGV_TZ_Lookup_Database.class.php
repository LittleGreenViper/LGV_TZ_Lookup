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
    \brief This handles database access, via [PDO](https://www.php.net/manual/en/book.pdo.php).
 */

declare(strict_types = 1);
define('LGV_DB_CATCHER', 1);
require_once __DIR__.'/LGV_TZ_Lookup_PDO.class.php';

/***************************************************************************************************************************/
/**
 */
class LGV_TZ_Lookup_Database {
    /***********************************************************************************************************************/
    /**
        This has our PDO connection to our database.
     */
    var $pdo_instance;

    /***********************************************************************************************************************/
    /**
        The constructor.
     */
	public function __construct(    $inDatabase,	        ///< database name (required)
	                                $inUser = NULL,		    ///< user, optional
                                    $inPassword = NULL,	    ///< password, optional
                                    $inDriver = 'mysql',	///< database server type (default is 'mysql')
                                    $inHost = '127.0.0.1',  ///< database server host (default is 127.0.0.1)
                                    $inPort = 3306 	        ///< database TCP port (default is 3306)
								) {
		$this->pdo_instance = new LGV_TZ_Lookup_PDO($inDatabase, $inUser, $inPassword, $inDriver, $inHost, $inPort);
    }
    
    /***********************************************************************************************************************/
    /**
        This is an array map callback. It takes a coordinate pair, in lng.lat form, and creates a space-delimites string from it.
        
        \returns: A string, with the two numbers. These are also rounded to five decimal places.
     */
    private static function _map_string_from_lng_lat($lng_lat   ///< This is a 2-element array of float, in the form of lng, lat.
                                                    ) {
        $lng = round($lng_lat[0] * 100000) / 100000;
        $lat = round($lng_lat[1] * 100000) / 100000;
        
        return sprintf("%g %g", $lng, $lat);
    }
    
    /***********************************************************************************************************************/
    /**
    This uses our PDO instance to save the entity as a table row.
     */
    public function store_entity(   $inEntity   ///< The entity to be saved into the database.
                                ) {
        $polygon_string = 'POLYGON(('.implode(',', array_map('LGV_TZ_Lookup_Database::_map_string_from_lng_lat', $inEntity->polygon)).'))';
        $sql = "INSERT INTO `timezones` (`tzname`, `east`, `west`, `north`, `south`, `polygon`) VALUES (?, ?, ?, ?, ?, PolygonFromText(?))";
        $params = [$inEntity->tzID, $inEntity->domainRect['east'], $inEntity->domainRect['west'], $inEntity->domainRect['north'], $inEntity->domainRect['south'], $polygon_string];
        $this->pdo_instance->preparedStatement($sql, $params);
    }
}