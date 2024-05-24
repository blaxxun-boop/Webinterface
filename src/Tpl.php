<?php

namespace ValheimServerUI;

use Amp\Http\HttpStatus;
use Amp\Http\Server\Response;

class Tpl {
	private string $file;
	private array $vars = [];
	private string $include;

	public function doEval() {
		extract($this->vars);
		include $this->include;
	}

	public function load(string $file) {
		$this->file = $file;
	}

	public function set(string|array $key, $val = null) {
	    if(is_array($key) && $val === null) {
            $this->vars += $key;
	    } else {
    		$this->vars[$key] = $val;
	    }
	}

	public function render(int $status = HttpStatus::OK, array $headers = []): Response {
		return new Response($status, $headers + ['content-type' => 'text/html, charset=utf8'], $this->singlePage($this->file));
	}

	private function singlePage($file) {
		$this->include = $file;
		ob_start();
		$this->doEval();
		return ob_get_clean();
	}
}
