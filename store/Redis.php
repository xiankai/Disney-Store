<?

namespace Store;

class Redis implements StoreInterface {

	public $store;
	private $interval;

	function __construct($db, $interval) {
		$this->store = new \Predis\Client();
		$this->store->select($db);
		$this->interval = $interval;
	}

	function get($key) {
		return $this->store->get($key);
	}

	function set($key, $value) {
		$this->store->set($key, $value);

		$this->refresh($key);
	}

	function keyExists($key) {
		return !is_null($this->store->get($key));
	}

	function count() {
		return $this->store->dbsize();
	}

	function refresh($key) {
		$this->store->expire($key, $this->interval * 2);
	}

	function getAll() {
		return $this->store->keys("*");
	}
}