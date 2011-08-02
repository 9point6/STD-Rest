<?php

require_once("class.apimethod.php");
require_once("class.rest.php");

class APIFactory {
	
	function APIFactory() {
		$this->params = array();
		$this->methods = array();

		$this->rest = new REST();
	}

	function __get($name) {
		if(!isset($this->$name)) {
			$this->$name = new APIFactorySub($name, $this);
		}
		return $this->$name;
	}
	function __call($name, $args) {
		$name = preg_replace("/([^a-z]+)/i", "_", $name);

		if(count($args) == 1 && (is_array($args[0]) || is_object($args[0]))) {
			$args = $args[0];
		}

		if(isset($this->methods[$name])) {
			$this->method = $this->methods[$name];

			$method = $this->form_signature($this->method->get_signature(), $args);
			$static = $this->form_signature($this->get_static_fields($this->method->authenticated), $this->get_param());
			
			$sig = array_merge($static, $method); # this order to cause method to overwrite static in the event that this would ever happen.

			# at this stage, we have a fully formed signature and all required fields exist.
			# however, we need to do validation.
			foreach($this->method->validation as $key=>$value) {
				$regex = "/".$value."/i";
				if(isset($sig[$key]) && $sig[$key] !== FALSE && !preg_match($regex, $sig[$key])) {
					throw new Exception("Validation failed on $name > $key, where value = " . $sig[$key]);
				}
			}

			# all required fields exist, all filled fields are validated. nothing left but to do the charleston.
			$type = $this->method->request_type;

			$this->rest->set_method($type);
			$this->rest->add_param($sig);
			$this->rest->set_path($this->method->path);

			$this->rest->url = $this->replace_keys($this->url);

			$result = $this->rest->execute();
			return $result;
		}
		else {
			throw new Exception("Call to undefined method `$name`");
		}
	}


	function get_static_fields($authenticated = false) {
		$base = $this->static_fields->all;
		$plus = $this->static_fields->{$authenticated ? "auth_only" : "unauth_only"};
		$out = array();

		foreach(array_merge($base, $plus) as $key) {			
			$keys = $this->parse_default($key, NULL); # setting to null causes them to be required

			foreach($keys as $key=>$val)
				$out[$key] = $val;
		}
		return $out;
	}

	function parse_default($key, $def = NULL) {
		if(strpos($key, "=") === false)
			return array($key, $def);

		$keys = array();

		$key = $this->replace_keys($key);

		# preg instead of explode.
		preg_match_all("/&?([^=]+)=([^=]+)/", $key, $args);
		foreach($args[1] as $i=>$v) {
			$keys[$v] = $args[2][$i];
		}

		return $keys;
	}

	function replace_keys($str, $km = "%", $rk = false, $po = false) {
		preg_match_all("/(%[^".$km."=&,\/]+)/", $str, $args);

		if(!$po) $po = $this;

		foreach($args[1] as $i=>$v) {
			if($rk && $i != $rk)
				continue;

			$k = explode(".", substr($v,strlen($km)));
			$o = $po;

			foreach($k as $kk)
				if(isset($o->$kk))
					$o = $o->$kk;
			
			if(is_array($o) || is_object($o))
				throw new Exception("Replacing keys on $v pointed to an object/array instead of literal");

			if($rk)
				return $o;

			$str = str_replace($v, $o,  $str);
		}

		return $str;
	}

	function form_signature($sig, $args) {
		$args = (array) $args;

		# if the $args was an object/associative array
		foreach($args as $key=>$value) {
			if(isset($sig[$key])) {
				$sig[$key] = $value;
				unset($args[$key]); # remove it from the list to stop it carrying through
			}
		}

		# assign any unused $args to any unfilled $sig
		foreach($sig as $key=>$value) {
			if(!$sig[$key] && count($args)) # if there are unused $args
				$sig[$key] = array_shift($args);
			else if($val = $this->get_param($key)) # if there is a param of the correct name
				$sig[$key] = $val;
			else if($sig[$key] === NULL) # if it is required and is still empty by now
				throw new Exception("Required field $key not set");
		}

		return $sig;
	}

