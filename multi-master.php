<?php

require_once('config.php');
require_once('common.php');
require_once('alerts.php');

$last_contact = 0;

while(true) {
	foreach($config['multi_slaves'] as $slave) {
		echo "going to sleep for {$config['multi_interval']}\n";
		sleep($config['multi_interval']);
		echo "using [$slave]\n";

		if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
			multi_alert('failed to create socket');
			continue;
		}

		if (socket_connect($sock, $slave, $config['multi_port']) === false) {
			multi_alert('failed to connect to slave');
			continue;
		}

		$message = "{$config['multi_secret']}\n";
		socket_write($sock, $message, strlen($message));
		$time_start = microtime(true);

		if (false === ($buf = socket_read($sock, 2048, PHP_NORMAL_READ))) {
			socket_close($sock);
			multi_alert('did not receive response from slave');
			continue;
		}

		socket_close($sock);
		$time_end = microtime(true);
		$time_elapsed = round(($time_end - $time_start) * 1000.0);
		echo "... success (time: $time_elapsed ms)\n";
	}
}

function multi_alert($message) {
	global $last_contact, $config;

	echo "... error communicating: $message\n";

	if(time() - $last_contact > 600 && !empty($config['multi_contact'])) {
		echo "... forwarding error to contact\n";
		alert_email($config['multi_contact'], 'Slave communication failed', $message);
		$last_contact = time();

	}
}

?>
