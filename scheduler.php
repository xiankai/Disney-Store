<?

$then = microtime(true);

require 'vendor/autoload.php';

$external_config = parse_ini_file('/repos/config/disney.ini', true);

date_default_timezone_set($external_config['timezone']);

class CurlFactory {

	function instantiate(/* Variadic */) {
		$reflection = new ReflectionClass('\\RollingCurl\\RollingCurl');
		return $reflection->newInstanceArgs(func_get_args());
	}

}

class RequestFactory {

	function instantiate(/* Variadic */) {
		$reflection = new ReflectionClass('\\RollingCurl\\Request');
		return $reflection->newInstanceArgs(func_get_args());
	}

}

// Helper
function stringContains($haystack, $needle) {
	return stripos($haystack, $needle) !== false;
}

$mailchimp = new Disney\MailChimp($external_config['mailchimp']);

$curl_factory = new CurlFactory();
$request_factory = new RequestFactory();
$parser = new Disney\Parser();
// Because the default of 5 makes the site choke and return more errors than usual. What.
// $rolling_curl->setSimultaneousLimit(2);

$date = date('Y-m-d H:i:s');

try {
	foreach ($external_config['disney'] as $config) {
		$redis = new Store\Redis($config['db'], 300);
		$store = new Disney\Store($redis, $config, $mailchimp);
		$store->init($curl_factory, $request_factory, $parser);
	}	
} catch (Disney\DisneyException $e) {
	// In the event of failure, refresh keys indefinitely.
	foreach ($redis->getAll() as $key) {
		$redis->refresh($key);
	}

	// Store the error message in redis in lieu of execution time
	$message = $config['locale'] . ': ' . $e->getMessage();
	$redis->set($date, $message);

	// Echo to cronjob for mail notifications
	echo PHP_EOL . $message;
}

$mailchimp->send($date);

$now = microtime(true);

$redis = new Store\Redis(15, 0);
$redis->store->set($date, $now - $then);