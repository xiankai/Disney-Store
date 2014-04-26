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

$curl_factory = new CurlFactory();
$request_factory = new RequestFactory();
$parser = new Disney\Parser();
// Because the default of 5 makes the site choke and return more errors than usual. What.
// $rolling_curl->setSimultaneousLimit(2);

$redis = new Store\Redis(300);
$DisneyStoreUS = new Disney\Store('http://www.disneystore.com', $redis);
$DisneyStoreUS->init($curl_factory, $request_factory, $parser);