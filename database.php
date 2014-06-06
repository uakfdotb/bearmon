<?php

database_init();

function database_die($ex = NULL) {
	if($ex == NULL) {
		die("Encountered database error.\n" . monitor_get_backtrace() . "\n");
	} else {
		die("Encountered database error: " . $ex->getMessage() . ".\n" . monitor_get_backtrace() . "\n");
	}
}

function database_error($ex = NULL) {
	global $database;

	try {
		$database->query('SELECT 1');
		database_die($ex);
	} catch(PDOException $e) {
		//indicates error due to disconnect
		database_reconnect();
	}
}

function database_init($reconnect = false) {
	global $database, $config;

	try {
		$database = new PDO('mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'], $config['db_username'], $config['db_password'], array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	} catch(PDOException $ex) {
		if(!$reconnect) {
			database_die($ex);
		}
	}
}

function database_reconnect() {
	global $database;
	$database = NULL;

	while($database == NULL) {
		print "database: reconnecting to database\n";
		sleep(5);
		database_init(true);
	}
}

function database_query($command, $array = array(), $assoc = false) {
	global $database;

	if(!is_array($array)) {
		database_die();
	}

	//convert false/true to numbers
	foreach($array as $k => $v) {
		if($v === false) {
			$array[$k] = 0;
		} else if($v === true) {
			$array[$k] = 1;
		}
	}

	try {
		$query = $database->prepare($command);

		if(!$query) {
			print_r($database->errorInfo());
			database_error();
			return database_query($command, $array, $assoc);
		}

		//set fetch mode depending on parameter
		if($assoc) {
			$query->setFetchMode(PDO::FETCH_ASSOC);
		} else {
			$query->setFetchMode(PDO::FETCH_NUM);
		}

		$success = $query->execute($array);

		if(!$success) {
			print_r($database->errorInfo());
			database_error();
			return database_query($command, $array, $assoc);
		}

		return $query;
	} catch(PDOException $ex) {
		database_error($ex);
		return database_query($command, $array, $assoc);
	}
}

function database_insert_id() {
	global $database;
	return $database->lastInsertId();
}

function database_create_select($vars) {
	$select = "";

	foreach($vars as $key => $var) {
		if(!empty($select)) {
			$select .= ', ';
		}

		$select .= "$var as `$key`";
	}

	return 'SELECT ' . $select;
}

function database_create_where($key_map, $constraints, &$params) {
	$where = "";

	foreach($constraints as $key_desc => $constraint) {
		if(!is_array($constraint)) {
			$constraint = array('=', $constraint);
		}

		if(!isset($key_map[$key_desc])) {
			continue;
		} else if($constraint[0] != '<' && $constraint[0] != '>' && $constraint[0] != '=' && $constraint[0] != '~' && $constraint[0] != '!=' && $constraint[0] != '<=' && $constraint[0] != '>=' && $constraint[0] != 'in') {
			continue;
		}

		if($constraint[0] == '~') {
			$constraint[0] = 'LIKE';
		}

		$key = $key_map[$key_desc];

		if(empty($where)) {
			$where .= "WHERE ";
		} else {
			$where .= " AND ";
		}

		if($constraint[0] == 'in') {
			//if this is IN, then constraint[1] should be a list of possibilities "('a', 'b', 'c')"
			$where .= $key . " " . $constraint[0] . " (";
			$first = true;

			foreach($constraint[1] as $x) {
				if(!$first) {
					$where .= ', ';
				} else {
					$first = false;
				}

				$where .= "?";
				$params[] = $x;
			}

			$where .= ")";
		} else {
			$where .= $key . " " . $constraint[0] . " ?";
			$params[] = $constraint[1];
		}
	}

	return $where;
}

