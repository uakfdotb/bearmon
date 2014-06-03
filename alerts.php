<?php

/*
* Sends an email to target with the given parameters.
* From address and email method are specified in configuration.
*
* $data: the email address to send to
* $subject: email subject
* $body: email body (plaintext)
*/
function alert_email($data, $subject, $body) {
	$config = $GLOBALS['config'];
	$from = filter_var($config['mail_from'], FILTER_SANITIZE_EMAIL);
	$to = $data;

	if(isset($config['redirect_email']) && $config['redirect_email'] !== false) {
		$body = "This is a redirected email: original to $to\n\n" . $body;
		$to = $config['redirect_email'];
	}

	$to = filter_var($to, FILTER_SANITIZE_EMAIL);

	if($to === false || $from === false) {
		return false;
	}

	if($config['mail_smtp']) {
		require_once "Mail.php";

		$host = $config['mail_smtp_host'];
		$port = $config['mail_smtp_port'];
		$username = $config['mail_smtp_username'];
		$password = $config['mail_smtp_password'];
		$headers = array ('From' => $from,
						  'To' => $to,
						  'Subject' => $subject,
						  'Content-Type' => 'text/plain');
		$smtp = Mail::factory('smtp',
							  array ('host' => $host,
									 'port' => $port,
									 'auth' => true,
									 'username' => $username,
									 'password' => $password));

		$mail = $smtp->send($to, $headers, $body);

		if (PEAR::isError($mail)) {
			return false;
		} else {
			return true;
		}
	} else {
		$headers = "From: $from\r\n";
		$headers .= "Content-type: text/plain\r\n";
		return mail($to, $subject, $body, $headers);
	}
}

/*
* Sends an SMS message via Twilio to the given number.
* The message is "[$title] $message"
* $config must include the strings twilio_accountsid, twilio_authtoken, and twilio_number
*
* $target_number: phone number to send SMS message to.
* $title: SMS title
* $message: SMS body
*/
function alert_sms_twilio($target_number, $title, $message) {
	global $config;
	require_once('twilio-php/Services/Twilio.php');

	$message = "[$title] $message";
	$client = new Services_Twilio($config['twilio_accountsid'], $config['twilio_authtoken']);

	try {
		$params = array();
		$params['From'] = $config['twilio_number'];
		$params['To'] = $target_number;
		$params['Body'] = $message;
		$message = $client->account->messages->create($params);
		return true;
	} catch(Services_Twilio_RestException $e) {
		echo 'alert_sms_twilio: error: ' . $e->getMessage() . "\n";
		return false;
	}
}

?>
