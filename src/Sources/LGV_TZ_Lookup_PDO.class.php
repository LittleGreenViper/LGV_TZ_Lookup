<?php
/***************************************************************************************************************************/
/**
    © Copyright 2023-2026, [The Great Rift Valley Software Company](https://riftvalleysoftware.com)
    
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
    \brief This class provides a genericized interface to the [PHP PDO](http://us.php.net/pdo) toolkit.

    This is a PDO abstraction class, derived from the Badger Hardened Baseline Database Component
    
    This supports MySQL and PostgreSQL, including binary parameters and bounded polygon reads.
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
	public function __construct(    $inDatabase,    ///< database name
	                                $inUser,	    ///< database user
                                    $inPassword,    ///< database password
                                    $inDriver,	    ///< database server type
                                    $inHost,        ///< database server host
                                    $inPort 	    ///< database TCP port
								) {
		$this->_pdo = NULL;
		$this->driver_type = strtolower($inDriver);

        if (!in_array($this->driver_type, ['mysql', 'pgsql'], true)) {
            throw new InvalidArgumentException('Supported database drivers are mysql and pgsql.');
        }
		
        $inPort = $inPort ?? ('pgsql' === $this->driver_type ? 5432 : 3306);
        $dsn = $this->driver_type . ':host=' . $inHost . ';dbname=' . $inDatabase . ';port=' . strval($inPort);
        $dsn .= 'mysql' === $this->driver_type ? ';charset=utf8' : ';options=--client_encoding=UTF8';
        
		try {
            $this->_pdo = new PDO($dsn, $inUser, $inPassword);
            $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->_pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
            $this->_pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 'mysql' === $this->driver_type);
            if ('pgsql' === $this->driver_type) {
                // Execute once without a separate PREPARE round trip; retain native binary parameter binding.
                $attribute = class_exists('Pdo\\Pgsql') ? \Pdo\Pgsql::ATTR_DISABLE_PREPARES : PDO::PGSQL_ATTR_DISABLE_PREPARES;
                $this->_pdo->setAttribute($attribute, true);
            }
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
								        $fetchResponse = false, ///< If true (default is false), then a fetch will be done, and a response returned.
                                    $paramTypes = array()   ///< Optional zero-based PDO parameter types for positional write parameters.
						            ) {
        // A read needs no BEGIN/COMMIT round trips. Retain the array-returning public API.
        if ($fetchResponse) {
            return iterator_to_array($this->preparedRows($sql, $params), false);
        }
		if ( NULL == $this->_pdo ) {
            throw new Exception(__METHOD__ . '()::' . __LINE__ . "\nNo PDO object!");
		}
		
		// Non-MySQL servers aren't fans of the backticks.
		if ( 'mysql' != $this->driver_type ) {
		    $sql = str_ireplace('`', '', $sql);
		}
		
        $ownsTransaction = !$this->_pdo->inTransaction();
		try {
            if ($ownsTransaction) {
		        $this->_pdo->beginTransaction();
		    }
		    
            $stmt = $this->_pdo->prepare($sql);
        
            if ( false == $stmt || -1 == $stmt ) {
                throw new Exception(__METHOD__ . '()::' . __LINE__ . "\n" . print_r($stmt->errorInfo(), true));
            }
            
            if (empty($paramTypes)) {
                $stmt->execute($params);
            } else {
                foreach (array_values($params) as $index => $value) {
                    $stmt->bindValue($index + 1, $value, $paramTypes[$index] ?? PDO::PARAM_STR);
                }
                $stmt->execute();
            }
            $stmt->closeCursor();
        
            if ($ownsTransaction && $this->_pdo->inTransaction()) {
                $this->_pdo->commit();
            }
            
            return true;
		} catch (PDOException $exception) {
		    $this->last_insert = NULL;
            if ($ownsTransaction && $this->_pdo->inTransaction()) {
                $this->_pdo->rollBack();
            }
			throw new Exception(__METHOD__ . '()::' . __LINE__ . "\n" . $exception->getMessage(), 0, $exception);
		}
		
        return false;
	}

    /***********************************************************************************************************************/
    /**
        Yield rows one at a time and release the cursor, including when a lookup returns before consuming every row.
        Polygon reads can disable MySQL buffering to avoid retaining every candidate blob. An unbuffered reader
        must be closed before another query; the finally block drains its cursor and restores the connection mode.
        PostgreSQL uses single-row fetching on PHP 8.5+, or a server cursor on earlier PHP releases. BYTEA streams
        are read and closed here, so callers receive the same binary strings on either database.
     */
    public function preparedRows($sql, $params = array(), $buffered = true) {
        if (NULL == $this->_pdo) {
            throw new Exception(__METHOD__.'(): No PDO object!');
        }
        if ('mysql' != $this->driver_type) {
            $sql = str_ireplace('`', '', $sql);
        }
        $stmt = NULL;
        $bufferAttribute = NULL;
        $previousBuffering = NULL;
        $pgsqlStreaming = false;
        $executed = false;
        try {
            if ('mysql' == $this->driver_type && !$buffered) {
                // PHP 8.5 deprecates the PDO alias; the fallback supports the project's earlier PHP versions.
                $bufferAttribute = class_exists('Pdo\\Mysql') ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
                $previousBuffering = $this->_pdo->getAttribute($bufferAttribute);
                $this->_pdo->setAttribute($bufferAttribute, false);
            }
            $options = [];
            if ('pgsql' === $this->driver_type && !$buffered) {
                $pgsqlStreaming = PHP_VERSION_ID >= 80500;
                $options = $pgsqlStreaming ? [PDO::ATTR_PREFETCH => 0] : [PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL];
            }
            $stmt = $this->_pdo->prepare($sql, $options);
            $stmt->execute($params);
            $executed = true;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ('pgsql' === $this->driver_type) {
                    foreach ($row as $column => $value) {
                        if (is_resource($value)) {
                            try {
                                $row[$column] = stream_get_contents($value);
                                if (false === $row[$column]) {
                                    throw new RuntimeException('Could not read PostgreSQL binary column '.$column.'.');
                                }
                            } finally {
                                fclose($value);
                            }
                        }
                    }
                    unset($value);
                }
                yield $row;
                unset($row);
            }
        } catch (PDOException $exception) {
            throw new Exception(__METHOD__.'() '.$exception->getMessage(), 0, $exception);
        } finally {
            try {
                if (NULL !== $stmt && false !== $stmt) {
                    if ($pgsqlStreaming && $executed) {
                        // PDO_PGSQL closeCursor() does not drain lazy results. Destroying an unfinished statement
                        // cancels its query, which can abort a caller's transaction. Consume without decoding columns.
                        while ($stmt->fetch(PDO::FETCH_BOUND)) { }
                    }
                    $stmt->closeCursor();
                }
            } finally {
                if (NULL !== $previousBuffering) {
                    $this->_pdo->setAttribute($bufferAttribute, $previousBuffering);
                }
            }
        }
    }
};

?>
