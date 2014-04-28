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
		$notify = $this->store->count() < 1;

		$messages = array();

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
					\stringContains($item->title, 'Create Your Own')
				)
			) {
				$this->store->set($item->title, NO_STOCK);
				$messages[] = $this->parser->generateMessage($item, $this->base_url, $this->locale, NO_STOCK);
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

		$messages = array_merge($messages, $this->checkStock($this->rolling_curl->getCompletedRequests()));

		if ($notify) {
			$this->notify($messages);
		}
	}

	private function checkStock($requests) {
		$messages = array();

		foreach ($requests as $request) {
			$item = $request->getExtraInfo();
			$stock = $this->parser->validateStock($request->getResponseText(), $item->productId);

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
				|| $item->stock !== IN_STOCK && $stock === IN_STOCK
				// Or if recovered from error
				|| $item->stock === UNKNOWN_ERROR && $stock !== UNKNOWN_ERROR
			) {
				$messages[] = $this->parser->generateMessage($item, $this->base_url, $this->locale, $stock);
			}
		}

		return $messages;
	}

	private function notify($messages) {
		$new = $old = $restock = 0;
		$html = "";

		foreach ($messages as $message) {
			$new += $message['new'];
			$old += $message['old'];
			$restock += $message['restock'];
			$html .= $message['html'];
		}

		$this->notifier->add($this->locale, $html, $new, $old, $restock);
	}
}