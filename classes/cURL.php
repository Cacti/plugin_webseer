<?php
class cURL {
	var $headers;
	var $user_agent;
	var $compression;
	var $cookie_file;

	var $proxy_hostname;
	var $proxy_http_port;
	var $proxy_https_port;
	var $proxy_username;
	var $proxy_password;

	var $results;
	var $error;
	var $host;
	var $data;
	var $bundle;
	var $httperrors;
	var $debug;

	function __construct($cookies = true, $cookie = 'cookies.txt', $compression = 'gzip', $proxy_hostname = '', $host = '') {
		global $config, $httperrors, $debug;

//		$this->headers[] = 'Accept: image/gif, image/x-bitmap, image/jpeg, image/pjpeg';
//		$this->headers[] = 'Connection: Keep-Alive';
//		$this->headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';

		$this->host           = $host;
		$this->user_agent     = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
		$this->compression    = $compression;
		$this->proxy_hostname = $proxy_hostname;
		$this->httperrors     = $httperrors;
		$this->cookies        = $cookies;
		$this->debug          = $debug;

		if ($this->cookies === true){
			$this->cookie($cookie);
		}

		$this->results        = array('result' => 0, 'time' => time(), 'error' => '');
		$this->host['search'] = $this->host['search'];
		$this->bundle         = $config['base_path'] . '/plugins/webseer/ca-bundle.crt';
	}

	function cookie($cookie_file) {
		$this->debug('Checking Cookie File');

		if (file_exists($cookie_file)) {
			$this->cookie_file = $cookie_file;
		} elseif (is_writable($cookie_file)) {
			$this->cookie_file = $cookie_file;
		}else{
			$this->results['error'] = 'The cookie file could not be opened. Make sure this directory has the correct permissions';
		}
	}

	function post($url, $data = array()) {
		$this->debug('Executing Post Request');

		$process = curl_init($url);
		$this->headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';

		curl_setopt($process, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($process, CURLOPT_HEADER, true);
		curl_setopt($process, CURLOPT_USERAGENT, $this->user_agent);

		// curl_setopt($process, CURLOPT_ENCODING , $this->compression);

		curl_setopt($process, CURLOPT_TIMEOUT, 4);

		$d = array();
		foreach ($data as $i => $j) {
			$d[] = "$i=$j";
		}

		$data = implode('&', $d);
		curl_setopt($process, CURLOPT_POSTFIELDS, $data);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($process, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($process, CURLOPT_POST, true);
		$return = curl_exec($process);
		curl_close($process);

		return $return;
	}

	function debug($message) {
		plugin_webseer_debug($message, $this->host);
	}

	function get() {
		$this->debug('Executing Get Request for URL:' . $this->host['url'] . ', IP:' . $this->host['ip']);

		$url = $this->host['url'];

		$process = curl_init($url);

		if ($this->proxy_hostname != '') {
			if ($this->proxy_http_port == '') {
				$this->proxy_http_port = 80;
			}

			if ($this->proxy_https_port == '') {
				$this->proxy_https_port = 443;
			}

			curl_setopt($process, CURLOPT_UNRESTRICTED_AUTH, true);
			curl_setopt($process, CURLOPT_PROXY, $this->proxy_hostname);

			if (substr(strtolower($url), 0, 5) == 'https') {
				curl_setopt($process, CURLOPT_PROXYPORT, $this->proxy_https_port);
			} else {
				curl_setopt($process, CURLOPT_PROXYPORT, $this->proxy_http_port);
			}

			if ($this->proxy_username != '') {
				curl_setopt($process, CURLOPT_PROXYUSERPWD, $this->proxy_username . ':' . $this->proxy_password);
			}
		}

		curl_setopt($process, CURLOPT_HEADER, true);
		curl_setopt($process, CURLOPT_USERAGENT, $this->user_agent);

		// curl_setopt($process, CURLOPT_ENCODING , $this->compression);

		curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($process, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($process, CURLOPT_MAXREDIRS, 4);

		// curl_setopt($process,     CURLOPT_VERBOSE, 1);

		curl_setopt($process, CURLOPT_TIMEOUT, $this->host['timeout_trigger']);

		// if ($this->cookies == TRUE) curl_setopt($process, CURLOPT_COOKIEFILE, $this->cookie_file);
		// if ($this->cookies == TRUE) curl_setopt($process, CURLOPT_COOKIEJAR, $this->cookie_file);

		curl_setopt($process, CURLOPT_FAILONERROR, ($this->host['requiresauth'] == '' ? true : false));

		// curl_setopt($process, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		// curl_setopt($process, CURLOPT_SSLVERSION, 3);  // FORCE SSL v3

		// Disable Cert checking for now
		if ($this->host['checkcert'] == '') {
			curl_setopt($process, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($process, CURLOPT_SSL_VERIFYHOST, FALSE);
		}

		curl_setopt($process, CURLOPT_CAINFO, $this->bundle);

		$data = curl_exec($process);

		$this->data = str_replace(array("'", "\\"), array(''), $data);

		$this->results['options'] = curl_getinfo($process);

		$errnum = curl_errno($process);

		$this->debug('cURL errno: ' . $errnum);
		if ($errnum) {
			$this->debug('cURL error: ' . curl_error($process));
		}

		switch ($errnum) {
			case 0:
				break;
			default:
				$this->results['error'] = 'HTTP ERROR: ' . str_replace(array('"', "'"), '', (curl_error($process)));

				break;
		}

		curl_close($process);

		// If we have set a failed search string, then ignore the normal searches and only alert on it
		if ($this->host['search_failed'] != '' && $errnum > 0) {
			$this->debug('Processing search_failed');

			if (strpos($data, $this->host['search_failed']) !== false) {
				$this->results['error'] = 'Failure Search string found!';
			} else {
				$this->results['error'] = '';
				$this->results['result'] = 1;
			}
		}elseif ($errnum == 0) {
			$this->debug('Processing search');

			if ($this->host['search'] != '') {
				$found = (strpos($data, $this->host['search']) !== false);
			} else {
				$found = false;
			}

			if (!$found && $this->host['search_maint'] != '') {
				$this->debug('Processing search maint');
				$found = (strpos($data, $this->host['search_maint']) !== false);
			}

			if (!$found) {
				$this->debug('Processing search not found');

				$this->results['error'] = 'Search string not found';
			} else {
				$this->debug('Processing search found');

				if ($this->host['requiresauth'] == '') {
					$this->debug('Processing requires authentication');

					$this->results['result'] = 1;
				} else {
					$this->debug('Processing requires no authentication required');

					if ($this->results['options']['http_code'] == 401) {
						$this->results['result'] = 1;
					} else {
						$this->results['error'] = 'The requested URL returned error: ' . $this->results['options']['http_code'];
					}
				}
			}
		}

		return $this->results;
	}
}
