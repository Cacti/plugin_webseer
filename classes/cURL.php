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

	function __construct($cookies = true, $cookie = 'cookies.txt', $compression = WEBSEER_COMPRESSION_NONE, $proxy_hostname = '', $host = '') {
		global $config, $httperrors, $httpcompressions, $debug;

//		$this->headers[] = 'Accept: image/gif, image/x-bitmap, image/jpeg, image/pjpeg';
//		$this->headers[] = 'Connection: Keep-Alive';
//		$this->headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';

		$this->host           = $host;
		$this->user_agent     = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
		$this->proxy_hostname = $proxy_hostname;
		$this->httperrors     = $httperrors;
		$this->cookies        = $cookies;
		$this->debug          = $debug;

		if ($this->cookies === true){
			$this->cookie($cookie);
		}

		if (isset($httpcompressions[$compression])) {
			$this->compression = $compression;
		} else {
			$this->compression = 0;
		}

		$this->results        = array('result' => 0, 'time' => time(), 'error' => '');
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
		global $httpcompressions;

		$this->debug('Executing Post Request');

		$process = curl_init($url);
		$this->headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';

		$d = array();
		foreach ($data as $i => $j) {
			$d[] = "$i=$j";
		}

		$data = implode('&', $d);

		$options = array(
			CURLOPT_HTTPHEADER => $this->headers,
			CURLOPT_HEADER => true,
			CURLOPT_USERAGENT => $this->user_agent,
			CURLOPT_TIMEOUT => 4,
			CURLOPT_POSTFIELDS => $data,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_POST => true,
		);

		if (!empty($this->compression)) {
			$options[CURLOPT_ENCODING] = $httpcompressions[$this->compresion];
		}

		$this->debug('cURL options: ' . clean_up_lines(var_export($options, true)));
		curl_setopt_array($process, $options);

		$return = curl_exec($process);
		curl_close($process);

		return $return;
	}

	function debug($message) {
		plugin_webseer_debug($message, $this->host);
	}

	function get() {
		global $httpcompressions;

		$this->debug('Executing Get Request for URL:' . $this->host['url'] . ', IP:' . $this->host['ip']);

		$url = $this->host['url'];

		$process = curl_init($url);

		$options = array(
			CURLOPT_HEADER         => true,
			CURLOPT_USERAGENT      => $this->user_agent,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 4,
			CURLOPT_TIMEOUT        => $this->host['timeout_trigger'],
			CURLOPT_FAILONERROR    => ($this->host['requiresauth'] == '' ? true : false),
		);

		if (!empty($this->compression)) {
			$options[CURLOPT_ENCODING] = $httpcompressions[$this->compression];
		}

		// CURLOPT_ENCODING  => $this->compression,
		//     CURLOPT_VERBOSE => 1,


		// if ($this->cookies == TRUE) CURLOPT_COOKIEFILE => $this->cookie_file,
		// if ($this->cookies == TRUE) CURLOPT_COOKIEJAR => $this->cookie_file,


		// CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0,
		// CURLOPT_SSLVERSION => 3,  // FORCE SSL v3

		if ($this->proxy_hostname != '') {
			$port_http = intval($this->proxy_http_port);
			if ($port_http < 0 || $port_http > 65535) {
				$port_http = 80;
				$this->proxy_http_port = $port_http;
			}

			$port_https = intval($this->proxy_https_port);
			if ($port_https < 0 || $port_https > 65535) {
				$port_https = 443;
				$this->proxy_https_port = $port_http;
			}

			$is_https = (substr(strtolower($url), 0, 5) == 'https');

			$proxy_opts = array(
				CURLOPT_UNRESTRICTED_AUTH => true,
				CURLOPT_PROXY             => $this->proxy_hostname,
				CURLOPT_PROXYPORT         => $is_https ? $port_https : $port_http,
			);

			if ($this->proxy_username != '') {
				$proxy_opts[CURLOPT_PROXYUSERPWD] = $this->proxy_username . ':' . $this->proxy_password;
			}
		} else {
			$proxy_opts = array();
		}

		// Disable Cert checking for now
		if ($this->host['checkcert'] == '') {
			$cert_opts = array(
				CURLOPT_SSL_VERIFYPEER => FALSE,
				CURLOPT_SSL_VERIFYHOST => FALSE,
			);
		} else {
			$cert_opts = array();
		}

		$options += $proxy_opts;
		$options += $cert_opts;

		$this->debug('cURL options: ' . clean_up_lines(var_export($options, true)));
		curl_setopt_array($process,$options);

		$data = curl_exec($process);

		$this->data = str_replace(array("'", "\\"), array(''), $data);

		$this->results['options'] = curl_getinfo($process);
		$this->results['options']['compression'] = $this->compression;

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
