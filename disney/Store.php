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

	public function __construct($store, $config, $notifier) {
		$this->store = $store;
		$this->notifier = $notifier;

		$this->base_url = $config['url'];
		$this->store_id = $config['store_id'];
		$this->frozen_id = $config['frozen_id'];
		$this->locale = $config['locale'];
	}

	// Entrypoint
	public function init($curl_factory, $request_factory, $parser) {
		$this->curl_factory = $curl_factory;
		$this->request_factory = $request_factory;
		$this->parser = $parser;

		$params = http_build_query(array(
			'storeId' => $this->store_id,
			'N' => $this->frozen_id,
			'templateId' => 'Width-3_4-ProductList',
			'navNum' => 96,
		));

		$curl = $this->curl_factory->instantiate();
		$curl->get($this->base_url . '/disney/store/DSIProcessWidget?' . $params)
			->addOptions(array(
				CURLOPT_HEADER => true,
				CURLOPT_COOKIESESSION => true,
			))
			->execute();

		$requests = $curl->getCompletedRequests();
		$response = $this->parser->validateListing($requests[0]);

		// Notify only if there are existing items. If 0, a flood will be caused.
		$notify = $this->store->count() > 0;

		$items = $this->updateStock($response['items'], $response['cookies']);

		$notify_items = $this->processStock($items);
		
		if ($notify) {
			$this->notify($notify_items);
		}
	}

	public function updateStock($items, $cookies) {
		$curl = $this->curl_factory->instantiate();

		foreach ($items as $key => $item) {
			// Some store items have same exact names. Use product ID to distinguish.
			$item->title .= " ({$item->productId})";
			$item->status = $this->store->keyExists($item->title) ? OLD_ENTRY : NEW_ENTRY;
			$item->stock = $this->store->get($item->title);

			if (
				// Collections are basically groups of items that may or may not be elsewhere in the listings.
				$item->isCollection === "true" ||
				// Customizable items cannot have stock tracked	
				\stringContains($item->title, 'Create Your Own') || 
				// Already confirmed to be not stocked
				$item->stock === NO_STOCK
			) {
				$item->new_stock = NO_STOCK;
			} else{
				// Do stock-checking
				$this->checkStock($curl, $item, $cookies);

				// Will get the updated item later
				unset($items[$key]);
			}
		}

		// Get results of stock-checking
		$curl->execute();

		foreach ($curl->getCompletedRequests() as $request) {
			$item = $request->getExtraInfo();
			$item->new_stock = $this->parser->validateStock($request->getResponseText(), $item->productId);

			// Add back updated item
			$items[] = $item;
		}

		return $items;
	}

	public function checkStock($curl, $item, $cookies) {
		// Check for stock
		$request = $this->request_factory->instantiate($this->base_url . '/disney/store/DSIAjaxOrderItemAdd', 'POST');

		// Set required cookies
		$request->addOptions(array(
			CURLOPT_COOKIESESSION => true,
			CURLOPT_COOKIE => $cookies,
		))
		->setExtraInfo($item)
		->setPostData(array(
			'quantity' => 1,
			'originalStoreId' => $this->store_id,
			'catEntryId' => $item->productId,
		));

		$curl->add($request);
	}

	public function processStock($items) {
		$notify_items = array();

		foreach ($items as $item) {
			if ($item->status === OLD_ENTRY && $item->new_stock === UNKNOWN_ERROR) {
				// Don't update old entries with the "new error" - refresh the existing stock status
				$this->store->set($item->title, $item->stock);
			} else {
				$this->store->set($item->title, $item->new_stock);
			}

			if (
				// Notify if new entry
				$item->status === NEW_ENTRY 
				// Or if restocked
				|| $item->stock !== IN_STOCK && $item->new_stock === IN_STOCK
				// Or if recovered from error
				|| $item->stock === UNKNOWN_ERROR && $item->new_stock !== UNKNOWN_ERROR
			) {
				$notify_items[] = $item;
			}
		}

		return $notify_items;
	}

	public function notify($items) {
		$new = $old = $restock = 0;
		$html = "";

		foreach ($items as $item) {
			$message = $this->parser->generateMessage($item, $this->base_url, $this->locale);
			$new += $message['new'];
			$old += $message['old'];
			$restock += $message['restock'];
			$html .= $message['html'];
		}

		$this->notifier->add($this->locale, $html, $new, $old, $restock);
	}
}