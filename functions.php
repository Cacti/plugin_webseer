<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2010-2017 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function webseer_show_tab($current_tab) {
	global $config;
	$tabs = array(
		'webseer.php'         => __('Checks'),
		'webseer_servers.php' => __('Servers')
	);

	print "<div class='tabs'><nav><ul>\n";
	if (sizeof($tabs)) {
		foreach ($tabs as $url => $name) {
			print "<li><a class='" . (($url == $current_tab) ? 'pic selected' : 'pic') .  "' href='" . $config['url_path'] .
				"plugins/webseer/$url'>$name</a></li>\n";
		}
	}
	print "</ul></nav></div>\n";
}

function plugin_webseer_refresh_servers() {
	$server = db_fetch_row('SELECT * FROM plugin_webseer_servers WHERE master = 1');

	$cc              = new cURL();
	$cc->host['url'] = $server['url'];
	$data            = array();
	$data['action']  = 'GETSERVERS';
	$results         = $cc->post($server['url'], $data);

	$results         = explode("\n", $results);

	foreach ($results as $r) {
		if (substr($r, 0, 8) == 'SERVERS=') {
			$servers = substr($r, 8);
			$servers = unserialize(base64_decode($servers));
			if (isset($servers[0]['id'])) {
				db_execute('TRUNCATE TABLE plugin_webseer_servers');
				foreach ($servers as $save) {
					db_execute_prepared('REPLACE INTO plugin_webseer_servers (id, enabled, master, name, url, ip, location)
						VALUES (?,?,?,?,?,?,?)',
						array(
							$save['id'], $save['enabled'], $save['master'], $save['name'], $save['url'], $save['ip'] , $save['location'] 
						)
					);
				}
			}
			break;
		}
	}
}

function plugin_webseer_refresh_urls () {
	$server          = db_fetch_row('SELECT * FROM plugin_webseer_servers WHERE master = 1');
	$cc              = new cURL();
	$cc->host['url'] = $server['url'];
	$data            = array();
	$data['action']  = 'GETURLS';
	$results         = $cc->post($server['url'], $data);

	$results         = explode("\n", $results);

	foreach ($results as $r) {
		if (substr($r, 0, 5) == 'URLS=') {
			$urls = substr($r, 5);
			$urls = unserialize(base64_decode($urls));
			if (isset($urls[0]['id'])) {
				db_execute('TRUNCATE TABLE plugin_webseer_urls');
				foreach ($urls as $save) {
					db_execute_prepared('REPLACE INTO plugin_webseer_urls (id, enabled, requiresauth, checkcert, ip, display_name, notify_accounts, url, search, search_maint, search_failed, notify_extra, downtrigger)
						VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
						array(
							$save['id'], $save['enabled'], $save['requiresauth'], $save['checkcert'], $save['ip'], $save['display_name'], $save['notify_accounts'],
							$save['url'], $save['search'], $save['search_maint'], $save['search_failed'], $save['notify_extra'], $save['downtrigger']
						)
					);
				}
			}
			break;
		}
	}
}

function plugin_webseer_remove_old_users () {
	$users = db_fetch_assoc('SELECT id FROM user_auth');
	$u = array();
	foreach ($users as $user) {
		$u[] = $user['id'];
	}
	$contacts = db_fetch_assoc('SELECT DISTINCT user_id FROM plugin_thold_contacts');
	foreach ($contacts as $c) {
		if (!in_array($c['user_id'], $u)) {
			db_execute_prepared('DELETE FROM plugin_thold_contacts WHERE user_id = ?', array($c['user_id']));
		}
	}
}

function plugin_webseer_check_dns ($host) {
	$results = false;

	if (sizeof($host)) {
		$results = array();
		$results['result']                     = 0;
		$results['options']['http_code']       = 0;
		$results['error']                      = '';
		$results['options']['total_time']      = 0;
		$results['options']['namelookup_time'] = 0;
		$results['options']['connect_time']    = 0;
		$results['options']['redirect_time']   = 0;
		$results['options']['redirect_count']  = 0;
		$results['options']['size_download']   = 0;
		$results['options']['speed_download']  = 0;
		$results['time']                       = time();

		$s = microtime(true);
		$a = new mxlookup($host['search'], $host['url']);
		$t = microtime(true) - $s;
		$results['options']['connect_time'] = $results['options']['total_time'] = $results['options']['namelookup_time'] = round($t, 4);

		$results['data'] = '';
		foreach ($a->arrMX as $m) {
			$results['data'] .= "A RECORD: $m\n";
			if ($m == $host['search_maint']) {
				$results['result'] = 1;
			}
		}
	}

	return $results;
}

