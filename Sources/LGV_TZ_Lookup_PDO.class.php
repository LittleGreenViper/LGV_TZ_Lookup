<?php
/***************************************************************************************************************************/
/**
    This is a PDO abstraction class, derived from the Badger Hardened Baseline Database Component
    
    This defaults to a standard localhost MySQL server (can be other types of servers).
    
    © Copyright 2023, [The Great Rift Valley Software Company](https://riftvalleysoftware.com)
    
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
defined( 'LGV_DB_CATCHER' ) or die ( 'Cannot Execute Directly' );	// Makes sure that this file is in the correct context.

/***************************************************************************************************************************/
/**
    \brief This class provides a genericized interface to the [PHP PDO](http://us.php.net/pdo) toolkit.
 */
class LGV_TZ_Lookup_PDO {
	/// \brief Internal PDO object
	private $_pdo = NULL;
	/// \brief The type of PDO driver we are configured for.
	var $driver_type = NULL;
	/// \brief This holds the integer ID of the last AUTO_INCREMENT insert.
	var $last_insert = NULL;
    
    /***********************************************************************************************************************/
    /***********************/
	/**
		\brief Initializes connection param class members.
		
		Must be called BEFORE any attempts to connect to or query a database. This uses UTF8, as the charset.
		
		Will destroy previous connection (if one exists).
	*/
	public function __construct(    $inDatabase,    ///< database name (required)
	                                $inUser,	    ///< user, (required)
                                    $inPassword,    ///< password, (required)
                                    $inDriver,	    ///< database server type (required)
                                    $inHost,        ///< database server host (required)
                                    $inPort 	    ///< database TCP port (required)
								) {
		$this->_pdo = NULL;
		$this->driver_type = strtolower($inDriver);
		
        $dsn = $inDriver . ':host=' . $inHost . ';dbname=' . $inDatabase . ';charset=utf8;port=' . strval($inPort);
        
		try {
            $this->_pdo = new PDO($dsn, $inUser, $inPassword);
            $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->_pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
            $this->_pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        } catch (PDOException $exception) {
			throw new Exception(__METHOD__ . '() ' . $exception->getMessage());
        }
	}

    /***********************/
	/**
		\brief Wrapper for preparing and executing a PDOStatement
		
		\throws Exception   thrown if internal PDO exception is thrown
		\returns            true if execution is successful (and fetchResponse is false), or an array of associative arrays of results, if fetchResponse is true.
	*/
	public function preparedStatement(  $sql,				    ///< SQL statement to send (with question mark placeholders).
								        $params = array(),      ///< Data for the placeholders. Default is an empty array.
								        $fetchResponse = false  ///< If true (default is false), then a fetch will be done, and a response returned.
						            ) {
		if ( NULL == $this->_pdo ) {
            throw new Exception(__METHOD__ . '()::' . __LINE__ . "\nNo PDO object!");
		}
		
		// Non-MySQL servers aren't fans of the backticks.
		if ( 'mysql' != $this->driver_type ) {
		    $sql = str_ireplace('`', '', $sql);
		}
		
		try {
            if ( !$this->_pdo->inTransaction() ) {
		        $this->_pdo->beginTransaction();
		    }
		    
            $stmt = $this->_pdo->prepare($sql);
        
            if ( false == $stmt || -1 == $stmt ) {
                throw new Exception(__METHOD__ . '()::' . __LINE__ . "\n" . print_r($stmt->errorInfo(), true));
            }
            
            if ( $fetchResponse ) {
                $stmt->setFetchMode(PDO::FETCH_ASSOC);
            }
            
            $stmt->execute($params);
            
            $ret = false;
            
            if ( $fetchResponse ) {
                $ret = $stmt->fetchAll();
            } else {
                $ret = true;
            }
        
            if ( $this->_pdo->inTransaction() ) {
                $this->_pdo->commit();
            }
            
            return $ret;
		} catch (PDOException $exception) {
		    $this->last_insert = NULL;
            $this->_pdo->rollback();
			throw new Exception(__METHOD__ . '()::' . __LINE__ . "\n" . $exception->getMessage());
		}
		
        return false;
	}
};

?>