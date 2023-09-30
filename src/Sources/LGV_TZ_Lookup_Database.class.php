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
require_once __DIR__.'/LGV_TZ_Lookup_Entity.class.php';

/***************************************************************************************************************************/
/**
    This class manages interactions between the server and the database.
 */
class LGV_TZ_Lookup_Database {
    /***********************************************************************************************************************/
    /**
        The SQL that we use to initialize the database.
     */
    private static $_init_sql;
    
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
	                                $inUser = NULL,		    ///< database user, optional
                                    $inPassword = NULL,	    ///< database password, optional
                                    $inDriver = 'mysql',	///< database server type (optional, default is 'mysql')
                                    $inHost = '127.0.0.1',  ///< database server host (optional, default is 127.0.0.1)
                                    $inPort = 3306 	        ///< database TCP port (optional, default is 3306)
								) {
		$this->pdo_instance = new LGV_TZ_Lookup_PDO($inDatabase, $inUser, $inPassword, $inDriver, $inHost, $inPort);
        ini_set('max_execution_time', 1200);
		set_time_limit(1200);
		
		// This is the SQL that we use to create the table. Currently, it is MySQL-only, but should be changeable.
		self::$_init_sql = 'DROP TABLE IF EXISTS timezones;
                            CREATE TABLE timezones (
                              id bigint(20) NOT NULL,
                              tzname varchar(255) NOT NULL,
                              east float NOT NULL,
                              west float NOT NULL,
                              north float NOT NULL,
                              south float NOT NULL,
                              polygon longblob NOT NULL
                            );

                            ALTER TABLE timezones
                              ADD PRIMARY KEY (id),
                              ADD KEY tzname (tzname),
                              ADD KEY east (east),
                              ADD KEY west (west),
                              ADD KEY north (north),
                              ADD KEY south (south);

                            ALTER TABLE timezones
                              MODIFY id bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;';
    }
    
    /***********************************************************************************************************************/
    /**
        This is a sort callback that sorts, so the smaller domain gets put first.
        
        \returns: True, if $in_A < $inB
     */
    private static function _sort_by_domainSize($in_A,  ///< The first entity
                                                $in_B   ///< The second entity
                                                ) {
        $ret = false;
        
        if (isset($in_A["east"]) &&
            isset($in_A["west"]) &&
            isset($in_A["north"]) &&
            isset($in_A["south"]) &&
            isset($in_B["east"]) &&
            isset($in_B["west"]) &&
            isset($in_B["north"]) &&
            isset($in_B["south"])) {
                $areaA = abs(floatval($in_A["east"] - $in_A["west"])) * abs(floatval($in_A["south"] - $in_A["north"]));
                $areaB = abs(floatval($in_B["east"] - $in_B["west"])) * abs(floatval($in_B["south"] - $in_B["north"]));
                return ($areaA < $areaB) ? -1 : (($areaA < $areaB) ? 1 : 0);
            }
            
        return $ret;
    }
    
    /***********************************************************************************************************************/
    /**
        This is a simple "brute force" array flattener. It rounds the values to six significant digits, as well.
     */
    static function _array_flatten( $inArray    ///< The multi-dimensional array to be squished.
                                    ) {
        $resulting_array = array();
        array_walk_recursive($inArray, function($inValue) use (&$resulting_array) { array_push($resulting_array, round($inValue, 6)); });
        return $resulting_array;
    }

    /***********************************************************************************************************************/
    /**
        This uses our PDO instance to save the entity as a table row.
     */
    public function store_entity(   $inEntity   ///< The entity to be saved into the database.
                                ) {
        $polygon_nested_array = array($inEntity->polygon);
        $polygon_array = self::_array_flatten($polygon_nested_array);
        $polygon = implode(array_map(function($inValue) { return pack('d*', $inValue); }, $polygon_array));
        $sql = "INSERT INTO timezones (tzname, east, west, north, south, polygon) VALUES (?, ?, ?, ?, ?, ?)";
        $params = [$inEntity->tzID, $inEntity->domainRect['east'], $inEntity->domainRect['west'], $inEntity->domainRect['north'], $inEntity->domainRect['south'], $polygon];
        $this->pdo_instance->preparedStatement($sql, $params);
    }
    
    /***********************************************************************************************************************/
    /**
        This queries the database, and returns the TZ IDs.
        
        \returns: An array of integers, which are the database IDs of table rows that have the long/lat in their domainRects.
     */
    public function get_tz_ids( $in_lng,    ///< The longitude 
                                $in_lat     ///< The latitude
                            ) {
        $sql = "SELECT id, tzname, east, west, north, south FROM timezones WHERE east>=? AND west<=? AND north>=? AND south<=?";
        
        $params = [$in_lng, $in_lng, $in_lat, $in_lat];
        $ret = $this->pdo_instance->preparedStatement($sql, $params, true);
        if (1 < count($ret)) {
            usort($ret, 'LGV_TZ_Lookup_Database::_sort_by_domainSize');
        }
        return $ret;
    }
    
    /***********************************************************************************************************************/
    /**
        \returns: An array of simple arrays ([tzname, polygon]), which are the database entities of table rows that correspond to the IDs passed in.
     */
    public function get_tz_entities( $in_id_list ///< A list of IDs for the rows we want to check.
                            ) {
        $sql = "SELECT tzname, polygon FROM timezones WHERE";
        $idSQL = "";
        $params = [];
        
        foreach ($in_id_list as $id) {
            if (!empty($idSQL)) {
                $idSQL .= " OR";
            }
            
            $idSQL .= " id=?";
            $params[] = $id;
        }
        
        $entities = [];
        
        if (!empty($params)) {
            $ret = $this->pdo_instance->preparedStatement($sql.$idSQL, $params, true);
            
            if (!empty($ret)) {
                foreach($ret as $row) {
                    $entity = ['tzname' => $row['tzname'], 'polygon' => unpack('d*', $row['polygon'])];
                    $entity['polygon'] = array_chunk(array_values($entity['polygon']), 2);
                    $entities[] = $entity;
                }
            }
        }
        
        return $entities;
    }
    
    /***********************************************************************************************************************/
    /**
        This clears the database.
     */
    public function reset_database() {
        $this->pdo_instance->preparedStatement(self::$_init_sql);
    }
}