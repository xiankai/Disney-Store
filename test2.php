<?

require 'vendor/autoload.php';

$base_url = 'http://www.disneystore.com';

$curl = new RollingCurl\RollingCurl();

$response = $curl
	->get($base_url . '/disney/store/DSIProcessWidget?storeId=10051&N=1021701&templateId=Width-3_4-ProductList&navNum=96')
	->addOptions(array(
		CURLOPT_HEADER => true,
		CURLOPT_COOKIESESSION => true,
	))
	->setCallback(function($request) {
		$info = $request->getResponseInfo();
		$header = substr($request->getResponseText(), 0, $info['header_size']);
		$body = substr($request->getResponseText(), $info['header_size']);

		$matches = array();
		preg_match('/Set-Cookie: (WC_USERACTIVITY.*);/', $header, $matches);
		if (!empty($matches[1])) {
			var_dump($matches[1]);
		}
	})
	->execute();