<?php

class APIMethod {
	
	function APIMethod() {
		$this->required = array();
		$this->optional = array();
	}

	function get_signature() {
		$sig = array();
		foreach($this->required as $key=>$value) {
			$sig[$value] = NULL; /* null here for required */
		}
		foreach($this->optional as $key=>$value) {
			$sig[$value] = FALSE; /* false here, so we can test properly */
		}

		return $sig;
	}

	function set_name($name) {
		$this->name = $name;
	}

	function set_required($required) {
		if(is_array($required)) {
			$this->required = $required;
		}
		else if(strpos(",", $required)) {
			$this->required = explode(",", $required);
		}
		else {
			$this->required = array($required);
		}
	}

	function set_optional($optional) {
		if(is_array($optional)) {
			$this->optional = $optional;
		}
		else if(strpos(",", $optional)) {
			$this->optional = explode(",", $optional);
		}
		else {
			$this->optional = array($optional);
		}
	}

	function set_path($path) {
		if(is_array($path)) {
			$this->path = $path;
		}
		else if(strpos("/", $path)) {
			$this->path = explode("/", $path);
		}
		else if($path) {
			$this->path = array($path);
		}
		else {
			$this->path = array();
		}
	}

	function set_authenticated($bool) {
		$this->authenticated = (bool) $bool;
	}

	function set_docs($link) {
		$this->docs = $link;
	}

	function set_request_type($type) {
		$type = strtoupper(trim($type));
		if(in_array($type, array("GET","POST","DELETE","PUT"))) {
			$this->request_type = $type;
		}
		else throw new Exception("Unknown request type $type passed to method {$this->name}");
	}

	function set_validation($vals) {
		$this->validation = $vals;
	}
}