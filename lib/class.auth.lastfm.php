<?php

class Lastfm_auth {
	
	function Lastfm_auth($parent, $data) {
		$this->parent = $parent;
		$this->url = get($data, "url");

		if(!$this->url)
			throw new Exception("LastFM Auth expected a URL in it's data-set");


	}

	/**
	* This hook is called prior to data being loaded from JSON.
	* The parent object contains /almost/ nothing - just service name and URL
	*/
	function hook_pre_load() {
	}

	/**
	* This hook is called after data was loaded from JSON.
	* The parent object contains all methods, but no request-specific data.
	*/
	function hook_post_load() {
		
	}

	/**
	* This hook is called at the start of any method execution,
	* before any signatures etc are calculated - only the $this->parent->method is set
	* Aside from the request-specific stuff, the parent object should contain:
	*	- any seperately added parameters (such as API key)
	*	- all method signatures
	*	- vars, docs, and static_fields definitions
	*/
	function hook_pre_call() {
		if(isset($_GET['token'])) {
			@session_start();

			if(!isset($_SESSION['key']) && !isset($this->skip)) {
				$this->skip = true;
				$token = $_GET['token'];
				$_SESSION['token'] = $token;
				$this->parent->vars->token = $token;

				# get the API key
				if(!($api_key = get($this->parent, 'api_key', false)))
					if(!($api_key = get($this->parent->vars, 'api_key', false)))
						if(!($api_key = get($this->parent->params, 'api_key', false)))
							throw new Exception("LastFM Auth requires an api key");

				$fields = $this->parent->get_static_fields(false);
				$fields["method"] = "auth.getSession";
				$fields["token"] = $token;


				$result = $this->parent->auth->getSession($token, $this->sign($fields));
				
				$this->parent->vars->key = $result->key;
				$_SESSION['key'] = $result->key;
			}
		}
	}

	/**
	* This hook mimics hook_pre_call but is only called for authorised calls.
	*/
	function hook_pre_call_auth() {
		# we need a token. If one is not set, go get it.
		@session_start();
		
		if($key = get($this->parent->vars, 'key') or $key = get($_SESSION, 'key')) {
			$this->parent->vars->key = $key;
			$this->parent->set_param("sk", $key);
			return;
		}

		# get the API key
		if(!($api_key = get($this->parent, 'api_key', false)))
			if(!($api_key = get($this->parent->vars, 'api_key', false)))
				if(!($api_key = get($this->parent->params, 'api_key', false)))
					throw new Exception("LastFM Auth requires an api key");

		$cb  = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?lastfm_auth=true";
		$url = "http://www.last.fm/api/auth/?api_key=" . $api_key . "&cb=" . urlencode($cb);

		header("Location: " . $url);
	}

	/**
	* This hook is called just prior to the REST::execute() is called, and
	* as such the parent object contains everything needed to make a succesful
	* request.
	*/
	function hook_pre_execute() {
		// print_r($this->parent);
	}

	/**
	* This hook mimics hook_pre_execute but is only called on authorised calls.
	*/
	function hook_pre_execute_auth() {
		$this->parent->rest->params['api_sig'] = $this->sign($this->parent->rest->params);
	}




	private function sign($params) {
		ksort($params);
		$out = '';

		unset($params['format']);

		foreach($params as $key=>$value) {
			$out .= trim(utf8_encode($key)) . trim(utf8_encode($value));
		}

		# get the API key
		if(!($secret = get($this->parent, 'secret', false)))
			if(!($secret = get($this->parent->vars, 'secret', false)))
				if(!($secret = get($this->parent->params, 'secret', false)))
					throw new Exception("LastFM Auth requires an api key");

		$out .= $secret;

		return md5($out);
	}
}

?>