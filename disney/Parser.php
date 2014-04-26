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
			'items' => $items,
			'cookies' => $cookies,
		);
	}

}

class DisneyException extends \Exception {}