function plugin_webseer_set_remote_masters ($ip) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');
	foreach ($servers as $server) {
		plugin_webseer_set_remote_master ($server['url'], $ip);
	}
	db_execute('UPDATE plugin_webseer_servers set master = 0');
	db_execute_prepared('UPDATE plugin_webseer_servers set master = 1 WHERE ip = ?', array($ip));
}

function plugin_webseer_set_remote_master ($url, $ip) {
	$cc              = new cURL();
	$cc->host['url'] = $url;
	$data            = array();
	$data['action']  = 'SETMASTER';
	$data['ip']      = $ip;
	$results         = $cc->post($server['url'], $data);
}

function plugin_webseer_enable_remote_hosts ($id, $value = true) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');
	foreach ($servers as $server) {
		$cc              = new cURL();
		$cc->host['url'] = $server['url'];
		$data            = array();
		$data['action']  = ($value ? 'ENABLEURL' : 'DISABLEURL');
		$data['id']      = $id;
		$results         = $cc->post($server['url'], $data);
	}
}

function plugin_webseer_delete_remote_hosts ($id) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');
	foreach ($servers as $server) {
		$cc              = new cURL();
		$cc->host['url'] = $server['url'];
		$data            = array();
		$data['action']  = 'DELETEURL';
		$data['id']      = $id;
		$results         = $cc->post($server['url'], $data);
	}
}

function plugin_webseer_add_remote_hosts ($id, $save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');
	foreach ($servers as $server) {
		$cc              = new cURL();
		$cc->host['url'] = $server['url'];
		$save['action']  = 'ADDURL';
		$save['id']      = $id;
		$results         = $cc->post($server['url'], $save);
	}
}

function plugin_webseer_update_remote_hosts ($save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');
	foreach ($servers as $server) {
		$cc = new cURL();
		$cc->host['url'] = $server['url'];
		$save['action'] = 'UPDATEURL';
		$results = $cc->post($server['url'], $save);
	}
}

function plugin_webseer_add_remote_server ($id, $save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');
	foreach ($servers as $server) {
		$cc = new cURL();
		$cc->host['url'] = $server['url'];
		$save['action'] = 'ADDSERVER';
		$save['id'] = $id;
		$results = $cc->post($server['url'], $save);
	}
}

function plugin_webseer_update_remote_server ($save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');
	foreach ($servers as $server) {
		$cc              = new cURL();
		$cc->host['url'] = $server['url'];
		$save['action']  = 'UPDATESERVER';
		$results         = $cc->post($server['url'], $save);
	}
}

function plugin_webseer_enable_remote_server ($id, $value = true) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');
	foreach ($servers as $server) {
		$cc              = new cURL();
		$cc->host['url'] = $server['url'];
		$data            = array();
		$data['action']  = ($value ? 'ENABLESERVER' : 'DISABLESERVER');
		$data['id']      = $id;
		$results         = $cc->post($server['url'], $data);
	}
}

function plugin_webseer_delete_remote_server ($id) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');
	foreach ($servers as $server) {
		$cc              = new cURL();
		$cc->host['url'] = $server['url'];
		$data            = array();
		$data['action']  = 'DELETESERVER';
		$data['id']      = $id;
		$results         = $cc->post($server['url'], $data);
	}
}

function plugin_webseer_down_remote_hosts ($save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');
	foreach ($servers as $server) {
		$cc              = new cURL();
		$cc->host['url'] = $server['url'];
		$save['action']  = 'HOSTDOWN';
		$results         = $cc->post($server['url'], $save);
	}
}

