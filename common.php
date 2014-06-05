<?php

function string_begins_with($string, $search)
{
	return (strncmp($string, $search, strlen($search)) == 0);
}

function boolToString($bool) {
	return $bool ? 'true' : 'false';
}

function uid($length) {
	$characters = "0123456789abcdefghijklmnopqrstuvwxyz";
	$string = "";

	for ($p = 0; $p < $length; $p++) {
		$string .= $characters[secure_random() % strlen($characters)];
	}

	return $string;
}

function isAscii($str) {
    return 0 == preg_match('/[^\x00-\x7F]/', $str);
}

function array_splice_assoc(&$input, $offset, $length, $replacement) {
	$replacement = (array) $replacement;
	$key_indices = array_flip(array_keys($input));
	if (isset($input[$offset]) && is_string($offset)) {
		$offset = $key_indices[$offset];
	}
	if (isset($input[$length]) && is_string($length)) {
		$length = $key_indices[$length] - $offset;
	}

	$input = array_slice($input, 0, $offset, TRUE)
		+ $replacement
		+ array_slice($input, $offset + $length, NULL, TRUE);
}

//returns random number from 0 to 2^24
function secure_random() {
	return hexdec(bin2hex(secure_random_bytes(3)));
}

function recursiveDelete($dirPath) {
	foreach(
		new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$dirPath, FilesystemIterator::SKIP_DOTS
			),
			RecursiveIteratorIterator::CHILD_FIRST
		)
		as $path) {
		$path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
	}

	rmdir($dirPath);
}

function monitor_get_backtrace() {
	$array = debug_backtrace();
	$str = "";
	$counter = 0;

	foreach($array as $el) {
		$str .= "#$counter\t" . $el['function'] . '(';

		$first = true;
		foreach($el['args'] as $arg) {
			if($first) {
				$first = false;
			} else {
				$str .= ',';
			}

			$str .= print_r($arg, true);
		}

		$str .= ") called at {$el['file']}:{$el['line']}\n";
		$counter++;
	}

	return htmlspecialchars($str);
}

function shuffle_assoc($list) {
	if (!is_array($list)) return $list;
	$keys = array_keys($list);
	shuffle($keys);
	$random = array();
	foreach ($keys as $key) {
		$random[$key] = $list[$key];
	}
	return $random;
}

function get_if_exists($array, $key, $default = false) {
	if(isset($array[$key])) {
		return $array[$key];
	} else {
		return $default;
	}
}

