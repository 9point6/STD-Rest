<?php

class REST {

	function REST() {
		$this->url = false;
		$this->reset();
	}

	function set_method($type) {
		$type = strtoupper(trim($type));
		if(in_array($type, array("GET","POST","DELETE","PUT"))) {
			$this->method = $type;
		}
		else throw new Exception("Unknown HTTP method $type passed to method REST::set_method");
	}

	function add_param($key, $value = false) {
		if(is_array($key)) {
			foreach($key as $k=>$v) {
				$this->add_param($k, $v);
			}
			return;
		}

		$this->params[$key] = $value;
	}

	function secure($bool = false) {
		$this->secure = (bool) $bool;
	}

	function execute() {
		# form URL
		$url = ($this->secure ? "https" : "http") . "://" . $this->url;

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);


		switch ($this->method) {
			case "POST":
				curl_setopt($ch, CURLOPT_POST, TRUE);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
				break;
			case "PUT":
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-length: ' . http_build_query($this->params)));
				break;
			case "DELETE":
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
				curl_setopt($ch, CURLOPT_URL, $this->url . '?' . http_build_query($this->params));
				break;
			case "GET":
			default:
				curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($this->params));
				break;
		}

		$this->last_request = new stdClass;
		$this->last_request->url = $url;
		$this->last_request->params = $this->params;
		$this->last_request->error = false;

		$this->last_request->raw = curl_exec($ch);
		$this->last_request->status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$this->last_request->content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		$this->last_request->content_length = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

		# various methods for decoding.
		if($result = json_decode($this->last_request->raw)) {
			# json, woo
		}
		else if($result = simplexml_load_string($this->last_request->raw)) {
			# xml, woo-ish... reliance on simplexml?
		}
		else {
			# default case is string. return that fucker.
			return $this->last_request->raw;
		}

		# check for errors.
		if($this->error_check && $err1 = $this->resolve_path($result, $this->error_check, TRUE)) {
			if($this->error_return && $err2 = $this->resolve_path($result, $this->error_return, TRUE)) {}
			else { $err2 = $err1; }
			
			$this->last_request->error = $err2;
			return $this->last_request->error;
		}

		# no errors, so resolve the path and return the data.
		$this->last_request->result = $this->resolve_path($result, $this->path, FALSE);
		return $this->last_request->result;
	}

	function set_path($path) {
		$this->path = $path;
	}

	function resolve_path($object, $path, $fatal = false) {
		if(is_string($path)) {
			$path = preg_split("/(\/|\.|-)/", $path);
		}
		foreach($path as $p) {
			if(($object = get($object, $p)) === FALSE)
				return ($fatal ? FALSE : $object);
		}

		return $object;
	}

	function reset() {
		$this->params = array();
		$this->secure = false;
		$this->path = false;
	}
}