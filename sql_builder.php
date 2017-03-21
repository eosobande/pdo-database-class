<?php
	
	/**
	 * SQLBuilder Class
	 *
	 * @category  SQL Query Statements Auto Generation
	 * @package   SQLBuilder
	 * @author    Emmanuel O. Sobande <eosobande@gmail.com>
	 * @copyright Copyright (c) 2016
	 * @link      http://github.com/eosobande/pdo-database-class
	 * @version   1.0
	 */

	class SQLBuilder {

	    /**
	     * SQL KEYWORDS
	     * @var array
	     */
		private $keywords = [
			'group_by' => ' GROUP BY ',
			'order_by' => ' ORDER BY ',
			'limit' => ' LIMIT ',
			'offset' => ' OFFSET '
		];

	    /**
	     * Table Join Types
	     * @var array
	     */
		private $join_types = ['LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER'];

		/**
	     * SQL Query string to be executed
	     * @var string
	     */
		protected $sql;

		/**
	     * Variable that holds the where statement
	     * @var array
	     */
		private $where;

		/**
	     * Variable that holds the having statement
	     * @var array
	     */
		private $having;

		/**
	     * Union
	     * @var array
	     */
		private $union;

		/**
	     * Var holds the typr of query to be executed
	     * @var string
	     */
		private $query_type;

		/**
	     * Variable which allows query values to be quoted automatically
	     * @var boolean
	     */
		protected $auto_quote_values = TRUE;

		/**
	     * Get $sql
	     * @return string
	     */
		public function get_sql() {
			return $this->sql;
		}

		/**
	     * Unset $sql
	     * @return void
	     */
		protected function unset_sql() {
			$this->sql = NULL;
		}

		/**
		 * Method sets @var SQLBuilder::auto_quote_values
		 *
		 * @param boolean $query_type
     	 * @return void
		 */
		public function auto_quote_values($bool) {
			$this->auto_quote_values = (bool) $bool;	
		}

		/**
		 * Method sets @var $query_type
		 *
		 * @param string $query_type
     	 * @return void
		 */
		private function set_query_type($query_type) {
			$this->query_type = $query_type;		
		}

		/**
		 * Run raw SQL Query
		 *
		 * @param string $sql @var holds SQL statement
		 *
     	 * @return int
		 */
        public function raw_query($sql) {

			$this->set_query_type('RAW');

        	if (is_object($sql) && get_class($sql) === __CLASS__) {
        		$this->sql .= $sql->get_sql();
        	} else {
        		$this->sql .= $sql;
        	}
        	
        }

		/**
		 * Instantiate object for sub queries
		 *
     	 * @return SQLBuilder Objeect
		 */
        public function sub_query() {
			return new self;
        }

		/**
		 * COMPILE the TABLES for the statement
		 *
		 * @param array|string $table_names @var that holds the name(s) of the table(s)
		 *
     	 * @return void
		 */
		private function table_clause(&$table_names) {

        	if (is_array($table_names)) {

        		// i.e selecting from multiple tables
	        	foreach ($table_names as $table) {
	        		if (is_array($table)) {

	        			// alias
	        			$clause = !empty($table['as']) ? "{$table[0]} AS {$table['as']}" : $table[0];

	        			// the table has join
	        			// [ 	0=>table name, join => join type, on => on statement 	]	 
	        			if (!empty($table['join']) && in_array(strtoupper($table['join']), $this->join_types) && !empty($table['on'])) {
	        				$clause = strtoupper($table['join']). ' JOIN ' . $clause . ' ON ' . $table['on'];
	        			}

	        			$joins[] = $clause;

	        		} else {
	        			$joins[] = $table;
	        		}
	        	}

        	}

        	// convert array to text seperated by commas if $joins is not empty
        	$this->sql .= empty($joins) ? $table_names : implode(' ', $joins);

		}

		/**
		 * Create a VIEW
		 *
		 * @param string $view_name @var contains name of the VIEW
		 * @param boolean $replace @var determining whether to use 'OR REPLACE' keyword
		 *
     	 * @return void
		 */
		public function create_view($view_name, $replace=FALSE) {
			$this->sql .= "CREATE " . ($replace ? 'OR REPLACE ' : NULL) . "VIEW $view_name AS ";
		}

		/**
		 * DROP a Database|Table|View
		 *
		 * @param string $name @var that holds the name of the DATABASE|TABLE|VIEW
		 * @param string $type @var containing what type of object to DROP
		 *
     	 * @return void
		 */
		public function drop($name, $type='table') {

			if (in_array(strtolower($type), ['database', 'table', 'view'])) {
				$this->sql .= "DROP " . strtoupper($type) . " $name";
			} else {
				throw new Exception('invalid type passed as parameter 2', 1);
			}

		}

		/**
		 * Build SQL INSERT Query
		 *
		 * @param boolean $ignore @var to determine whether to ignore on duplicate
		 * @param array|string $table_name @var that holds the table to insert into
		 * @param string $columns @var of column(s) to insert values into
		 * @param array|string $values @var of value(s) to insert into the column(s)
		 *
     	 * @return void
		 */
		public function insert($table_name, $values, $columns=NULL, $ignore=FALSE) {

			$this->set_query_type('INSERT');

			$this->sql .= 'INSERT ' . ($ignore ? 'IGNORE ' : NULL) . "INTO $table_name";
			$this->sql .= $this->add_parenthesis(is_array($columns) ? implode(',', $columns) : $columns);
			$this->sql .= ' VALUES ' . $this->add_parenthesis($this->quote_values($values));
			
		}

		/**
		 * Build SQL SELECT Query
		 *
		 * @param boolean $distinct @var to determine whether to select only unique fields
		 * @param array|string $columns @var of column(s) to be selected
		 * @param array|string $table_names @var that holds the table(s) to select data from
		 * @param array $keywords @var containing SQL KEYWORDS ( [group, having, order, limitL, offset] )
		 *
     	 * @return void
		 */
		public function select($table_names, $columns='*', $keywords=[], $distinct=FALSE) {

			if (!$this->union) {
				$this->unset_sql();
			}

			$this->set_query_type('SELECT');

			$this->sql .= 'SELECT ' . ($distinct ? 'DISTINCT ' : NULL);
			$this->sql .= (is_array($columns) ? implode(',', $columns) : $columns) . ' FROM ';
			$this->table_clause($table_names);

			$this->build_rest_of_sql($keywords);

			if ($this->union) {
				$this->union = NULL;
			}

		}

		/**
		 * Build SQL UPDATE Query
		 *
		 * @param array|string $table_names @var that holds the table(s) to update
		 * @param array|string $set @var containing the SET clause
		 * @param int $limit @var of maximum number of columns to update
		 *
     	 * @return void
		 */
		public function update($table_names, $set, $limit=NULL) {

			$this->set_query_type('UPDATE');

			$this->sql .= 'UPDATE ';
			$this->table_clause($table_names);
			$this->sql .= ' SET ' . $this->set_clause($set);

			$this->build_rest_of_sql(['limit'=>$limit]);

		}

		/**
		 * Build SET clause
		 *
		 * @param array|string $set
		 *
     	 * @return string
		 */
        private function set_clause(&$set) {
        	if (is_array($set)) {

        		// loop through the array keys
	        	foreach ($set as $column => $value) {
	        		// quote the values
	        		$set[$column] = "$column=" . $this->quote_values($value);
	        	}

	        	// return as text seperated by commas
	        	return implode(',', $set);
	        }

	        // return as it is, (string) 
	        return trim($set); 
        }

		/**
		 * Build SQL DELETE Query
		 *
		 * @param string $table_name @var that holds the table to DELETE FROM
		 * @param int $limit @var of maximum number of columns to delete
		 *
     	 * @return void
		 */
		public function delete($table_name, $limit=NULL) {

			$this->set_query_type('DELETE');

			$this->sql .= 'DELETE FROM ' . $table_name;
			$this->build_rest_of_sql(['limit'=>$limit]);

		}

		/**
		 * TRUNCATE a table
		 *
		 * @param string $view_name @var that holds the name of the table
		 *
     	 * @return void
		 */
		public function truncate($table_name) {
			$this->delete($table_name);
		}

		/**
		 * Method bulds the remaining parts of the SQL Query
		 *
		 * @param array $keywords i.e GROUP BY|ORDER BY|LIMIT|OFFSET
		 *
     	 * @return void
		 */
		private function build_rest_of_sql($keywords) {

			$this->build_clause('WHERE', $this->where);
			$this->build_keywords('group_by', $keywords);
			$this->build_clause('HAVING', $this->having);
			$this->build_keywords('order_by', $keywords);
			$this->build_keywords('limit', $keywords);
			$this->build_keywords('offset', $keywords);

		}

		/**
		 * SET the KEYWORDS for an SQL query
		 *
		 * @param string $key
		 * @param array $data
		 *
     	 * @return void
		 */
		private function build_keywords($key, &$data) {

			if (!empty($data[$key])) {
				$this->sql .= $this->keywords[$key] . ( is_array($data[$key]) ? implode(', ', $data[$key]) : $data[$key] );
			}

		}

		/**
		 * Method sets the WHERE variable to the argumentsb passed
		 *
		 * @param array|string
		 *
     	 * @return void
		 */
		public function where() {
			$this->where = func_get_args();
		}

		/**
		 * Method sets the HAVING variable to the argumentsb passed
		 *
		 * @param array|string
		 *
     	 * @return void
		 */
        public function having() {
        	$this->having = func_get_args();
        }

		/**
	     * Union
	     * @return void
	     */
		public function union($union=FALSE) {

			if (in_array($union, [NULL, FALSE])) {
				$this->sql .= $this->union = ' UNION ';
			} elseif (in_array($union, [TRUE, 'all'])) {
				$this->sql .= $this->union = ' UNION ALL ';
			} else {
				throw new Exception(__METHOD__."expects parameter 1 to be boolean or 'all'", 1);
			}

			return $this;
		}

		/**
		 * Build WHERE|HAVING clause
		 *
		 * @param string $clause
		 * @param array $data
		 *
     	 * @return void
		 */
        private function build_clause($clause, &$data) {

        	if (is_null($data)) {
        		return;
        	}

    		foreach ($data as $key => $param) {

				// HAVING $data = [	0=>column_name|aggregate_function(column_name), 1=>value, 2=> operator, 3=>and/or operator	]
				// WHERE $data = [	0=>column_name, 1=>value, 2=>operator, 3=>and/or operator	]

    			if (is_array($param)) {

	        		if ($key > 0) {
						if (empty($param[3])) {
							$data[$key] = 'AND';
						} elseif (in_array(strtoupper($param[3]), ['AND', 'OR'])) {
							$data[$key] = strtoupper($param[3]);
						} else {
							throw new Exception(__METHOD__." expects array key 3 of each parameter to be 'AND', 'OR' or NULL", 1);
						}
					} else {
						$data[$key] = ' ' . $clause;
					}

	        		$operator = empty($param[2]) ? '=' : trim(strtoupper($param[2]));
	        		$data[$key] .= ' ' . $param[0] . " $operator ";

	        		switch ($operator) {
	        			case 'BETWEEN':
	        			case 'NOT BETWEEN':
	        				$data[$key] .= implode(' AND ', $this->quote_values($param[1], 'array'));
	        				break;

	        			case 'IN':
	        			case 'NOT IN':
	        				$data[$key] .= $this->add_parenthesis($this->quote_values($param[1]));
	        				break;

	        			case 'IS NULL':
	        				break;

	        			default:
	        				$data[$key] .= $this->quote_values($param[1]);
	        				break;
	        		}

	        	} elseif (is_string($param)) {
	        		$this->where[$key] = ' WHERE ' . $param;
	        	}
    		}

    		$this->sql .= implode(' ', $data);
    		$data = NULL;

        }

		/**
		 * Enclose @var in parenthesis
		 *
		 * @param string|int $value
     	 * @return string
		 */
		private function add_parenthesis($value) {
			return $value ? "($value)" : NULL;
		}

		/**
		 * Surround @var values in single quotes ''
		 *
		 * @param array|string $values
		 * @param string $return_type
		 *
     	 * @return string|array
		 */
        private function quote_values($values, $return_type='string') {

        	if (is_object($values) && get_class($values) === __CLASS__) {
        		return $this->add_parenthesis($values->get_sql());
        	} elseif (!is_array($values)) {
        		$values = [$values];
        	}

        	foreach ($values as $key => $value) {
        		if (is_object($value) && get_class($value) === __CLASS__) {
    				$values[$key] = $value->get_sql();
        		} elseif ($this->auto_quote_values) {
		        	$values[$key] = $value == '?' || is_int($value) ? $value : "'$value'";
		        }
        	}

        	switch ($return_type) {	        		
        		case 'array':
        			return $values;
        			break;

        		default:
        			return implode(',', $values);
        			break;
        	}

        }
		
	}
