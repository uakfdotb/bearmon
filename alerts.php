<?php

/*
* Sends an email to target with the given parameters.
* From address and email method are specified in configuration.
*
* $data['email']: the email address to send to
* $context['title']: email subject
* $context['message']: email body (plaintext)
*/
function alert_email($data, $context) {
	if(!isset($data['email'])) {
		die("alert_email: missing email\n");
	}

	$config = $GLOBALS['config'];
	$from = filter_var($config['mail_from'], FILTER_SANITIZE_EMAIL);
	$to = $data['email'];

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
						  'Subject' => $context['title'],
						  'Content-Type' => 'text/plain');
		$smtp = Mail::factory('smtp',
							  array ('host' => $host,
									 'port' => $port,
									 'auth' => true,
									 'username' => $username,
									 'password' => $password));

		$mail = $smtp->send($to, $headers, $context['message']);

		if (PEAR::isError($mail)) {
			return false;
		} else {
			return true;
		}
	} else {
		$headers = "From: $from\r\n";
		$headers .= "Content-type: text/plain\r\n";
		return mail($to, $context['title'], $context['message'], $headers);
	}
}

/*
* Sends an SMS message via Twilio to the given number.
* The message is "[$title] $message"
* $config must include the strings twilio_accountsid, twilio_authtoken, and twilio_number
*
* $data['number']: phone number to send SMS message to.
* $data['twilio_accountsid'], $data['twilio_authtoken'], $data['twilio_number']: optional Twilio configuration
* $context['title']: used in creating SMS message
* $context['message']: used in creating SMS message
*/
function alert_sms_twilio($data, $context) {
	global $config;
	require_once('twilio-php/Services/Twilio.php');

	if(!isset($data['number'])) {
		die("alert_sms_twilio: missing number\n");
	}

	$config_target = $config;

	if(isset($data['twilio_accountsid']) && isset($data['twilio_authtoken']) && isset($data['twilio_number'])) {
		$config_target = $data;
	}

	$sms_message = "[{$context['title']}] {$context['message']}";
	$client = new Services_Twilio($config_target['twilio_accountsid'], $config_target['twilio_authtoken']);

	try {
		$params = array();
		$params['From'] = $config_target['twilio_number'];
		$params['To'] = $data['number'];
		$params['Body'] = $sms_message;
		$message = $client->account->messages->create($params);
		return true;
	} catch(Services_Twilio_RestException $e) {
		echo 'alert_sms_twilio: error: ' . $e->getMessage() . "\n";
		return false;
	}
}

?>
