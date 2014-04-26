<?

require 'vendor/autoload.php';

$test_store = new Store\MockStore();
$test_disney = new Disney\Store('elsa', $test_store);

$test_curl = new Mock\CurlFactory();
$test_request = new Mock\RequestFactory();
$test_parser = new Mock\Parser();
$test_disney->init($test_curl, $test_request, $test_parser);