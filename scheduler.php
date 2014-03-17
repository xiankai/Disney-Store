<?

require 'vendor/autoload.php';

$base_url = 'http://www.disneystore.com';

$curl = new RollingCurl\RollingCurl();
$client = new Predis\Client();

$response = $curl
	->get($base_url . '/disney/store/DSIProcessWidget?Nr=AND(pPublished%3A1%2CpBuyable%3A1)&Ne=1000201&categoryId=306000&storeId=10051&N=1021701+1000208&templateId=Width-3_4-ProductList&initialN=1021701&navNum=96');
	->setCallback('parseStoreListing')
	->execute();

function parseStoreList($request) {
	$rolling_curl = new RollingCurl\RollingCurl();

	$items = json_decode($request->getResponseText());
	foreach ($items->items as $item) {
		// New item or out of stock
		if (!$client->get($item->title)) {
			$curl = new RollingCurl\Request('http://www.disneystore.com/disney/store/DSIAjaxOrderItemAdd', 'POST');
			$curl->setExtraInfo($item);
			$curl->setPostData(array(
				'quantity' => 1,
				'originalStoreId' => 10051,
				'orderId' => '.',
				'widget' => 'addToBag',
				'catEntryId' => $item->productId,
			));

			$rolling_curl->addRequest($curl);
		} else {
			updateRedis();
		}
	}

	$rolling_curl->setCallback(function($request, $rolling_curl) {
		$item = $request->getExtraInfo();

		// Curl for an item and request returns a positive
		if ($item && stripos($request->getResponseText()) !== false) {
			$message = $item->title . ': ' . $base_url . $item->link . PHP_EOL;
			$message .= 'Image: ' . $item->imageUrl . PHP_EOL . PHP_EOL;

			// Push notification curl onto the stack
			$rolling_curl->post('https://api.pushover.net/1/messages.json', array(
				'token' => 'aSruoKSByoBRHJfdx5ZTDZZEindFiE',
				'user' => 'u4en9LeaFguiSD4gAewuRXkydRaKGw',
				'message' => $message,
			));

			updateRedis();
		}
	});
}

function updateRedis() {
	// value isn't important
	$client->set($item->title, 1);

	// Expire in one day, to account for possible downtime.
	$client->expire($item->title, 3600 * 24);
}