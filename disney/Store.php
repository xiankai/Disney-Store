<?

namespace Disney;

define('IN_STOCK', "1");
define('OUT_OF_STOCK', "2");
define('NO_STOCK', "3");
define('UNKNOWN_ERROR', "4");

define('NEW_ENTRY', "5");
define('OLD_ENTRY', "7");

class Store {
	private $base_url;
	private $logger;
	private $store;
	private $rolling_curl;

	private $store_id;
	private $frozen_id;

	private $notify = true;

	public function __construct($store, $config) {
		$this->store = $store;

		$this->base_url = $config['url'];
		$this->store_id = $config['store_id'];
		$this->frozen_id = $config['frozen_id'];
		$this->locale = $config['locale'];
	}

	// Helper
	public function stringContains($haystack, $needle) {
		return stripos($haystack, $needle) !== false;
	}

	// Entrypoint
	public function init($curl_factory, $request_factory, $parser) {
		$this->rolling_curl = $curl_factory->instantiate();
		$this->request_factory = $request_factory;
		$this->parser = $parser;

		$params = http_build_query(array(
			'storeId' => $this->store_id,
			'N' => $this->frozen_id,
			'templateId' => 'Width-3_4-ProductList',
			'navNum' => 96,
		));

		$curl = $curl_factory->instantiate();
		$response = $curl
			->get($this->base_url . '/disney/store/DSIProcessWidget?' . $params)
			->addOptions(array(
				CURLOPT_HEADER => true,
				CURLOPT_COOKIESESSION => true,
			))
			->setCallback(array($this, 'parseStoreList'))
			->execute();
	}

	public function parseStoreList($request) {
		$response = $this->parser->validateListing($request);

		// No existing items. Let's not notify or a flood will be caused.
		if ($this->store->count() < 1) {
			$this->notify = false;
		}

		foreach ($response['items']->items as $item) {
			// Some store items have same exact names. Use product ID to distinguish.
			$item->title .= " ({$item->productId})";
			$item->status = $this->store->keyExists($item->title) ? OLD_ENTRY : NEW_ENTRY;
			$item->stock = $this->store->get($item->title);

			if (
				$item->status === NEW_ENTRY && (
					// Collections are basically groups of items that may or may not be elsewhere in the listings.
					$item->isCollection === "true" ||
					// Customizable items cannot have stock tracked	
					$this->stringContains($item->title, 'Create Your Own')
				)
			) {
				$this->store->set($item->title, NO_STOCK);
				$this->notify($item, NO_STOCK);
				continue;
			}

			// Refresh timer
			if ($item->stock === NO_STOCK) {
				$this->store->set($item->title, NO_STOCK);
				continue;
			}

			// Check for stock
			$curl = $this->request_factory->instantiate($this->base_url . '/disney/store/DSIAjaxOrderItemAdd', 'POST');

			// Set required cookies
			$curl->addOptions(array(
				CURLOPT_COOKIESESSION => true,
				CURLOPT_COOKIE => $response['cookies'],
			))
			->setExtraInfo($item)
			->setPostData(array(
				'quantity' => 1,
				'originalStoreId' => $this->store_id,
				'catEntryId' => $item->productId,
			));

			$this->rolling_curl->add($curl);
		}

		$this->rolling_curl->execute();

		$this->checkStock($this->rolling_curl->getCompletedRequests(), true);
	}

	private function checkStock($requests) {
		$new = $old = $restock = 0;
		$plaintext = $html = "";

		foreach ($requests as $request) {
			$item = $request->getExtraInfo();
			$response = $request->getResponseText();

			if ($this->stringContains($response, '_ERR_PROD_NOT_ORDERABLE')) {
			$stock = OUT_OF_STOCK;
			} elseif ($this->stringContains($response, '_ERR_GETTING_SKU')) {
			$stock = NO_STOCK;
			} elseif ($this->stringContains($response, '"catEntryId": "' . $item->productId . '"')) {
			$stock = IN_STOCK;
		} else {
			$stock = UNKNOWN_ERROR;
		}

		if ($item->status === OLD_ENTRY && $stock === UNKNOWN_ERROR) {
			// Don't update old entries with "error" - refresh expiry in the meantime
			$this->store->set($item->title, $item->stock);
		} else {
			$this->store->set($item->title, $stock);
		}

		if (
			// Notify if new entry
			$item->status === NEW_ENTRY 
			// Or if restocked
				|| $item->stock !== IN_STOCK && $item->new_stock === IN_STOCK
			// Or if recovered from error
				|| $item->stock === UNKNOWN_ERROR && $item->new_stock !== UNKNOWN_ERROR
		) {

			} else {
				continue;
		}

		$message = strtoupper($this->locale) . ' ' . $item->title . ': ' . $this->base_url . $item->link . PHP_EOL;
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
				$level = 'notice';
				break;
			case OUT_OF_STOCK: 
				$message .= 'No';
				$snippet .= ' has been listed but is out of stock.';
				$level = 'info';
				break;
			case NO_STOCK: 
				$message .= 'N/A';
				$snippet .= ' has been listed!';
				$level = 'notice';
				break;
			case UNKNOWN_ERROR: 
				$message .= 'Could not check';
				$snippet .= ' has been listed but we could not check its stock.';
				$level = 'info';
				break;
		}

		$item->new_stock = $stock;

		$this->rolling_curl->post('https://api.pushover.net/1/messages.json', array(
			'token' => 'aSruoKSByoBRHJfdx5ZTDZZEindFiE',
			'user' => 'u4en9LeaFguiSD4gAewuRXkydRaKGw',
			'message' => $message,
		));
	}
}