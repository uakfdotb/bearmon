<?php

require_once('config.php');
require_once('common.php');

if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
	echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
	exit;
}

if (socket_bind($sock, '0.0.0.0', $config['multi_port']) === false) {
	echo "socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
	exit;
}

if (socket_listen($sock, 5) === false) {
	echo "socket_listen() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
	exit;
}

echo "ready and loaded\n";

while(true) {
	if (($msgsock = socket_accept($sock)) === false) {
		echo "socket_accept() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
		break;
	}

	echo "New connection\n";

	$address = 0;
	if(socket_getpeername($msgsock, $address) === false) {
		echo "socket_getpeername failed: reason: " . socket_strerror(socket_last_error($msgsock)) . "\n";
		socket_close($msgsock);
		continue;
	}

	if($address != $config['multi_master']) {
		echo "remote side has unrecognized ip [$address]\n";
		socket_close($msgsock);
		continue;
	}

	if (false === ($buf = socket_read($msgsock, 2048, PHP_NORMAL_READ))) {
		echo "socket_read() failed: reason: " . socket_strerror(socket_last_error($msgsock)) . "\n";
		socket_close($msgsock);
		continue;
	}

	if(trim($buf) != $config['multi_secret']) {
		echo "incorrect secret received\n";
		socket_close($msgsock);
		continue;
	}

	echo "executing monitoring script\n";
	include('monitor.php');

	$message = "success\n";
	socket_write($msgsock, $message, strlen($message));
	socket_close($msgsock);
}

socket_close($sock);

?>
