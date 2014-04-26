<?

namespace Mock;

class Parser {

	private $items = array();
	
	// Set test listing
	public function validateListing() {
		return array(
			'items' => $this->items,
			'cookies' => '',
		);
	}

	public function setTestListing($items) {
		$this->items = $items;
	}

}