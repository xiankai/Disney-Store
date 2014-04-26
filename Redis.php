<?

require 'StoreInterface.php';

class Redis implements StoreInterface {

	private $store;
	private $interval;

	function __construct($interval) {
		$this->store = new Predis\Client();
		$this->interval = $interval;
	}

	function get($key) {
		return $this->store->get($key);
	}

	function set($key, $value) {
		$this->store->set($key, $value);

		$this->store->expire($key, $this->interval * 2);
	}

	function keyExists($key) {
		return !is_null($this->store->get($key));
	}
}