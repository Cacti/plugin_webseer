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
		'webseer.php'         => __('Checks', 'webseer'),
		'webseer_servers.php' => __('Servers', 'webseer'),
		'webseer_proxies.php' => __('Proxies', 'webseer')
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
					db_execute_prepared('REPLACE INTO plugin_webseer_urls
						(id, enabled, requiresauth, checkcert, ip, display_name, notify_accounts, url, search, search_maint, search_failed, notify_extra, downtrigger)
						VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
						array(
							$save['id'], $save['enabled'], $save['requiresauth'], $save['checkcert'],
							$save['ip'], $save['display_name'], $save['notify_accounts'],
							$save['url'], $save['search'], $save['search_maint'],
							$save['search_failed'], $save['notify_extra'], $save['downtrigger']
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
	$contacts = db_fetch_assoc('SELECT DISTINCT user_id FROM plugin_webseer_contacts');
	foreach ($contacts as $c) {
		if (!in_array($c['user_id'], $u)) {
			db_execute_prepared('DELETE FROM plugin_webseer_contacts WHERE user_id = ?', array($c['user_id']));
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

function plugin_webseer_update_contacts() {
	$users = db_fetch_assoc("SELECT id, 'email' AS type, email_address FROM user_auth WHERE email_address!=''");
	if (sizeof($users)) {
		foreach($users as $u) {
			$cid = db_fetch_cell('SELECT id FROM plugin_webseer_contacts WHERE type="email" AND user_id=' . $u['id']);

			if ($cid) {
				db_execute("REPLACE INTO plugin_webseer_contacts (id, user_id, type, data) VALUES ($cid, " . $u['id'] . ", 'email', '" . $u['email_address'] . "')");
			}else{
				db_execute("REPLACE INTO plugin_webseer_contacts (user_id, type, data) VALUES (" . $u['id'] . ", 'email', '" . $u['email_address'] . "')");
			}
		}
	}
}

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

	function __construct($cookies = true, $cookie = 'cookies.txt', $compression = 'gzip', $proxy_hostname = '') {
		global $config, $httperrors, $debug;

//		$this->headers[] = 'Accept: image/gif, image/x-bitmap, image/jpeg, image/pjpeg';
//		$this->headers[] = 'Connection: Keep-Alive';
//		$this->headers[] = 'Content-type: application/x-www-form-urlencoded;charset=UTF-8';

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
		if ($this->debug) {
			echo "DEBUG: " . trim($message) . "\n";
		}
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

		if ($this->host['requiresauth'] == '') {
			curl_setopt($process, CURLOPT_FAILONERROR, ($this->host['requiresauth'] == '' ? true : false));
		}

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

class mxlookup {
	var $dns_socket = NULL;
	var $QNAME      = '';
	var $dns_packet = NULL;
	var $ANCOUNT    = 0;
	var $cIx        = 0;
	var $arrMX      = array();
	var $dns_repl_domain;

	function __construct($domain, $dns = '4.2.2.1') {
		$this->QNAME($domain);
		$this->pack_dns_packet();

		$dns_socket = fsockopen("udp://$dns", 53);

		fwrite($dns_socket, $this->dns_packet, strlen($this->dns_packet));

		$this->dns_reply  = fread($dns_socket,1);
		$bytes            = stream_get_meta_data($dns_socket);
		$this->dns_reply .= fread($dns_socket,$bytes['unread_bytes']);

		fclose($dns_socket);

		$this->cIx       = 6;
		$this->ANCOUNT   = $this->gord(2);
		$this->cIx      += 4;

		$this->parse_data($this->dns_repl_domain);

		$this->cIx      += 7;

		for ($ic = 1; $ic <= $this->ANCOUNT; $ic++) {
			$QTYPE = ord($this->gdi($this->cIx));
			if ($QTYPE !== 1) {
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

	function __destruct() {
		return true;
	}

	function parse_data(&$retval) {
		$arName = array();
		$byte   = ord($this->gdi($this->cIx));

		while($byte !== 0) {
			if ($byte == 192) { //compressed
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

			$arName[] = $retval;
			$byte     = ord($this->gdi($this->cIx));
		}

		$retval = join('.',$arName);
	}

	function gdi(&$cIx,$bytes=1) {
		$this->cIx++;

		return(substr($this->dns_reply, $this->cIx-1, $bytes));
	}

	function QNAME($domain) {
		$dot_pos = 0;
		$temp    = '';

		while($dot_pos = strpos($domain, '.')) {
			$temp         = substr($domain, 0, $dot_pos);
			$domain       = substr($domain, $dot_pos + 1);
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
		$this->dns_packet =
			chr(0).chr(1).
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
