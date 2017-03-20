<?php
	
	/**
	 * MysqliDb Class
	 *
	 * @category  Database Access
	 * @package   PdoDb
	 * @author    Emmanuel O. Sobande <eosobande@gmail.com>
	 * @copyright Copyright (c) 2016
	 * @link      http://github.com/eosobande/PHP-MySQLi-Database-Class 
	 * @version   1.0
	 */

    trait PDOdbRef {

        public function __db_instance_ref() {
            global $db;
            $this->db =& $db; // db reference
        }
        
    }

	class PDOdb extends SQLBuilder {

	    /**
	     * Result fetch types
	     * @var int
	     */
		const _FETCH_ALL = 1;
		const _FETCH_ONE = 2;
		const _FETCH_ONE_FIELD = 3;
		const _FETCH_FIRST_FROM_EACH_ROW = 4;

	    /**
	     * Database Server
	     * @var string
	     */
		private $server_name;

		/**
	     * Database Name
	     * @var string
	     */
		private $db_name;

		/**
	     * Database Username
	     * @var string
	     */
		private $db_user;

		/**
	     * Database password
	     * @var string
	     */
		private $db_password;

		/**
	     * Previously executed SQL query
	     * @var string
	     */
		private $last_sql;

		/**
	     * Variable that holds the result of a SQL Query
	     * @var boolean
	     */
		private $result;

		/**
	     * Variable that holds the number of rows returned|affected by SELECT/UPDATE/DELETE statements
	     * @var integer
	     */
		private $row_count;

		/**
	     * The prepared statement object
	     * @var object
	     */
		private $stmt;

		/**
	     * Instance of the PDO class
	     * @var boolean
	     */
		private $pdo;

		/**
	     * The type of database we've connected to
	     * @var string
	     */
		private $database_type;	

	    /**
	     * __construct method creates the database connection
	     *
	     * @param string $server_name
	     * @param string $db_name
	     * @param string $db_user
	     * @param string $db_pass
	     */
		public function __construct($server_name, $db_name=NULL, $db_user=NULL, $db_password=NULL) {

			try {

				if ($db_name && $db_user) {
					$this->pdo = new PDO("mysql:host=$server_name;dbname=$db_name", $db_user, $db_password);
					$this->database_type = 'MYSQL';
				} else {
					$this->pdo = new PDO("sqlite:$server_name");
					$this->database_type = 'SQLITE';
				}			

				$this->server_name = $server_name;
				$this->db_name = $db_name;
				$this->db_user = $db_user;
				$this->db_password = $db_password;

				// set the PDO error mode to exception
    			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

			} catch (PDOException $e) {
				// Connection failed
				die($e->getMessage());
			}
		}

		/**
		 * A method to close the PDO database connection
		 *
     	 * @return void
		 */
        public function close() {
        	$this->db->pdo = NULL;
        }

		/**
		 * A method to instantiate a new PDO class if old instance times out
		 *
     	 * @return void
		 */
        public function pdo_reconnect() {
            $this->pdo = new PDOdb($this->server_name, $this->db_name, $this->db_user, $this->db_password);
        }

		/**
		 * Method returns $row_count
		 *
     	 * @return int
		 */
        public function row_count() {
        	return $this->stmt->rowCount();
        }

		/**
		 * Method returns last sql statement
		 *
     	 * @return string
		 */
        public function last_sql() {
        	return $this->last_sql;
        }

		/**
		 * A method of accessing the id of the last insert statement
		 *
		 * @uses PDO::lastInsertId() method
		 *
		 * @return int
		 */
        public function last_insert_id() {
        	return $this->pdo->lastInsertId();
        }

		/**
		 * A method of accessing error information from SQL execution
		 *
		 * @uses PDO::errorinfo() method
		 *
     	 * @return string
		 */
        public function error_info() {
        	return $this->pdo->errorinfo();
        }

		/**
		 * Begin a Transaction (MULTIPLE QUERY)
		 * 
		 * @uses PDO::beginTransaction()
     	 * @return void
		 */
		public function begin_transaction() {
    		$this->pdo->beginTransaction();
		}

		/**
		 * Commit Transaction (MULTIPLE QUERY)
		 *
		 * @uses PDO::commit()
		 * @uses PDO::rollback()
		 *
     	 * @return void
		 */
        public function commit() {
        	try {
        		$this->result = $this->pdo->commit();
        	} catch(PDOException $e) {
			    // roll back the transaction if something failed
			    $this->pdo->rollback();
			    echo $e->getMessage();
        	}
        }

		/**
		 * Method prepares an SQL statement
		 *
		 * @uses PDO::prepare($sql)
		 *
     	 * @param array $options
     	 *
     	 * @return boolean
		 */
	    public function prepare($options=[]) {

	    	$this->stmt = $this->pdo->prepare($this->sql, $options);

	    	// Set the last_sql variable
	    	$this->last_sql = $this->sql;

	    	return is_object($this->stmt);

	    }

		/**
		 * Method executes a prepared statement
		 *
     	 * @param array $args
     	 * @return int
		 */
	    public function execute($args=[], $prepared_stmt=FALSE) {

	    	if (!$prepared_stmt && (empty($args) || is_array($args))) {
	    		$this->prepare();
	    	} elseif ($args && !is_array($args)) {
				throw new Exception(__METHOD__.' expects parameter 1 to be array, '.strtoupper(gettype($args)).' given', 1);
	    	}

			try {
				$this->result = $this->stmt->execute($args);
			} catch (PDOException $e) {

				if ($e->getCode() != 'HY000' || !stristr($e->getMessage(), 'server has gone away')) {
                	throw $e;
            	}
            	// Server has gone away, reconnect
            	$this->pdo_reconnect($prepared_stmt);
            	$this->result = $this->stmt->execute($args);
			}

	    	$this->unset_sql();
			return $this->row_count();

	    }

		/**
		 * Method returns a result set
		 *
		 * @param string $fetch_type
		 *
		 * @uses PDOStatement::setFetchMode(PDO::FETCH_ASSOC)
		 *
     	 * @return array|string|int
		 */
        public function fetch($fetch_type=self::_FETCH_ALL, $exec=TRUE) {

        	if ($exec) {
        		$this->execute();
        	}

        	$this->result = $this->stmt->setFetchMode(PDO::FETCH_ASSOC);

        	$rows = $fetch_type == self::_FETCH_ONE || $fetch_type == self::_FETCH_ONE ? $this->stmt->fetch() : $this->stmt->fetchAll();
        	if (!$rows) {
        		return NULL;
        	}

        	switch ($fetch_type) {
        		case self::_FETCH_ONE:
        		case self::_FETCH_ALL:
        		case NULL:
        			return $rows;
        			break;

        		case self::_FETCH_FIRST_FROM_EACH_ROW:
        			return array_map('array_shift', $rows);
        			break;

        		case self::_FETCH_ONE_FIELD:
        			$row = array_shift($rows);
        			return array_shift($row);
        			break;
        	}

        }

	}

	$db = new PDOdb(DB_SERVER,DB_NAME,DB_USER,DB_PASS);



