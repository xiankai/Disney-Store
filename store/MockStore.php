<?

namespace Store;

class MockStore implements StoreInterface {
	public function __construct($interval) {}

	public function get($key) {}

	public function set($key, $value) {}

	public function keyExists($key) {}
}