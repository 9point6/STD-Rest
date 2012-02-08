<?php

class AF_Cache {
	public $cache = TRUE;
	public $cache_location = './cache';
	public $format = 'mc-cache';
	public $APIFactory = NULL;

	/**
	 * Constructor: set the cache location to default.
	 */
	function AF_Cache() {
		$this->hook_cache_location($this->cache_location);
	}

	/**
	 * Hook for when a method is added. Adds the "cache"
	 * parameter to the method.
	 * @param $method - the internal representation of the method just added
	 * @param $source - the stdClass (raw from JSON) representation of the method
	 */
	function hook_add_method(APIMethod $method, $source) {
		if (isset($source->cache) && ((int) $source->cache) > 0) {
			$method->cache = ((int) $source->cache);
		}
		else {
			$method->cache = FALSE;
		}
		return $method;
	}

	/**
	 * Hook for getting/setting the cache location.
	 * @param $dir - the new cache location. Either a full path or relative to this file.
	 * @return string the current cache location as a full path.
	 */
	function hook_cache_location($dir = FALSE) {
		if ($dir) {
			if (!file_exists($dir)) {
				$dir = dirname(__FILE__) . '/' . $dir;
			}
			$this->cache_location = $dir;
		}
		return $this->cache_location;
	}

	/**
	 * Hook for enabling/disabling caching.
	 */
	function hook_cache($cache) {
		$this->cache = (boolean) $cache;
		return TRUE;
	}

	/**
	 * Hook called prior to the CURL execute. Handles the retrieval and returning
	 * of existing cache items, taking note of cache expiry.
	 */
	function hook_pre_execute() {
		if (!file_exists($this->cache_location)) {
			trigger_error('APIFactory::Cache - Cache location does not exist: ' . $this->cache_location, E_USER_WARNING);
			return NULL;
		}

		$signature = $this->APIFactory->params;
		array_unshift($signature, $this->APIFactory->method->name);

		$expires = $this->APIFactory->method->cache;

		if (!$expires) {
			return NULL;
		}
		if ($expires === 1 || $expires === TRUE) {
			$expires = time() + 50; # never expire naturally
		}

		if ($this->cache && $contents = $this->hook_get_cache($signature, $expires)) {
			return $contents;
		}
	}

	/**
	 * Hook called after the CURL execute, for saving the results of a cached call.
	 */
	function hook_post_execute($result) {
		if (!file_exists($this->cache_location)) {
			trigger_error('APIFactory::Cache - Cache location does not exist: ' . $this->cache_location, E_USER_WARNING);
			return NULL;
		}

		$signature = $this->APIFactory->params;
		array_unshift($signature, $this->APIFactory->method->name);

		$expires = $this->APIFactory->method->cache;

		if ($this->cache && $this->APIFactory->method->cache) {
			$this->hook_add_cache($signature, $result);
		}

		if ($clear = @$this->APIFactory->method->cache_clear) {
			if (is_array($clear)) {
				foreach ($clear as $pattern) {
					$this->hook_clear_cache($pattern);
				}
			}
			else {
				$this->hook_clear_cache($clear);
			}
		}
	}

	/**
	 * Helper to return the filename of the cache file based on a signature.
	 * @return full-path filename.
	 */
	private function cache_name($signature) {
		if (is_array($signature)) {
			$start = preg_replace('/([^0-9a-z-\.]+|-+)/', '-', strtolower(array_shift($signature)));
			$signature = $start . '-' . md5(implode('', $signature));
		}
		return $this->cache_location . '/' . $signature . '.' . $this->format;
	}

	/**
	 * Hook for retrieving the cache on a specific signature.
	 * Automatically removes dead cache files of the same signature if it has expired.
	 * @param signature - array or string file signature.
	 * @param expiry - the number of seconds since creation that this file should be considered stale.
	 * @return file contents (stdClass, array) or NULL
	 */
	function hook_get_cache($signature, $expires) {
		$file = $this->cache_name($signature);
		if (file_exists($file)) {
			$contents = json_decode(file_get_contents($file));
			if (isset($contents->time) && $contents->time + $expires > time()) {
				return $contents->contents;
			}
			else {
				unlink($file);
			}
		}
		return FALSE;
	}

	/**
	 * Hook for adding a cache based on a signature manually.
	 * @param signature to use for filename
	 * @param contents - file contents to save
	 */
	function hook_add_cache($signature, $contents) {
		$file = $this->cache_name($signature);
		$data = array(
			'time' => time(),
			'contents' => $contents
		);
		file_put_contents($file, json_encode($data));
		return TRUE;
	}

	/**
	 * Hook for clearing the cache. Either one file or the whole cache.
	 * @param $signature - if set only the file with this signature is removed
	 *                     if set but only 1 char long, clear all files starting with this.
	 *                     if NOT set the entire cache is deleted.
	 */
	function hook_clear_cache($signature = FALSE) {
		if (!$signature || count($signature) == 1 || is_string($signature)) {
			if (is_array($signature)) {
				$signature = $signature[0];
			}
			$start = $signature ? '^' . preg_quote($signature) : '';
			# delete all cache files
			$od = opendir($this->cache_location);
			while ($file = readdir($od)) {
				if (preg_match('/' . $start . '.+\.' . preg_quote($this->format) . '$/', $file)) {
					unlink($this->cache_location . '/' . $file);
				}
			}
		}
		else {
			$file = $this->cache_name($signature);
			unlink($file);
		}
		return TRUE;
	}

}