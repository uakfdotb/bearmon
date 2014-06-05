<?php

function check_http_contains($data) {
	//keys: substring, url (also http_helper params)
	if(!isset($data['substring']) || !isset($data['url'])) {
		die("check_http_contains: missing substring\n");
	}

	$result = check_http_helper($data);

	if($result['status'] == 'fail') {
		return $result;
	} else if(strpos($result['content'], $data['substring']) !== false) {
		return array('status' => 'success');
	} else {
		return array('status' => 'fail', 'message' => "target [{$data['url']}] does not contain string [{$data['substring']}]");
	}
}

function check_http_helper($data) {
	if(!isset($data['url'])) {
		die("check_http_helper: missing url\n");
	}

	$handle = curl_init($data['url']);

	if($handle === false) {
		return array('status' => 'fail', 'message' => "curl_init: failed to establish connection to target [$url]");
	}

	curl_setopt($handle,  CURLOPT_RETURNTRANSFER, true);
	curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($handle, CURLOPT_AUTOREFERER, true);

	$timeout = 10;
	if(isset($data['timeout'])) {
		$timeout = $data['timeout'];
	}

	curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt($handle, CURLOPT_TIMEOUT, $timeout);
	$response = curl_exec($handle);

	if($response === false) {
		return array('status' => 'fail', 'message' => "curl_exec: failed to read target [$url]");
	}

	$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
	curl_close($handle);

	return array('status' => 'success', 'code' => $httpCode, 'content' => $response);
}

function check_http_status($data) {
	//keys: status, url (also http_helper params)
	if(!isset($data['status']) || !isset($data['url'])) {
		die("check_http_status: missing status");
	}

	$result = check_http_helper($data);

	if($result['status'] == 'fail') {
		return $result;
	} else if($result['code'] == $data['status']) {
		return array('status' => 'success');
	} else {
		return array('status' => 'fail', 'message' => "target [{$data['url']}] returned unexpected status [{$result['code']}], expected [{$data['status']}]");
	}
}

function check_http_ok($data) {
	$data['status'] = 200;
	return check_http_status($data);
}

function check_ssl_expire($data) {
	//keys: hostname, optional days (default 7) and timeout (default 10)
	if(!isset($data['hostname'])) {
		die("check_ssl_expire: missing hostname");
	}

	$hostname = $data['hostname'];
	$days = 7;
	$timeout = 10;

	if(isset($data['days'])) {
		$days = $data['days'];
	}

	if(isset($data['timeout'])) {
		$timeout = $data['timeout'];
	}

	$get = stream_context_create(array("ssl" => array("capture_peer_cert" => TRUE)));
	$read = stream_socket_client("ssl://$hostname:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $get);

	if($read === false) {
		return array('status' => 'fail', 'message' => "failed to read from host [$hostname]");
	}

	$cert = stream_context_get_params($read);
	$result = openssl_x509_parse($cert["options"]["ssl"]["peer_certificate"]);

	if(!isset($result['validTo_time_t'])) {
		return array('status' => 'fail', 'message' => "parsed SSL information missing validTo");
	}

	$remaining = $result['validTo_time_t'] - time();
	$remaining_hours = round($remaining / 3600);

	if($remaining_hours < $days * 24) {
		return array('status' => 'fail', 'message' => "SSL certificate will expire in $remaining_hours hours");
	} else {
		return array('status' => 'success');
	}
}

function check_ping($data) {
	//keys: target
	if(!isset($data['target'])) {
		die("check_ping: missing target");
	}

	$target = $data['target'];
	$result = shell_exec("ping " . escapeshellarg($target) . " -c 3 -w 3");

	if(strpos($result, '100% packet loss') !== false) {
		return array('status' => 'fail', 'message' => "No response from $target");
	} else {
		return array('status' => 'success');
	}
}

function check_tcp_connect($data) {
	//keys: target, port; optional: timeout
	if(!isset($data['target']) || !isset($data['port'])) {
		die("check_tcp_connect: missing target or port");
	}

	$target = $data['target'];
	$port = $data['port'];
	$timeout = 5;

	if(isset($data['timeout'])) {
		$timeout = $data['timeout'];
	}

	if(($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
		echo "check_tcp_connect: warning: failed to create a socket!\n";
		return array('status' => 'success'); //can't do anything?
	}

	socket_set_option($sock, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $timeout, 'usec' => 0));

	if(socket_connect($sock, $target, $port) === false) {
		socket_close($sock);
		return array('status' => 'fail', 'message' => "Connection to $target:$port failed");
	}

	socket_close($sock);
	return array('status' => 'success');
}

?>
