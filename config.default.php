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

//multi settings
// multi mode allows you to iterate over slaves when running checks
// currently the only purpose is to avoid sending erroneous alerts in case one slave dies
$config['multi_master'] = '1.2.3.4'; //IP that slaves should accept connections from
$config['multi_slaves'] = array('5.6.7.8', 'localhost'); //slave hosts
$config['multi_port'] = 19549; //port to communicate with slaves
$config['multi_contact'] = 'admin@example.com'; //email to alert if a slave fails
$config['multi_secret'] = 'CHANGEMEPLEASE'; //secret to authenticate master with slave (in addition to IP)
$config['multi_interval'] = 30; //interval to execute checks

?>
