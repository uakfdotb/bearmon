<?php

//database settings (MySQL)
$config['db_name'] = 'monitor';
$config['db_host'] = 'localhost';
$config['db_username'] = 'monitor';
$config['db_password'] = '';

//mail settings
// set mail_smtp to true if you would like to send mail via SMTP instead of PHP mail()
$config['mail_from'] = 'noreply@example.com';
$config['mail_smtp'] = true;
$config['mail_smtp_host'] = 'tls://example.com';
$config['mail_smtp_port'] = '465';
$config['mail_smtp_username'] = 'monitor';
$config['mail_smtp_password'] = 'password';

//delimiter to use for check data
$config['check_delimiter'] = '|';
$config['alert_delimiter'] = '|';

//daemon settings
$config['sleep_interval'] = 5; //seconds to sleep when idle

?>