//secure_random_bytes from https://github.com/GeorgeArgyros/Secure-random-bytes-in-PHP
/*
* The function is providing, at least at the systems tested :),
* $len bytes of entropy under any PHP installation or operating system.
* The execution time should be at most 10-20 ms in any system.
*/
function secure_random_bytes($len = 10) {

   /*
* Our primary choice for a cryptographic strong randomness function is
* openssl_random_pseudo_bytes.
*/
   $SSLstr = '4'; // http://xkcd.com/221/
   if (function_exists('openssl_random_pseudo_bytes') &&
       (version_compare(PHP_VERSION, '5.3.4') >= 0 ||
substr(PHP_OS, 0, 3) !== 'WIN'))
   {
      $SSLstr = openssl_random_pseudo_bytes($len, $strong);
      if ($strong)
         return $SSLstr;
   }

   /*
* If mcrypt extension is available then we use it to gather entropy from
* the operating system's PRNG. This is better than reading /dev/urandom
* directly since it avoids reading larger blocks of data than needed.
* Older versions of mcrypt_create_iv may be broken or take too much time
* to finish so we only use this function with PHP 5.3 and above.
*/
   if (function_exists('mcrypt_create_iv') &&
      (version_compare(PHP_VERSION, '5.3.0') >= 0 ||
       substr(PHP_OS, 0, 3) !== 'WIN'))
   {
      $str = mcrypt_create_iv($len, MCRYPT_DEV_URANDOM);
      if ($str !== false)
         return $str;
   }


   /*
* No build-in crypto randomness function found. We collect any entropy
* available in the PHP core PRNGs along with some filesystem info and memory
* stats. To make this data cryptographically strong we add data either from
* /dev/urandom or if its unavailable, we gather entropy by measuring the
* time needed to compute a number of SHA-1 hashes.
*/
   $str = '';
   $bits_per_round = 2; // bits of entropy collected in each clock drift round
   $msec_per_round = 400; // expected running time of each round in microseconds
   $hash_len = 20; // SHA-1 Hash length
   $total = $len; // total bytes of entropy to collect

   $handle = @fopen('/dev/urandom', 'rb');
   if ($handle && function_exists('stream_set_read_buffer'))
      @stream_set_read_buffer($handle, 0);

   do
   {
      $bytes = ($total > $hash_len)? $hash_len : $total;
      $total -= $bytes;

      //collect any entropy available from the PHP system and filesystem
      $entropy = rand() . uniqid(mt_rand(), true) . $SSLstr;
      $entropy .= implode('', @fstat(@fopen( __FILE__, 'r')));
      $entropy .= memory_get_usage();
      if ($handle)
      {
         $entropy .= @fread($handle, $bytes);
      }
      else
      {
         // Measure the time that the operations will take on average
         for ($i = 0; $i < 3; $i ++)
         {
            $c1 = microtime(true);
            $var = sha1(mt_rand());
            for ($j = 0; $j < 50; $j++)
            {
               $var = sha1($var);
            }
            $c2 = microtime(true);
     $entropy .= $c1 . $c2;
         }

         // Based on the above measurement determine the total rounds
         // in order to bound the total running time.
         $rounds = (int)($msec_per_round*50 / (int)(($c2-$c1)*1000000));

         // Take the additional measurements. On average we can expect
         // at least $bits_per_round bits of entropy from each measurement.
         $iter = $bytes*(int)(ceil(8 / $bits_per_round));
         for ($i = 0; $i < $iter; $i ++)
         {
            $c1 = microtime();
            $var = sha1(mt_rand());
            for ($j = 0; $j < $rounds; $j++)
            {
               $var = sha1($var);
            }
            $c2 = microtime();
            $entropy .= $c1 . $c2;
         }

      }
      // We assume sha1 is a deterministic extractor for the $entropy variable.
      $str .= sha1($entropy, true);
   } while ($len > strlen($str));

   if ($handle)
      @fclose($handle);

   return substr($str, 0, $len);
}

$MONITOR_LOCK_FILENAME = NULL;
$MONTOR_LOCK_FH = NULL;

function monitor_lock_init($name) {
	global $MONITOR_LOCK_FH, $MONITOR_LOCK_FILENAME;

	monitor_lock_release();
	$MONITOR_LOCK_FILENAME = '/var/lock/' . md5($name) . '.pid';
	$MONITOR_LOCK_FH = @fopen($MONITOR_LOCK_FILENAME, 'a');

	if(!$MONITOR_LOCK_FH || !flock($MONITOR_LOCK_FH, LOCK_EX | LOCK_NB, $eWouldBlock) || $eWouldBlock) {
		die("Failed to acquire lock.\n");
	} else {
		register_shutdown_function('monitor_lock_release');
	}
}

function monitor_lock_release() {
	global $MONITOR_LOCK_FH, $MONITOR_LOCK_FILENAME;

	if($MONITOR_LOCK_FH !== NULL && $MONITOR_LOCK_FILENAME !== NULL) {
		fclose($MONITOR_LOCK_FH);
		unlink($MONITOR_LOCK_FILENAME);

		$MONITOR_LOCK_FH = NULL;
		$MONITOR_LOCK_FILENAME = NULL;
	}
}

function monitor_decode($data) {
	$array = array();
	$parts = explode('&', $data);

	foreach($parts as $part) {
		$keys = explode('=', $part);

		if(count($keys) >= 2) {
			$k = urldecode($keys[0]);
			$v = urldecode($keys[1]);
			$array[$k] = $v;
		}
	}

	return $array;
}

?>
