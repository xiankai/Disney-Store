<?

require 'vendor/autoload.php';

$base_url = 'http://www.disneystore.com';

$rolling_curl = new RollingCurl\RollingCurl();

$rolling_curl->setCallback(function($request, $rolling_curl) {
	echo $request->getResponseText();

	return;
});

$curl = new RollingCurl\Request($base_url . '/disney/store/DSIAjaxOrderItemAdd', 'POST');
$curl->addOptions(array(
	CURLOPT_COOKIESESSION => true,
	CURLOPT_COOKIE => implode('; ', array(
'WC_USERACTIVITY_-1002=%2d1002%2c10051%2cnull%2cnull%2cnull%2cnull%2cnull%2cnull%2cnull%2cnull%2cv0wgJpQUdavyLOm0TSWCtP2oXs27AUwuCGRvdS2FXfAeybkqfm8o%2bj49Cv8%2bHKyPsfFfWURD9KZ4%0asr1MfGVbAUgsIjjPzwdixQ67JaRtDrzQeVYuoyPzx%2fpMZYcYkX%2f4Yi3UaeNkTpctCKBKbi5ZKg%3d%3d',
'WC_ACTIVEPOINTER=%2d1%2c10051',
	)),
));

$curl->setPostData(array(
	'quantity' => 1,
	'originalStoreId' => 10051,
	'catEntryId' => 1349621,
));

$rolling_curl->add($curl)->execute();