function webseer_send_mail($to_email, $subject, $message, $type = '', $headers = '') {
	global $config;

	$headers = array();

	$from_email = read_config_option('settings_from_email');
	if ($from_email == '') {
		$from_email = 'webseer@cacti.pvt';
	}

	$from_name = read_config_option('settings_from_name');
	if ($from_name == '') {
		$from_email = 'Webseer';
	}

	$text = array('text' => '', 'html' => '');
	if ($type == 'text') {
		$message = str_replace('<br>',  "\n", $message);
		$message = str_replace('<BR>',  "\n", $message);
		$message = str_replace('</BR>', "\n", $message);
		$text['text'] = strip_tags($message);
	} else {
		$text['html'] = str_replace("\n", '<br>', $message);
		$text['text'] = strip_tags(str_replace('<br>', "\n", $message));
	}

	$attachments = array();

	$pinfo = plugin_webseer_version();
	$headers['X-Mailer'] = $headers['User-Agent'] = 'Cacti-Webseer-v' . $pinfo['version'];

	$error = mailer(
		array($from_email, $from_name),
		$to_email,
		'',
		'',
		'',
		$subject,
		$text['html'],
		$text['text'],
		$attachments,
		$headers
	);

	if (strlen($error)) {
		cacti_log('ERROR: Sending Email Failed.  Error was ' . $error, true, 'WEBSEER');

		return $error;
	}

	return '';
}

class cURL {
	var $headers;
	var $user_agent;
	var $compression;
	var $cookie_file;
	var $proxy;
	var $results;
	var $error;
	var $host;
	var $data;
	var $bundle;

	function cURL($cookies = TRUE, $cookie = 'cookies.txt', $compression = 'gzip', $proxy = '') {
		global $config;
//		$this->headers[] = 'Accept: image/gif, image/x-bitmap, image/jpeg, image/pjpeg';
//		$this->headers[] = 'Connection: Keep-Alive';
//		$this->headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';
		$this->user_agent = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
		$this->compression = $compression;
		$this->proxy = $proxy;
		$this->cookies = $cookies;
		if ($this->cookies == TRUE){
			$this->cookie($cookie);
		}
		$this->results = array('result' => 0, 'time' => time(), 'error' => '');
		$this->host['search'] = $this->host['search'];
		$this->bundle = $config['base_path'] . '/plugins/webseer/ca-bundle.crt';
	}

	function cookie($cookie_file) {
		if (file_exists($cookie_file)) {
			$this->cookie_file = $cookie_file;
		} else {
			fopen($cookie_file, 'w') or $this->results['error'] = 'The cookie file could not be opened. Make sure this directory has the correct permissions';
			$this->cookie_file = $cookie_file;
			fclose($this->cookie_file);
		}
	}

