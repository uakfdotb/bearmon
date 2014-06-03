<?php

monitor_lock_init('monitor.php');

require_once('config.php');
require_once('common.php');
require_once('database.php');
require_once('checks.php');
require_once('alerts.php');

$result = database_query("SELECT id, name, type, data, fail_count, success_count, status, last_alert, turn_count FROM checks", array(), true);

while($row = $result->fetch()) {
	$check_id = $row['id'];
	$check_name = $row['name'];
	$fail_count = $row['fail_count'];
	$success_count = $row['success_count'];
	$status = $row['status'];
	$last_alert = $row['last_alert'];
	$turn_count = $row['turn_count'];

	$check_fname = "check_{$row['type']}";

	if(!function_exists($check_fname)) {
		die("Invalid check function [{$check_fname}]!\n");
	}

	echo "processing check $check_id: calling $check_fname\n";
	$check_result = call_user_func($check_fname, $row['data']);

	if(!is_array($check_result) || !isset($check_result['status'])) {
		die("Bad check function [{$check_fname}]: returned non-array or missing status.\n");
	} else if(!isset($check_result['message'])) {
		$check_result['message'] = "Check offline: $check_name";
	}

	if($check_result['status'] == 'fail') {
		echo "... got failure!\n";
		$string_result = 'fail';

		if($status == 'online') {
			if($turn_count + 1 >= $fail_count) {
				//target has failed
				echo "... fail_count exceeded, target is offline state now\n";
				database_query("UPDATE checks SET status = 'offline', turn_count = 0 WHERE id = ?", array($check_id));

				$alert_result = database_query("SELECT contacts.id, contacts.type, contacts.data FROM contacts, alerts WHERE contacts.id = alerts.contact_id AND alerts.check_id = ?", array($check_id), true);

				while($alert_row = $alert_result->fetch()) {
					echo "... alerting contact {$alert_row['id']}\n";
					$alert_fname = "alert_{$alert_row['type']}";

					if(!function_exists($alert_fname)) {
						die("Invalid alert function [{$alert_fname}]!\n");
					}

					call_user_func($alert_fname, $alert_row['data'], "Check offline: $check_name", $check_result['message']);
				}
			} else {
				//increase turn count
				database_query("UPDATE checks SET turn_count = turn_count + 1 WHERE id = ?", array($check_id));
			}
		} else {
			database_query("UPDATE checks SET turn_count = 0 WHERE id = ?", array($check_id));
		}
	} else if($check_result['status'] == 'success') {
		echo "... got success\n";
		$string_result = 'success';

		if($status == 'offline') {;
			if($turn_count + 1 >= $success_count) {
				//target has come back online
				echo "... success_count exceeded, target is online state now\n";
				database_query("UPDATE checks SET status = 'online', turn_count = 0 WHERE id = ?", array($check_id));
			} else {
				//increase turn count
				database_query("UPDATE checks SET turn_count = turn_count + 1 WHERE id = ?", array($check_id));
			}
		} else {
			database_query("UPDATE checks SET turn_count = 0 WHERE id = ?", array($check_id));
		}
	} else {
		die("Check function [{$check_fname}] returned invalid status code [{$check_result['status']}]!\n");
	}

	database_query("INSERT INTO check_results (check_id, result) VALUES (?, ?)", array($check_id, $check_result['status']));
}

?>
