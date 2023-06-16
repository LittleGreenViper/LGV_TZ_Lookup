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
		
		// This is the SQL that we use to create the table. Currently, it is MySQL-only, but should be changeable.
		self::$_init_sql = 'DROP TABLE IF EXISTS timezones;
                            CREATE TABLE timezones (
                              id bigint(20) NOT NULL,
                              tzname varchar(255) NOT NULL,
                              east float NOT NULL,
                              west float NOT NULL,
                              north float NOT NULL,
                              south float NOT NULL,
                              polygon polygon NOT NULL
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
        This is an array map callback. It takes a coordinate pair, in lng, lat form, and creates a space-delimited string from it.
        
        \returns: A string, with the two numbers. These are also rounded to five decimal places.
     */
    private static function _map_string_from_lng_lat($inLng_lat   ///< This is a 2-element array of float, in the form of lng, lat.
                                                    ) {
        $lng = round($inLng_lat[0] * 100000) / 100000;
        $lat = round($inLng_lat[1] * 100000) / 100000;
        
        return sprintf("%g %g", $lng, $lat);
    }
    
    /***********************************************************************************************************************/
    /**
        This uses our PDO instance to save the entity as a table row.
     */
    public function store_entity(   $inEntity   ///< The entity to be saved into the database.
                                ) {
        $polygon_string = 'POLYGON(('.implode(',', array_map('LGV_TZ_Lookup_Database::_map_string_from_lng_lat', $inEntity->polygon)).'))';
        $sql = "INSERT INTO timezones (tzname, east, west, north, south, polygon) VALUES (?, ?, ?, ?, ?, PolygonFromText(?))";
        $params = [$inEntity->tzID, $inEntity->domainRect['east'], $inEntity->domainRect['west'], $inEntity->domainRect['north'], $inEntity->domainRect['south'], $polygon_string];
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
        $sql = "SELECT id, tzname FROM timezones WHERE east>=? AND west<=? AND north>=? AND south<=?";
        
        $params = [$in_lng, $in_lng, $in_lat, $in_lat];
        return $this->pdo_instance->preparedStatement($sql, $params, true);
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
                    $entity = ['tzname' => $row['tzname'], 'polygon' => unpack('VSRID/corder/ltype/Lnum_rings/Lnum_points/d*', $row['polygon'])];
                    unset($entity['polygon']['SRID']);
                    unset($entity['polygon']['order']);
                    unset($entity['polygon']['type']);
                    unset($entity['polygon']['num_rings']);
                    unset($entity['polygon']['num_points']);
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