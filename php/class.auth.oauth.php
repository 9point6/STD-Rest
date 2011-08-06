<?php

class Oauth {
	function Oauth($data) {
		$this->url = $data->url;
	}

	function get_url($parent) {
		return str_replace("%api_key", $parent->get_param("api_key"), $this->url);
	}

	function validate_params($parent) {
		if(isset($this->token)) {
			$parent->add_param("sk", $this->token);

			$sig = array();
			foreach($parent->get_param() as $key=>$value) {
				$sig[$key] = $value;
			}
			ksort($sig);

			$str = "";
			foreach($sig as $key=>$value) {
				$str .= $key . $value;
			}

			// $secret = ;
			if($parent->get_param("secret") === false) {
				throw new Exception("Ouath");
			}

			$parent->add_param("api_sig", md5($str));
		}
	}
}