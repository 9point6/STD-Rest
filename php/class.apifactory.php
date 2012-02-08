<?php

require_once("class.apimethod.php");
require_once("class.rest.php");

class APIFactory {
	
	function APIFactory() {
		$this->params = array();
		$this->methods = array();
		$this->hooked = array();

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

		if ($pre_return = $this->call_hook($name, false, $args)) {
			return $pre_return;
		}

		if(isset($this->methods[$name])) {
			$this->method = $this->methods[$name];
		
			$this->call_hook("pre_call", true);

			if($this->method->authenticated)
				$this->call_hook("pre_call_auth", true);
			
			if(($this->method->authenticated && $this->secure == "auth_only") || $this->secure == "always")
				$this->rest->secure(true);
			else
				$this->rest->secure(false);

			$method = $this->form_signature($this->method->get_signature(), $args);
			$static = $this->form_signature($this->get_static_fields($this->method->authenticated), $this->get_param());

			$sig = array_merge($static, $method);# this order to cause method to overwrite static in the event that this would ever happen.

			# at this stage, we have a fully formed signature and all required fields exist.
			# however, we need to do validation.
			foreach($sig as $key=>$value) {

				# allow direct specification of either pattern or validators[key]
				$regex = null;
				if(get($this->method, 'validation', false) && $pattern = get($this->method->validation, $key, false)) {
					$regex = get($this->validators, $pattern, false) ? $this->validators->$pattern->pattern : $pattern;
				}

				# otherwise try match up validators/*/fields:* to key
				if($regex === null) {
					# look through the validators for one that either matches by key or by fields[]
					# if we wanted to allow multiple regexs to validate on a single field, switch to array here
					foreach($this->validators as $vk=>$o) {
						if($vk == $key || in_array($key, get($o, 'fields', array()))) {
							$regex = $o->pattern;
						}
					}
				}
				if(isset($sig[$key]) && $sig[$key] !== FALSE && $regex && !preg_match($regex, $sig[$key])) {
					throw new Exception("Validation failed on $name:$key, where value = \n    " . $sig[$key] . "\nand pattern =\n    " . $regex . "\n");
				}
				if(isset($sig[$key]) && $sig[$key] === FALSE) {
					unset($sig[$key]);
				}
				else {
					$this->set_param($key, $value);
				}
			}


			# all required fields exist, all filled fields are validated. nothing left but to do the charleston.
			$this->rest->set_method($this->method->request_type);
			$this->rest->add_param($sig);
			$this->rest->set_path($this->method->path);

			$this->rest->url = $this->replace_keys($this->url);

			if ($pre_return = $this->call_hook("pre_execute", true)) {
				return $pre_return;
			}
			if ($this->method->authenticated && $pre_return = $this->call_hook("pre_execute_auth", true)) {
				return $pre_return;
			}
			$result = $this->rest->execute();

			if ($new_result = $this->call_hook("post_execute", true, $result)) {
				return $new_result;
			}

			return $result;
		}
		else {
			throw new Exception("Call to undefined method `$name`");
		}
	}

	function add_hooker($name) {
		if (class_exists($name)) {
			$obj = new $name($this);
			$obj->APIFactory = $this;
			
			$this->hooked[] = $obj;
			if (!property_exists($this, $name)) {
				$this->$name = $obj;
			}
			
			return $obj;
		}
		return FALSE;
	}

	function call_hook($hook, $extra = false) {
		$args = func_get_args();
		$targs = func_get_args();

		$hook = array_shift($args);
		$extra = array_shift($args);

		if($extra && $this->method) {
			$targs[0] .= "_" . preg_replace('/[^a-z]+/i', '_', $this->method->name);
			$targs[1] = false;
			call_user_func_array(array($this, "call_hook"), $targs);
		}

		$hook = "hook_" . $hook;

		$found = FALSE;
		foreach($this->hooked as $obj) {
			if(method_exists($obj, $hook)) {
				$found = TRUE;
				$result = call_user_func_array(array($obj, $hook), $args);
				if ($result !== NULL) {
					return $result;
				}
			}
		}
		return NULL;
	}


	function get_static_fields($authenticated = false) {
		$base = get($this->static_fields, "all", array());
		$plus = get($this->static_fields, $authenticated ? "auth_only" : "unauth_only", array());
		$out = array();

		foreach(array_merge($base, $plus) as $key) {			
			$keys = $this->parse_default($key, NULL); # setting to null causes them to be required

			foreach($keys as $key=>$val) {
				if(is_numeric($key)) {
					$key = $val;
					$val = NULL;
				}
				if(strlen($key))
					$out[$key] = $val;
			}
		}

		return $out;
	}

