<?

require 'vendor/autoload.php';

$base_url = 'http://www.disneystore.com';
$client = new Predis\Client();

define('IN_STOCK', "1");
define('OUT_OF_STOCK', "2");
define('NO_STOCK', "3");
define('UNKNOWN_ERROR', "4");

// Prime numbers
define('NEW_ENTRY', "5");
define('OLD_ENTRY', "7");

// Disney-specifc
define('FROZEN', 1021701);

function stringContains($haystack, $needle) {
	return stripos($haystack, $needle) !== false;
}

$notify = function ($item, $stock) use ($base_url) {
	$message = $item->title . ': ' . $base_url . $item->link . PHP_EOL;
	$message .= 'Image: ' . $item->imageUrl . PHP_EOL;

	switch ($item->status) {
		case NEW_ENTRY: 
			$message .= 'Status: ' . 'New';
			$snippet = 'A new item ';
			break;
		case OLD_ENTRY: 
			$message .= 'Status: ' . 'Old';
			$snippet = 'An existing item ';
			break;
	}

	$message .= PHP_EOL . 'Stock: ';
	
	switch ($stock) {
		case IN_STOCK: 
			$message .= 'Yes';
			$snippet .= ' has been restocked!';
			break;
		case OUT_OF_STOCK: 
			$message .= 'No';
			$snippet .= ' has been listed but is out of stock.';
			break;
		case NO_STOCK: 
			$message .= 'N/A';
			$snippet .= ' has been listed!';
			break;
		case UNKNOWN_ERROR: 
			$message .= 'Could not check';
			$snippet .= ' has been listed but we could not check its stock.';
			break;
	}

	// Push notification curl onto the stack
	$rolling_curl->post('https://api.pushover.net/1/messages.json', array(
		'token' => 'aSruoKSByoBRHJfdx5ZTDZZEindFiE',
		'user' => 'u4en9LeaFguiSD4gAewuRXkydRaKGw',
		'message' => $message,
	));
};

$setStore = function($key, $value) use ($client) {
	$client->set($key, $value);
};

$getStore = function($key) use ($client) {
	return $client->get($key);
};

$keyExistsStore = function($key) use ($client) {
	return !is_null($client->get($key));
};

$checkStock = function($request) use ($base_url, $notify, $setStore) {
	$item = $request->getExtraInfo();
	$message = $request->getResponseText();

	if (stringContains($message, '_ERR_PROD_NOT_ORDERABLE')) {
		$stock = OUT_OF_STOCK;
	} elseif (stringContains($message, '_ERR_GETTING_SKU')) {
		$stock = NO_STOCK;
	} elseif (stringContains($message, '"catEntryId": "' . $item->productId . '"')) {
		$stock = IN_STOCK;
	} else {
		$stock = UNKNOWN_ERROR;
	}

	if ($item->status === OLD_ENTRY && $stock === UNKNOWN_ERROR) {
		// Don't update old entries with "error"
	} else {
		$setStore($item->title, $stock);
	}

	// Notify if new entry or if newly in stock.
	if ($item->status === NEW_ENTRY 
		|| $item->stock !== IN_STOCK && $stock === IN_STOCK
		|| $item->stock === UNKNOWN_ERROR && $stock !== UNKNOWN_ERROR) {
		$notify($item, $stock);
	}
};

$parseStoreList = function ($request) use ($base_url, $checkStock, $notify, $getStore, $setStore, $keyExistsStore) {
	$rolling_curl = new RollingCurl\RollingCurl();
	$rolling_curl->setCallback($checkStock);

	// Because the default of 5 makes the site choke and return more errors than usual. What.
	$rolling_curl->setSimultaneousLimit(2);

	// Split headers and body
	$info = $request->getResponseInfo();
	$header = substr($request->getResponseText(), 0, $info['header_size']);
	$body = substr($request->getResponseText(), $info['header_size']);

	// Get required cookies from header
	$cookies = array();
	$results = preg_match_all('/Set-Cookie: (.*?);/', $header, $cookies);
	if (is_int($results) && $results > 0) {
		$cookies = implode('; ', $cookies[1]) . ';';
	} else {
		echo $request->getResponseText();
		throw new DisneyException('Unable to get required cookie');
	}

	$items = json_decode($body);

	if (is_null($items)) {
		// This is as 'items'  key is not present, so there is a trailing comma in the JSON string which renders it invalid.
		// Great job, Disney.
		throw new DisneyException('No items found.'); 
	}

	foreach ($items->items as $item) {
		// Some store items have same exact names. Use product ID to distinguish.
		$item->title .= " ({$item->productId})";
		$item->status = $keyExistsStore($item->title) ? OLD_ENTRY : NEW_ENTRY;
		$item->stock = $getStore($item->title);

		if (
			$item->status === NEW_ENTRY && (
				// Collections are basically groups of items that may or may not be elsewhere in the listings.
				$item->isCollection === "true" ||
				// Customizable items cannot have stock tracked	
				stringContains($item->title, 'Create Your Own')
			)
		) {
			$setStore($item->title, NO_STOCK);
			$notify($item, NO_STOCK);
			continue;
		}

		// Refresh timer
		if ($item->stock === NO_STOCK) {
			$setStore($item->title, NO_STOCK);
			continue;
		}

		// Check for stock
		$curl = new RollingCurl\Request($base_url . '/disney/store/DSIAjaxOrderItemAdd', 'POST');

		// Set required cookies
		$curl->addOptions(array(
			CURLOPT_COOKIESESSION => true,
			CURLOPT_COOKIE => $cookies,
		))
		->setExtraInfo($item)
		->setPostData(array(
			'quantity' => 1,
			'originalStoreId' => 10051,
			'catEntryId' => $item->productId,
		));

		$rolling_curl->add($curl);
	}

	$rolling_curl->execute();
};

$params = http_build_query(array(
	'storeId' => 10051,
	'N' => FROZEN,
	'templateId' => 'Width-3_4-ProductList',
	'navNum' => 96,
));

$curl = new RollingCurl\RollingCurl();
$response = $curl
	->get($base_url . '/disney/store/DSIProcessWidget?' . $params)
	->addOptions(array(
		CURLOPT_HEADER => true,
		CURLOPT_COOKIESESSION => true,
	))
	->setCallback($parseStoreList)
	->execute();

class DisneyException extends Exception {}