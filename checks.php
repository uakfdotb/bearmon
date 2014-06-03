<?php

function check_explode($data) {
	global $config;
	return explode($config['check_delimiter'], $data);
}

function check_http_contains($data) {
	//format: http_target[delimiter]substring
	$parts = check_explode($data);

	if(count($parts) < 2) {
		die("check_http_contains: encountered invalid request data [$data]\n");
	}

	$result = check_http_helper($parts[0]);

	if($result['status'] == 'fail') {
		return $result;
	} else if(strpos($result['content'], $parts[1]) !== false) {
		return array('status' => 'success');
	} else {
		return array('status' => 'fail', 'message' => "target [{$parts[0]}] does not contain string [{$parts[1]}]");
	}
}

function check_http_helper($url) {
	$handle = curl_init($url);

	if($handle === false) {
		return array('status' => 'fail', 'message' => "curl_init: failed to establish connection to target [$url]");
	}

	curl_setopt($handle,  CURLOPT_RETURNTRANSFER, true);
	curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($handle, CURLOPT_AUTOREFERER, true);
	curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($handle, CURLOPT_TIMEOUT, 15);
	$response = curl_exec($handle);

	if($response === false) {
		return array('status' => 'fail', 'message' => "curl_exec: failed to read target [$url]");
	}

	$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
	curl_close($handle);

	return array('status' => 'success', 'code' => $httpCode, 'content' => $response);
}

function check_http_status_helper($url, $expected_status) {
	$result = check_http_helper($url);

	if($result['status'] == 'fail') {
		return $result;
	} else if($result['code'] == $expected_status) {
		return array('status' => 'success');
	} else {
		return array('status' => 'fail', 'message' => "target [$url] returned unexpected status [{$result['code']}], expected [$expected_status]");
	}
}

function check_http_status($data) {
	//format: http_target[delimiter]expected_status
	$parts = check_explode($data);

	if(count($parts) < 2) {
		die("check_http_status: encountered invalid request data [$data]\n");
	}

	return check_http_status_helper($parts[0], $parts[1]);
}

function check_http_ok($data) {
	return check_http_status_helper($data, 200);
}

?>
