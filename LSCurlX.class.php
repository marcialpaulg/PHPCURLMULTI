<?php

class LSCurlX {
	private $data = array(
		'max_connection' => 0,
		'global_curl' => array(
			'option' => array(),
			'header' => array(),
			'callback' => null
		),
		'proccess_timeout' => 5, // in seconds
		'request' => array()
	);

	function __construct($m = 10) {
		$this->setMaxConnection($m);
		$this->_lsdata = $this->data;
	}

	public function reset($is_all = false){
		if($is_all === false) {
			$this->data['request'] = array();
		} else {
			$this->data = $this->_lsdata;
		}
		return $this;
	}

	public function setMaxConnection($m) {
		if($m <= 0) return false;
		$this->data['max_connection'] = $m;
		return $this;
	}

	public function setGlobal($name, $value) {
		if(!isset($this->data['global_curl'][$name]) || (!is_array($value) && !is_callable($value))) return false;
		$this->data['global_curl'][$name] = $value;
		return $this;
	}

	public function setTimeout($t) {
		if($t <= 0) return false;
		$this->data['proccess_timeout'] = $t;
		return $this;
	}

	public function requestCount(){
		return count($this->data['request']);
	}

	public function request($url, $data = null, callable $callback = null, $option = null, $udata = null){
		$this->data['request'][] = array(
			'url' => $url,
			'data' => $data,
			'callback' => !empty($callback) ? $callback : $this->data['global_curl']['callback'],
			'option' => $option,
			'udata' => $udata
		);
		return $this;
	}

	public function get($url, callable $callback = null, $option = null, $udata = null){
		return $this->request($url, null, $callback, $option, $udata);
	}

	public function post($url, $data, callable $callback = null, $option = null, $udata = null){
		return $this->request($url, $data, $callback, $option, $udata);
	}

	public function http($method, $url, $data = null, callable $callback = null, $option = null, $udata = null) {
		$method = strtoupper($method);
		if(!in_array($method, array('OPTIONS', 'GET', 'HEAD', 'POST', 'PUT', 'DELETE', 'TRACE', 'CONNECT'))) return false;

		$option[CURLOPT_CUSTOMREQUEST] = $method;

		return $this->request($url, $data, $callback, $option, $udata);
	}

	public function execute($mc = null) {
		if(!empty($mc)) $this->setMaxConnection($mc);

		if($this->requestCount() < $this->data['max_connection']) {
			$this->data['max_connection'] = $this->requestCount();
		}

		$_mem = [];
		$multicurl = curl_multi_init();

		for($i = 0; $i < $this->data['max_connection']; $i++) {
			$ch = curl_init();

			$request =& $this->data['request'][$i];

			curl_setopt_array($ch, $this->_opt($request));
			curl_multi_add_handle($multicurl, $ch);

			$_mem[(string) $ch] = $i;
		}
		do{
			while(($stat = curl_multi_exec($multicurl, $in_active)) == CURLM_CALL_MULTI_PERFORM);
			if($stat != CURLM_OK) break;

			while($in_ch = curl_multi_info_read($multicurl)) {
				$ch = $in_ch['handle'];

				$response = curl_multi_getcontent($ch);

				$key = (string) $ch;
				$request =& $this->data['request'][$_mem[$key]];

				if(!empty($this->data['global_curl']['option'][CURLOPT_HEADER]) || !empty($request['options'][CURLOPT_HEADER])) $response = 'X-Leetshares-Proxy: '.$request['url']."
".$response;

				unset($_mem[$key]); curl_multi_remove_handle($multicurl, $ch);

				if(isset($request['callback'])) call_user_func($request['callback'], $response, $request['user_data'], array(
					'url' => $request['url'],
					'info' => curl_getinfo($ch)
				));

				$request = NULL;

				if($i < count($this->data['request']) && isset($this->data['request'][$i])) {
					$ch = curl_init();

					curl_setopt_array($ch, $this->_opt($this->data['request'][$i]));
					curl_multi_add_handle($multicurl, $ch);

					$_mem[(string) $ch] = $i;
					$i++;
				}
			}
			if($in_active && curl_multi_select($multicurl, $this->data['proccess_timeout']) === -1) usleep(10000);
		} while ($in_active || count($_mem));

		$this->reset();
		curl_multi_close($multicurl);
	}

	private function _opt(array $request) {

		if(empty($request['url'])) return false;

		$options = !empty($request['option']) ? array_merge($request['option'], $this->data['global_curl']['option']) : $this->data['global_curl']['option'];
		$headers = !empty($request['option'][CURLOPT_HTTPHEADER]) ? array_merge($request['option'][CURLOPT_HTTPHEADER], $this->data['global_curl']['header']) : $this->data['global_curl']['header'];

		$options[CURLOPT_URL] = $request['url'];
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_TIMEOUT] = $this->data['proccess_timeout'];

		if(!empty($headers)) $options[CURLOPT_HTTPHEADER] = $headers;

		if(!empty($request['data'])) {
			$options[CURLOPT_POST] = 1;
			$options[CURLOPT_POSTFIELDS] = is_array($request['data']) ? http_build_query($request['data']) : $request['data'];
		}
		return $options;
	}

	public function debug(){
		// return
	}
}
