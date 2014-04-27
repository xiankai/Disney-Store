<?

namespace Store;

interface StoreInterface {
	public function __construct($db, $interval);

	public function get($key);

	public function set($key, $value);

	public function keyExists($key);

	public function count();
}