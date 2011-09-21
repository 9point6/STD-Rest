<?php

class Shopify_hooks {

	function Shopify_hooks($api) {
		$this->api = $api;
	}

	function hook_pre_execute_customers_search() {
		$method = $this->api->method;
		$params = $this->api->rest->params;
		$new_params = array();

		if(isset($params['f']) && is_array($params['f']))
		{
			foreach($params['f'] as $key=>$value) {
				$new_params[] = $key . ':' . $value;
			}
			unset($params['f']);
			$p = http_build_query($params) . '&f[]=' . implode('f[]=', $new_params);
			$this->api->rest->params = $p;
		}
	}
	
	function hook_pre_execute() {
		$method =& $this->api->method;
		
		$compress = false;

		if($method->request_type == 'GET' ||
			$method->request_type == 'DELETE')
			return;

		$bits = explode('/', $method->name);

		if($bits[1] == "create" || $bits[1] == "update") {
			if(substr($bits[0],-1,1) == "s") {
				$bits[0] = substr($bits[0],0,-1);
			}
			$compress = $bits[0];
		}

		if($method->name == "assets/save") {
			$compress = "asset";
		}

		if($method->name == "collections/create") {
			$compress = "collect";
		}

		if($bits[0] == "countrie") {
			$compress = "country";
		}


		if($compress) {
			$obj = new stdClass;

			foreach($this->api->rest->params as $key=>$value) {
				$obj->$key = $value;
				unset($this->api->rest->params[$key]);
			}

			$this->api->rest->params = json_encode(array($compress => $obj)); 
			$this->api->rest->copts = array(
				CURLOPT_HTTPHEADER => array('Content-Type: application/json; charset=utf-8', 'Content-length: ' . strlen($this->api->rest->params)),
			);
		}
	}
}