//returns a limit clause
//also sets second parameter to the actual limit
function database_create_limit($context, &$limit_max, $limit_page = 0) {
	$conf_limit_max = 50000;

	if($limit_max == -1) {
		$limit_max = $conf_limit_max;
	} else {
		$limit_max = min(intval($limit_max), $conf_limit_max);
	}

	if($limit_page < 0) {
		$limit_page = 0;
	}

	$limit_start = intval($limit_page * $limit_max);
	$limit = "LIMIT $limit_start, $limit_max";
	return $limit;
}

//constraints is where clause constraint array
//arguments optionally contains:
//  order_by: what to order by
//  order_asc: true if ASC, false if DESC
//  limit_max: number of entries in response is min(args['limit_max'], config['limit_max'], matching rows)
//    -1 means unlimited
//  limit_page: zero-based index to start
//  extended: if in addition to the actual list we should return the number of pages and other information
//  order_by_vars/select_vars/where_vars: extra variables to order by, select, or constrain
//  count: if extended and want to count by different thing from COUNT(*)
function database_object_list($vars, $table, $constraints, $arguments, $f_extra = false, $groupby = '') {
	$params = array(); //to the stored procedure

	$order_by_vars = $vars;
	if(isset($arguments['order_by_vars'])) {
		$order_by_vars = array_merge($order_by_vars, $arguments['order_by_vars']);
	}

	$select_vars = $vars;
	if(isset($arguments['select_vars'])) {
		$select_vars = array_merge($select_vars, $arguments['select_vars']);
	}

	$where_vars = $vars;
	if(isset($arguments['where_vars'])) {
		$where_vars = array_merge($where_vars, $arguments['where_vars']);
	}

	//where
	$where = database_create_where($where_vars, $constraints, $params);

	//limit
	$limit_type = 'generic';
	$limit_max = -1;
	$limit_page = 0;

	if(isset($arguments['limit_type'])) {
		$limit_type = $arguments['limit_type'];
	}

	if(isset($arguments['limit_max'])) {
		$limit_max = $arguments['limit_max'];
	}

	if(isset($arguments['limit_page'])) {
		$limit_page = $arguments['limit_page'];
	}

	$limit = database_create_limit($limit_type, $limit_max, $limit_page);

	//select
	$select = database_create_select($select_vars);

	//orderby
	$orderby = "";

	if(isset($arguments['order_by']) && isset($order_by_vars[$arguments['order_by']])) {
		$orderby = "ORDER BY " . $order_by_vars[$arguments['order_by']];
	} else if(!empty($order_by_vars)) {
		$orderby = "ORDER BY " . reset($order_by_vars);
	}

	if(!empty($orderby) && (!isset($arguments['order_asc']) || !$arguments['order_asc'])) {
		$orderby .= " DESC";
	}

	$result = database_query("$select FROM $table $where $groupby $orderby $limit", $params, true);
	$array = array();

	while($row = $result->fetch()) {
		if($f_extra !== false) {
			$f_extra($row);
		}

		$array[] = $row;
	}

	//check if we should return other information
	if(isset($arguments['extended'])) {
		$extended_array = array();
		$extended_array['list'] = &$array;

		//get number of pages
		if(empty($groupby)) {
			$result = database_query("SELECT COUNT(*) FROM $table $where", $params);
		} else {
			//non-empty group by, means we have to do subquery count
			$result = database_query("SELECT COUNT(*) FROM (SELECT 0 FROM $table $where $groupby) sub", $params);
		}

		$row = $result->fetch();
		$extended_array['count'] = ceil($row[0] / $limit_max);

		return $extended_array;
	} else {
		return $array;
	}
}

//returns array of possible constraints from request variables
// these are not sanitized in any way
// only like (~) relationships are supported
function database_filter_constraints($array) {
	$constraints = array();

	foreach($array as $k => $v) {
		if(!empty($v)) {
			$constraints[$k] = array('~', "%$v%");
		}
	}

	return $constraints;
}

function database_extract_constraints() {
	$array = array();

	foreach($_REQUEST as $k => $v) {
		if(string_begins_with($k, "constraint_")) {
			$array[substr($k, 11)] = $v;
		}
	}

	return $array;
}

?>
