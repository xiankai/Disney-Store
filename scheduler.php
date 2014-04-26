<?

require 'vendor/autoload.php';

// $test_store = new Store\MockStore();
// $test_disney = new Disney\Store('elsa', $test_store);

// $test_curl = new Mock\CurlFactory();
// $test_request = new Mock\RequestFactory();
// $test_parser = new Mock\Parser();
// $test_disney->init($test_curl, $test_request, $test_parser);

// die();

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