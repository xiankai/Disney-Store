<?

require 'vendor/autoload.php';

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

$configs = array(
	array(
		'db' => 0,
		'url' => 'http://www.disneystore.com',
		'store_id' => 10051,
		'frozen_id' => 1021701,
		'locale' => 'us',
	),
	array(
		'db' => 1,
		'url' => 'http://www.disneystore.co.uk',
		'store_id' => 30053,
		'frozen_id' => 1340001,
		'locale' => 'uk'
	),
	array(
		'db' => 2,
		'url' => 'http://www.disneystore.co.de',
		'store_id' => 70051,
		'frozen_id' => 1431501,
		'locale' => 'de',
	),
	array(
		'db' => 3,
		'url' => 'http://www.disneystore.co.fr',
		'store_id' => 60051,
		'frozen_id' => 1435501,
		'locale' => 'fr',
	),
);

$curl_factory = new CurlFactory();
$request_factory = new RequestFactory();
$parser = new Disney\Parser();
// Because the default of 5 makes the site choke and return more errors than usual. What.
// $rolling_curl->setSimultaneousLimit(2);

foreach ($configs as $config) {
	$redis = new Store\Redis($config['db'], 300);
	$store = new Disney\Store($redis, $config);
	$store->init($curl_factory, $request_factory, $parser);
}