	function post($url, $data = array()) {
		$process = curl_init($url);
		$this->headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';
		curl_setopt($process, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($process, CURLOPT_HEADER, 1);
		curl_setopt($process, CURLOPT_USERAGENT, $this->user_agent);
//		curl_setopt($process, CURLOPT_ENCODING , $this->compression);
		curl_setopt($process, CURLOPT_TIMEOUT, 4);
		$d = array();
		foreach ($data as $i => $j) {
			$d[] = "$i=$j";
		}
		$data = implode('&', $d);
		curl_setopt($process, CURLOPT_POSTFIELDS, $data);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($process, CURLOPT_POST, 1);
		$return = curl_exec($process);
		curl_close($process);
		return $return;
	}

	function get() {
		$url = $this->host['url'];

		$process = curl_init($url);
		if ($this->host['ip'] != '') {
			curl_setopt($process, CURLOPT_PROXY, $this->host['ip']); 
			if (substr(strtolower($url), 0, 5) == 'https') {
				curl_setopt($process, CURLOPT_PROXYPORT, 443); 
			} else {
				curl_setopt($process, CURLOPT_PROXYPORT, 80); 
			}
		}

		curl_setopt($process, CURLOPT_HEADER, 1);
		curl_setopt($process, CURLOPT_USERAGENT, $this->user_agent);
//		curl_setopt($process, CURLOPT_ENCODING , $this->compression);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($process, CURLOPT_MAXREDIRS, 4);
//		 curl_setopt($process,     CURLOPT_VERBOSE, 1);


		curl_setopt($process, CURLOPT_TIMEOUT, $this->host['timeout_trigger']);
//		if ($this->cookies == TRUE) curl_setopt($process, CURLOPT_COOKIEFILE, $this->cookie_file);
//		if ($this->cookies == TRUE) curl_setopt($process, CURLOPT_COOKIEJAR, $this->cookie_file);
//		if ($this->proxy) curl_setopt($process, CURLOPT_PROXY, 'proxy_ip:proxy_port');
		if ($this->host['requiresauth'] == 'off') {
			curl_setopt($process, CURLOPT_FAILONERROR, ($this->host['requiresauth'] == 'off' ? true : false)); 
		}
		// curl_setopt($process, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		// curl_setopt($process, CURLOPT_SSLVERSION, 3);  // FORCE SSL v3

		// Disable Cert checking for now
		if ($this->host['checkcert'] == 'off') {
			curl_setopt($process, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($process, CURLOPT_SSL_VERIFYHOST, FALSE);
		}
		curl_setopt($process, CURLOPT_CAINFO, $this->bundle);


		$data = curl_exec($process);

		$this->data = str_replace(array("'", "\\"), array(''), $data);

		$this->results['options'] = curl_getinfo($process);


		$errnum = curl_errno($process);

		switch ($errnum) {
			case 0:
				//$this->results['error'] = 'OK';

				break;
			default:
				$errors = array(
					1  => 'Unsupported Protocol',
					2  => 'Failed to Initialize',
					3  => 'Malformed URL',
					5  => 'Could not resolve Proxy',
					6  => 'Could not resolve host',
					7  => 'Could not connect',
					9  => 'Remote access denied',
					22 => $this->results['options']['http_code'],
					28 => 'Operation Timed Out',
					35 => 'SSL Handshake Error',
					47 => 'Too Many Redirects',
					51 => 'SSL Certificate failed verification',
					52 => 'No Data returned',
					55 => 'Error sending network data',
					56 => 'Error receiving network data',
					58 => 'Error with local certificate',
					59 => 'Provided SSL Cipher could not be used',
					60 => 'Peer certificate cannot be authenticated with known CA certificates',
					61 => 'Bad Content Encoding',
					67 => 'Login Denied',
					77 => 'Problem with reading the SSL CA Cert'
					);
				if (isset($errors[$errnum])) {
					if ($errnum == 22) {
						$httperrors = array(
							100 => 'Continue',
							101 => 'Switching Protocols',
							200 => 'OK',
							201 => 'Created',
							202 => 'Accepted',
							203 => 'Non-Authoritative Information',
							204 => 'No Content',
							205 => 'Reset Content',
							206 => 'Partial Content',
							300 => 'Multiple Choices',
							301 => 'Moved Permanently',
							302 => 'Found',
							303 => 'See Other',
							304 => 'Not Modified',
							305 => 'Use Proxy',
							306 => '(Unused)',
							307 => 'Temporary Redirect',
							400 => 'Bad Request',
							401 => 'Unauthorized',
							402 => 'Payment Required',
							403 => 'Forbidden',
							404 => 'Not Found',
							405 => 'Method Not Allowed',
							406 => 'Not Acceptable',
							407 => 'Proxy Authentication Required',
							408 => 'Request Timeout',
							409 => 'Conflict',
							410 => 'Gone',
							411 => 'Length Required',
							412 => 'Precondition Failed',
							413 => 'Request Entity Too Large',
							414 => 'Request-URI Too Long',
							415 => 'Unsupported Media Type',
							416 => 'Requested Range Not Satisfiable',
							417 => 'Expectation Failed',
							500 => 'Internal Server Error',
							501 => 'Not Implemented',
							502 => 'Bad Gateway',
							503 => 'Service Unavailable',
							504 => 'Gateway Timeout',
							505 => 'HTTP Version Not Supported'
							);
						if (isset($httperrors[$this->results['options']['http_code']])) {
							$this->results['error'] = 'HTTP ERROR: ' . $errors[$errnum] . ' - ' . $httperrors[$this->results['options']['http_code']];
						}
					} else {
						$this->results['error'] = $errors[$errnum];
					}
				} else {
					$this->results['error'] = "Unknown Error: $errnum";
				}
				break;
		}
		curl_close($process);

		// If we have set a failed search string, then ignore the normal searches and only alert on it
		if ($this->host['search_failed'] != '') {
			if (strpos($data, $this->host['search_failed']) !== FALSE) {
				$this->results['error'] = 'Failure Search string found!';
				return $this->results;
			} else {
				$this->results['error'] = '';
				$this->results['result'] = 1;
				return $this->results;
			}
		}

		if ($errnum != 0) {
			return $this->results;
		}

		$found = (strpos($data, $this->host['search']) !== false);
		if (!$found && $this->host['search_maint'] != '') {
			$found = (strpos($data, $this->host['search_maint']) !== false);
		}

		if (!$found) {
			$this->results['error'] = 'Search string not found';
			return $this->results;
		} else {
			if ($this->host['requiresauth'] == 'off') {
				$this->results['result'] = 1;
			} else {
				if ($this->results['options']['http_code'] == 401) {
					$this->results['result'] = 1;
				} else {
					$this->results['error'] = 'The requested URL returned error: ' . $this->results['options']['http_code'];
				}
			}
			return $this->results;
		}
	}
}

class mxlookup {
	var $dns_socket = NULL;
	var $QNAME = '';
	var $dns_packet = NULL;
	var $ANCOUNT = 0;
	var $cIx = 0;
	var $dns_repl_domain;
	var $arrMX = array();

	function mxlookup($domain, $dns = '4.2.2.1') {
		$this->QNAME($domain);
		$this->pack_dns_packet();

		$dns_socket = fsockopen("udp://$dns", 53);

		fwrite($dns_socket, $this->dns_packet, strlen($this->dns_packet));
		$this->dns_reply  = fread($dns_socket,1);
		$bytes = stream_get_meta_data($dns_socket);
		$this->dns_reply .= fread($dns_socket,$bytes['unread_bytes']);
		fclose($dns_socket);
		$this->cIx = 6;
		$this->ANCOUNT   = $this->gord(2);

		$this->cIx += 4;
		$this->parse_data($this->dns_repl_domain);
		$this->cIx += 7;
		for($ic = 1; $ic <= $this->ANCOUNT; $ic++) {
			$QTYPE = ord($this->gdi($this->cIx));
			if($QTYPE !== 1){
				print('[Record not returned]');
				die();
			}
			$this->cIx += 8;

			$ip = ord($this->gdi($this->cIx)) . '.' . ord($this->gdi($this->cIx)) . '.' . ord($this->gdi($this->cIx)) . '.' . ord($this->gdi($this->cIx));
			$this->arrMX[] = $ip;
			//$mxPref = ord($this->gdi($this->cIx));
			//$this->parse_data($curmx);
			//$this->arrMX[] = array('MX_Pref' => $mxPref, 'MX' => $curmx);
			//$this->cIx += 3;
		}
	}

	function parse_data(&$retval) {
		$arName = array();
		$byte = ord($this->gdi($this->cIx));
		while($byte!==0) {
			if($byte == 192) { //compressed 
				$tmpIx = $this->cIx;
				$this->cIx = ord($this->gdi($cIx));
				$tmpName = $retval;
				$this->parse_data($tmpName);
				$retval=$retval . '.' . $tmpName;
				$this->cIx = $tmpIx+1;
				return;
			}
			$retval='';
			$bCount = $byte;
			for($b=0;$b<$bCount;$b++) {
				$retval .= $this->gdi($this->cIx);
			}
			$arName[]=$retval;
			$byte = ord($this->gdi($this->cIx));
		}
		$retval=join('.',$arName);
	}

	function gdi(&$cIx,$bytes=1) {
		$this->cIx++;
		return(substr($this->dns_reply, $this->cIx-1, $bytes));
	}

	function QNAME($domain) {
		$dot_pos = 0;
		$temp = '';
		while($dot_pos = strpos($domain, '.')) {
			$temp   = substr($domain, 0, $dot_pos);
			$domain = substr($domain, $dot_pos + 1);
			$this->QNAME .= chr(strlen($temp)) . $temp;
		}
		$this->QNAME .= chr(strlen($domain)) . $domain.chr(0);
	}

	function gord($ln = 1) {
       	$reply = '';
		for($i = 0; $i < $ln; $i++){
			$reply .= ord(substr($this->dns_reply, $this->cIx, 1));
			$this->cIx++;
		}
		return $reply;
	}

	function pack_dns_packet() {
		$this->dns_packet = chr(0).chr(1).
				      chr(1).chr(0).
				      chr(0).chr(1).
				      chr(0).chr(0).
				      chr(0).chr(0).
				      chr(0).chr(0).
				      $this->QNAME .
				      chr(0).chr(1).
				      chr(0).chr(1);
	}
}