	function get_param($name = false) {
		if($name)
			if(isset($this->params[$name]))
				return $this->params[$name];
			else
				return false;
		
		return $this->params;
	}

	function set_param($name, $value) {
		$this->params[$name] = $value;
		return $this;
	}

	function fromJSON($file) {
		if(file_exists($file) == FALSE)
			throw new Exception("Unable to open file $file");

		if(($json = json_decode(file_get_contents($file))) == FALSE) {
			$errors = array(JSON_ERROR_DEPTH => "JSON_ERROR_DEPTH",
							JSON_ERROR_STATE_MISMATCH => "JSON_ERROR_STATE_MISMATCH",
							JSON_ERROR_CTRL_CHAR => "JSON_ERROR_CTRL_CHAR",
							JSON_ERROR_SYNTAX => "JSON_ERROR_SYNTAX",
							JSON_ERROR_UTF8 => "JSON_ERROR_UTF8");

			throw new Exception("Invalid JSON in $file : " . $errors[json_last_error()]);
		}

		if( ($this->service = get($json, "service")) == FALSE ||
			($methods       = get($json, "methods")) == FALSE ||
			($this->url     = get($json, "url"    )) == FALSE)
			throw new Exception("JSON does not conform to APIFactory spec.");

		$this->vars = get($json, "vars", array());

		$this->docs = get($json, "docs");
		$this->static_fields = get($json, "static_fields");

		$this->rest->error_check = get($json, "error_check_path");
		$this->rest->error_return = get($json, "error_retur_path");
		
		if(get($json, "authentication")) {
			$oauth = get($json->authentication, "oauth");

			if($oauth)
				$this->authentication = $this->authFactory("oauth", $oauth);
			
			// and so on for different auth schemes
		}

		foreach($methods as $name => $method) {
			$name 		= get($method, "name", $name);
			$required   = get($method, "required", array());
			$optional   = get($method, "optional", array());
			$validation = get($method, "validation", array());
			$path = get($method, "path", false);
			$auth = get($method, "authenticated", false);
			$type = get($method, "request_type", "get");

			$docs = false;
			if($this->docs && $pattern = get($this->docs, "pattern"))
				$docs = $this->replace_keys($pattern, "%", false, $method);

			$m =& $this->add_method($name, $required, $optional, $validation, $path, $auth, $type, $docs);

			# add other items..
			foreach($method as $key=>$value) {
				if(!in_array($key, array("name", "required", "optional", "validation", "path", "authenticated", "request_type"))) {
					$m->$key = $value;
				}
			}
		}
		return $this;
	}

	function add_method($name, $required = array(), $optional = array(), $validation = array(), $path = "", $authenticated = false, $method = "get", $docs = false) {
		$m = new APIMethod();
		$m->set_name($name);
		$m->set_required($required);
		$m->set_optional($optional);
		$m->set_validation($validation);
		$m->set_path($path);
		$m->set_authenticated($authenticated);
		$m->set_request_type($method);
		$m->set_docs($docs); # does not do string replacement.		

		$name = preg_replace("/([^a-z]+)/i", "_", $name);
		$this->methods[$name] = $m;
		return $m;
	}

	private function authFactory($method, $data) {
		require_once("class.auth." . $method . ".php");
		$method = ucwords($method);
		$this->authentication = new $method($data);
	}
}

function get($object, $key, $def = false) {
	if(is_object($object) && isset($object->$key))
		return $object->$key;
	else if(is_array($object) && isset($object[$key]))
		return $object[$key];
	else
		return $def;
}

class APIFactorySub {
	
	function __construct($prefix, $parent) {
		$this->prefix = $prefix;
		$this->parent = $parent;
	}

	function __get($name) {
		if(!isset($this->$name)) {
			$this->$name = new APIFactorySub($name, $this);
		}
		return $this->$name;
	}

	function __call($name, $args) {
		return $this->parent->__call($this->prefix . "_" . $name, $args);
	}
}