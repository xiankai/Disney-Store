<?

namespace Disney;

class Parser {

	public function validateListing($request) {
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
			echo $request->getResponseText();
			throw new DisneyException('No items found.'); 
		}

		return array(
			'items' => $items->items,
			'cookies' => $cookies,
		);
	}

	public function validateStock($response, $product_id) {
		if (\stringContains($response, '_ERR_PROD_NOT_ORDERABLE')) {
			return OUT_OF_STOCK;
		} elseif (\stringContains($response, '_ERR_GETTING_SKU')) {
			return NO_STOCK;
		} elseif (\stringContains($response, '"catEntryId": "' . $product_id . '"')) {
			return IN_STOCK;
		} else {
			return UNKNOWN_ERROR;
		}
	}

	public function generateMessage($item, $base_url, $locale) {
		$new = $old = $restock = 0;

		$html = "<a href='" . $base_url . "'>" . $item->title . "</a><br/>";
		$html .= "<img src='" . $item->imageUrl . "'/><br/>";
		$html .= "Price: " . $item->price . "<br/>";

		if ($item->discount) {
			$html .= "Discount: " . $item->discount . "<br/>";
		}

		$html .= "Status: ";
		
		switch ($item->status) {
			case NEW_ENTRY: 
				$html .= 'New';
				$new++;
				break;
			case OLD_ENTRY: 
				$html .= 'Existing';
				$old++;
				break;
		}

		$html .= "<br/>Stock: ";
		
		switch ($item->new_stock) {
			case IN_STOCK: 
				$html .= 'Yes';
				$restock++;
				break;
			case OUT_OF_STOCK: 
				$html .= 'No';
				break;
			case NO_STOCK: 
				$html .= 'N/A';
				break;
			case UNKNOWN_ERROR: 
				$html .= 'Could not check';
				break;
		}

		$html .= '<br/><br/>';

		return array(
			'new' => $new,
			'old' => $old,
			'restock' => $restock,
			'html' => $html,
		);
	}

}

class DisneyException extends \Exception {}