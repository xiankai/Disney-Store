<?

namespace Mock;

class Curl {

	private $callback;
	private $requests = array();
	
	function add(MockRequest $request) {
		$this->requests[] = $request;
		return $this;
	}

	function setCallback($closure) {
		$this->callback = $closure;
		return $this;
	}

	function execute() {
		foreach ($this->requests as $request) {
			$this->callback($request, $this);
		}

		return $this;
	}

	function post() {
		return $this;
	}

	function get() {
		return $this;
	}

	function addOptions() {
		return $this;
	}

}