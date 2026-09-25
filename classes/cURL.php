<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

class cURL {
	/** @var array<int,string> */
	var $headers;
	/** @var string */
	var $user_agent;
	/** @var int */
	var $compression;
	/** @var string|null */
	var $cookie_file;

	/** @var string */
	var $proxy_hostname;
	/** @var mixed */
	var $proxy_http_port;
	/** @var mixed */
	var $proxy_https_port;
	/** @var string */
	var $proxy_username;
	/** @var string */
	var $proxy_password;

	/** @var array<string,mixed> */
	var $results;
	/** @var mixed */
	var $error;
	/** @var array<string,mixed>|string */
	var $host;
	/** @var string */
	var $data;
	/** @var string */
	var $bundle;
	/** @var array<int|string,mixed> */
	var $httperrors;
	/** @var bool */
	var $debug;
	/** @var bool */
	var $cookies;

	/**
	 * Initializes a cURL wrapper instance for a single HTTP(S) service
	 * check: sets the user agent, target host context, HTTP compression
	 * option, and (optionally) prepares a cookie jar file. Called when a
	 * new cURL object is constructed, e.g. from webseer_process.php and
	 * poller_webseer.php's plugin_webseer_update_servers().
	 *
	 * @param bool         $cookies        Whether to use a cookie jar file for
	 *                                     this session; defaults to true.
	 * @param string       $cookie         The cookie jar file path to use when
	 *                                     $cookies is true; defaults to
	 *                                     'cookies.txt'.
	 * @param int          $compression    The configured HTTP compression
	 *                                     option id (validated against
	 *                                     $httpcompressions); defaults to
	 *                                     WEBSEER_COMPRESSION_NONE.
	 * @param string       $proxy_hostname An optional HTTP proxy hostname to
	 *                                     route requests through; defaults to
	 *                                     ''.
	 * @param array|string $host           The service check/server row this
	 *                                     request is being made for, used for
	 *                                     debug logging context; defaults to
	 *                                     '' (empty string).
	 *
	 * @return void
	 *
	 * @global array $config            Cacti global configuration array;
	 *                                  used to locate the CA bundle file.
	 * @global array $httperrors        Map of HTTP status codes to their
	 *                                  descriptions.
	 * @global array $httpcompressions  Map of compression option ids to
	 *                                  their cURL encoding values.
	 * @global bool  $debug             Whether debug output is enabled.
	 */
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

		if ($this->cookies === true) {
			$this->cookie($cookie);
		}

		if (isset($httpcompressions[$compression])) {
			$this->compression = $compression;
		} else {
			$this->compression = 0;
		}