	function parse_default($key, $def = NULL) {
		if(strpos($key, "=") === false)
			return array($key, $def);

		$keys = array();

		$key = $this->replace_keys($key);

		# preg instead of explode.
		foreach(explode("&", $key) as $i=>$v) {
			$k = explode("=", $v);
			$keys[$k[0]] = $k[1];
		}

		return $keys;
	}

	/*
	* Str = the string to be replacing in
	* rk  = return the key of that index within the string once replaced.
	* po  = the object to be replacing from (parent object)
	*/
	function replace_keys($str, $rk = false, $po = false) {
		preg_match_all("/\{([^{}=&,\/]+)\}/", $str, $args);

		if(!$po) $po = $this;

		foreach($args[1] as $i=>$v) {
			if($rk && $i != $rk)
				continue;

			$k = explode(".", $v);
			$o = $po;

			foreach($k as $kk) {
				if(is_object($o) && isset($o->$kk))
					$o = $o->$kk;
				else if(is_array($o) && isset($o[$kk]))
					$o = $o[$kk];
			}
			
			if(is_array($o) || is_object($o)) {
				throw new Exception("Replacing keys on $v pointed to an object/array instead of literal");
			}

			if(strpos($o, '{') !== false) {
				$o = $this->replace_keys($o, false, $po);
			}

			if($rk)
				return $o;

			$str = str_replace("{".$v."}", $o,  $str);
		}

		return $str;
	}

	function form_signature($sig, $args) {
		$args = (array) $args;

		# if the $args was an object/associative array
		foreach($args as $key=>$value) {
			if(array_key_exists($key, $sig)) {
				$sig[$key] = $value;
				unset($args[$key]); # remove it from the list to stop it carrying through
			}
		}

		# assign any unused $args to any unfilled $sig
		foreach($sig as $key=>$value) {
			if ($sig[$key] != NULL) {
				continue;
			}
			
			if(!$sig[$key] && count($args)) { # if there are unused $args
				$sig[$key] = array_shift($args);
				continue;
			}

			if(is_numeric($key)) {
				unset($sig[$key]);
				$sig[$value] = NULL;
				$key = $value;
			}

			if($val = $this->get_param($key)) # if there is a param of the correct name
				$sig[$key] = $val;
			else if($sig[$key] === NULL) { # if it is required and is still empty by now
				throw new Exception("Required field $key not set");
			}
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

		$file = file_get_contents($file);
		# remove comments.
		$file = preg_replace("/\n.*\/\/.*/im", "", $file);

		if(($json = json_decode($file)) == FALSE) {
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

		
		if($auth = get($json, "authentication"))
			foreach($auth as $key=>$value)
				if($auth = $this->authFactory($key, $value)) {
					$this->hooked[] = $auth;
					$this->call_hook("pre_load");
					break;
				}

		$this->vars = get($json, "vars", array());
		$this->secure = get($json, "secure", "never");
		$this->docs = get($json, "docs");
		$this->static_fields = get($json, "static_fields");
		$this->validators = get($json, "validators", array());

		# split validators by comma if neccessary
		foreach($this->validators as $key=>$value) {
			if($fields = get($value, 'fields')) {
				if(!is_array($fields)) {
					$fields = preg_replace("/(, | ,)/i", ",", trim($fields));
					$this->validators->$key->fields = explode(",", $fields);
				}
			}
		}
		
		$this->rest->error_check = get($json, "error_check_path");
		$this->rest->error_return = get($json, "error_return_path");

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
				$docs = $this->replace_keys($pattern, false, $method);

			$m =& $this->add_method($method, $name, $required, $optional, $validation, $path, $auth, $type, $docs);

			# add other items..
			foreach($method as $key=>$value) {
				if(!in_array($key, array("name", "required", "optional", "validation", "path", "authenticated", "request_type"))) {
					$m->$key = $value;
				}
			}
		}
		$this->call_hook("post_load");

		return $this;
	}

	function add_method($source, $name, $required = array(), $optional = array(), $validation = array(), $path = "", $authenticated = false, $method = "get", $docs = false) {
		$m = new APIMethod();
		$m->set_name($name);
		$m->set_required($required);
		$m->set_optional($optional);
		$m->set_validation($validation);
		$m->set_path($path);
		$m->set_authenticated($authenticated);
		$m->set_request_type($method);
		$m->set_docs($docs); # does not do string replacement.
		
		if ($method = $this->call_hook('add_method', FALSE, $m, $source)) {
			$m = $method;
		}	

		$name = preg_replace("/([^a-z]+)/i", "_", $name);
		$this->methods[$name] = $m;
		return $m;
	}

	private function authFactory($method, $data) {
		if(!file_exists("lib/class.auth." . $method . ".php"))
			return false;

		require_once("class.auth." . $method . ".php");
		$method = ucwords($method) . "_auth";

		if(!class_exists($method))
			return false;
		
		return ($this->authentication = new $method($this, $data));
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