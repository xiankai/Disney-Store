<?

namespace Mock;

class Request {

	private $item;
	private $data;

	function addOptions() {
		return $this;
	}

	function setPostData() {
		return $this;
	}

	// Getting cookies!
	function getResponseInfo() {
		$dummy_headers = 'Set-Cookie: test;' . PHP_EOL;
		$this->data = $dummy_headers . $this->data;
		return array('header_size', strlen($dummy_headers));
	}

	function setExtraInfo($item) {
		$this->item = $item;
		return $this;
	}

	function getExtraInfo() {
		return $this->item;
	}

	// Set test data here
	function setResponseText($data) {
		$this->data = $data;
		return $this;
	}

	function getResponseText() {
		return $this->data;
	}

}