		$this->results        = ['result' => 0, 'time' => time(), 'error' => ''];
		$this->bundle         = $config['base_path'] . '/plugins/webseer/ca-bundle.crt';
	}

	/**
	 * Validates that the cookie jar file exists or can be created, storing
	 * its path for use by subsequent requests, or recording an error if
	 * it is not accessible. Called from the constructor when cookies are
	 * enabled.
	 *
	 * @param string $cookie_file The cookie jar file path to validate.
	 *
	 * @return void
	 */
	function cookie($cookie_file) {
		$this->debug('Checking Cookie File');

		if (file_exists($cookie_file)) {
			$this->cookie_file = $cookie_file;
		} elseif (is_writable($cookie_file)) {
			$this->cookie_file = $cookie_file;
		} else {
			$this->results['error'] = 'The cookie file could not be opened. Make sure this directory has the correct permissions';
		}
	}

	/**
	 * Sends an HTTP POST request with the given form data to a URL.
	 * Called from poller_webseer.php's plugin_webseer_update_servers()
	 * (to send a HEARTBEAT notification) and from
	 * plugin_webseer_down_remote_hosts() in includes/functions.php (to
	 * send a HOSTDOWN notification) to another webseer server.
	 *
	 * @param string $url  The URL to POST to.
	 * @param array  $data Key/value pairs to send as
	 *                     'application/x-www-form-urlencoded' POST data;
	 *                     defaults to an empty array.
	 *
	 * @return string|bool The raw response body (including headers,
	 *                     since CURLOPT_HEADER is enabled), or false on
	 *                     cURL failure.
	 */
	function post($url, $data = []) {
		global $httpcompressions;

		$this->debug('Executing Post Request');

		$process         = curl_init($url);
		$this->headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';

		$d = [];

		foreach ($data as $i => $j) {
			$d[] = "$i=$j";
		}

		$data = implode('&', $d);

		$options = [
			CURLOPT_HTTPHEADER      => $this->headers,
			CURLOPT_HEADER          => true,
			CURLOPT_USERAGENT       => $this->user_agent,
			CURLOPT_TIMEOUT         => 4,
			CURLOPT_POSTFIELDS      => $data,
			CURLOPT_RETURNTRANSFER  => true,
			CURLOPT_FOLLOWLOCATION  => true,
			CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_POST            => true,
		];

		if (!empty($this->compression)) {
			$options[CURLOPT_ENCODING] = $httpcompressions[$this->compression];
		}

		$this->debug('cURL options: ' . clean_up_lines(var_export($options, true)));
		curl_setopt_array($process, $options);

		$return = curl_exec($process);
		curl_close($process);

		return $return;
	}

	/**
	 * Forwards a debug message to the plugin's debug logging function,
	 * tagged with this instance's target host context. Called throughout
	 * this class to log request/option details.
	 *
	 * @param string $message The debug message to log.
	 *
	 * @return void
	 */
	function debug($message) {
		plugin_webseer_debug($message, is_array($this->host) ? $this->host : []);
	}

	/**
	 * Performs the HTTP(S) GET request for this instance's configured
	 * target host/URL, applying its timeout, redirect, proxy, and
	 * certificate-verification settings, and records the resulting
	 * response data and cURL timing/status info. Called from
	 * webseer_process.php's main flow for each 'http'/'https' type
	 * service check.
	 *
	 * @return array The check result: 'result' (1 success, 0 failure),
	 *               'time' (Unix timestamp), and 'error' (error message,
	 *               if any); the raw/cleaned response body is also stored
	 *               on $this->data and full cURL info on
	 *               $this->results['options'].
	 *
	 * @global array $httpcompressions Map of compression option ids to
	 *                                their cURL encoding values.
	 */
	function get() {
		global $httpcompressions;

		$host = is_array($this->host) ? $this->host : [];

		$this->debug('Executing Get Request for URL:' . ($host['url'] ?? '') . ', IP:' . ($host['ip'] ?? ''));

		$url = $host['url'] ?? '';

		$process = curl_init($url);

		$options = [
			CURLOPT_HEADER          => true,
			CURLOPT_USERAGENT       => $this->user_agent,
			CURLOPT_RETURNTRANSFER  => true,
			CURLOPT_FOLLOWLOCATION  => true,
			CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_MAXREDIRS       => 4,
			CURLOPT_TIMEOUT         => $host['timeout_trigger'] ?? 0,
			CURLOPT_FAILONERROR     => (($host['requiresauth'] ?? '') == '' ? true : false),
		];

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
				$port_http             = 80;
				$this->proxy_http_port = $port_http;
			}

			$port_https = intval($this->proxy_https_port);

			if ($port_https < 0 || $port_https > 65535) {
				$port_https             = 443;
				$this->proxy_https_port = $port_http;
			}

			$is_https = (substr(strtolower($url), 0, 5) == 'https');

			$proxy_opts = [
				CURLOPT_UNRESTRICTED_AUTH => false,
				CURLOPT_PROXY             => $this->proxy_hostname,
				CURLOPT_PROXYPORT         => $is_https ? $port_https : $port_http,
			];

			if ($this->proxy_username != '') {
				$proxy_opts[CURLOPT_PROXYUSERPWD] = $this->proxy_username . ':' . $this->proxy_password;
			}
		} else {
			$proxy_opts = [];
		}

		// Verify the certificate by default; only skip verification when
		// the host explicitly opted out via an unchecked 'checkcert' field.
		// A missing 'checkcert' key (malformed/legacy row) also verifies.
		$checkcert = array_key_exists('checkcert', $host) ? $host['checkcert'] : 'on';

		if ($checkcert == '') {
			$cert_opts = [
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
			];
		} else {
			$cert_opts = [
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_CAINFO         => $this->bundle,
			];
		}

		$options += $proxy_opts;
		$options += $cert_opts;

		$this->debug('cURL options: ' . clean_up_lines(var_export($options, true)));
		curl_setopt_array($process,$options);

		$data = curl_exec($process);
		$data = is_string($data) ? $data : '';

		$this->data = str_replace(["'", '\\'], [''], $data);

		$this->results['options']                = curl_getinfo($process);
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
				$this->results['error'] = 'HTTP ERROR: ' . str_replace(['"', "'"], '', (curl_error($process)));

				break;
		}

		curl_close($process);

		// If we have set a failed search string, then ignore the normal searches and only alert on it
		if (($host['search_failed'] ?? '') != '' && $errnum > 0) {
			$this->debug('Processing search_failed');

			if (strpos($data, $host['search_failed']) !== false) {
				$this->results['error'] = 'Failure Search string found!';
			} else {
				$this->results['error']  = '';
				$this->results['result'] = 1;
			}
		} elseif ($errnum == 0) {
			$this->debug('Processing search');

			if (($host['search'] ?? '') != '') {
				$found = (strpos($data, $host['search']) !== false);
			} else {
				$found = false;
			}

			if (!$found && ($host['search_maint'] ?? '') != '') {
				$this->debug('Processing search maint');
				$found = (strpos($data, $host['search_maint']) !== false);
			}

			if (!$found) {
				$this->debug('Processing search not found');

				$this->results['error'] = 'Search string not found';
			} else {
				$this->debug('Processing search found');

				if (($host['requiresauth'] ?? '') == '') {
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
