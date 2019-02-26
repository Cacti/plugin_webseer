<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

/* we are not talking to the browser */
$no_http_headers = true;

/* do NOT run this script through a web browser */
if (isset($_SERVER['argv'][0])) {
	die('<br>This script is only meant to run through a web service.');
}

chdir('../../');

require_once('./include/cli_check.php');
include_once($config['base_path'] . '/lib/functions.php');
include_once($config['base_path'] . '/plugins/webseer/includes/functions.php');

if (isset($_SERVER['X-Forwarded-For'])) {
	$remoteip = $_SERVER['X-Forwarded-For'];
} elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
	$remoteip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} elseif (isset($_SERVER['REMOTE_ADDR'])) {
	$remoteip = $_SERVER['REMOTE_ADDR'];
}else {
	$remoteip = '127.0.0.1';
}

//cacti_log("Remote Connection received from : $remoteip");

$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers');
$s = array();
foreach ($servers as $server) {
	if ($server['ip'] == $remoteip) {
		$action = get_nfilter_request_var('action', '');
		//cacti_log("Received $action from $remoteip");
		switch ($action) {
			case 'HEARTBEAT':
				db_execute_prepared('UPDATE plugin_webseer_servers 
					SET lastcheck = ? WHERE ip = ?',
					array(time(), $remoteip));

				break;
			case 'HOSTDOWN':
				if (isset($_POST['url_id'])) {
					db_execute_prepared('INSERT INTO plugin_webseer_servers_log
						(url_id, server, lastcheck, result, http_code, error, total_time, namelookup_time, 
						connect_time, redirect_time, redirect_count, size_download, speed_download)
						VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
						array(
							get_nfilter_request_var('url_id'), get_nfilter_request_var('server'), get_nfilter_request_var('lastcheck'), 
							get_nfilter_request_var('result'), get_nfilter_request_var('http_code'), 
							get_nfilter_request_var('error'), get_nfilter_request_var('total_time'),
							get_nfilter_request_var('namelookup_time'), get_nfilter_request_var('connect_time'), 
							get_nfilter_request_var('redirect_time'), get_nfilter_request_var('redirect_count'), 
							get_nfilter_request_var('size_download'), get_nfilter_request_var('speed_download')
						)
					);
				}

				break;
			case 'ENABLEURL':
				if (isset($_POST['id'])) {
					$id = get_filter_request_var('id');
					db_execute_prepared('UPDATE plugin_webseer_urls 
						SET enabled = ? WHERE id = ?', 
						array(($action == 'ENABLEURL' ? 'on' : ''), $id));
				}

				break;
			case 'UPDATEURL':
			case 'ADDURL':
				if (isset($_POST['id'])) {
					get_filter_request_var('id',		FILTER_VALIDATE_INT);
				//	get_filter_request_var('notify_accounts',	FILTER_VALIDATE_IS_NUMERIC_ARRAY);
					get_filter_request_var('url',		FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([\w:/\.?=&-+]+)$/')));



					get_filter_request_var('notify_extra',	FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_@,\.\+]+)$/')));
					get_filter_request_var('downtrigger',	FILTER_VALIDATE_INT);
					get_filter_request_var('ip',		FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([\d]{1,3}\.[\d]{1,3}\.[\d]{1,3}\.[\d]{1,3})$/')));
					get_filter_request_var('display_name',	FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([\w,\s]+)$/')));

					$save['id']              = get_filter_request_var('id');
					$save['enabled']         = get_nfilter_request_var('enabled', '');
					$save['requiresauth']    = get_nfilter_request_var('requiresauth', '');
					$save['proxy_server']    = get_nfilter_request_var('proxy_server', '');
					$save['checkcert']       = get_nfilter_request_var('checkcert', '');
					$save['notify_accounts'] = get_nfilter_request_var('notify_accounts', '');
					$save['url']             = get_nfilter_request_var('url');
					$save['search']          = get_nfilter_request_var('search');
					$save['search_maint']    = get_nfilter_request_var('search_maint');
					$save['search_failed']   = get_nfilter_request_var('search_failed');
					$save['notify_extra']    = get_nfilter_request_var('notify_extra');
					$save['downtrigger']     = get_filter_request_var('downtrigger');
					$save['ip']              = get_nfilter_request_var('ip');
					$save['display_name']    = get_nfilter_request_var('display_name');

					if ($action == 'UPDATEURL') {
						$id = sql_save($save, 'plugin_webseer_urls', 'id');
					} else {
						db_execute_prepared('REPLACE INTO plugin_webseer_urls 
							(id, enabled, requiresauth, proxy_server, checkcert, ip, display_name, notify_accounts, 
							url, search, search_maint, search_failed, notify_extra, downtrigger)
							VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', 
							array(
								$save['id'], $save['enabled'], $save['requiresauth'], $save['proxy_server'], 
								$save['checkcert'], $save['ip'], $save['display_name'], $save['notify_accounts'], 
								$save['url'], $save['search'], $save['search_maint'], $save['search_failed'],
								$save['notify_extra'], $save['downtrigger']
							)
						);
					}
				}

				break;
			case 'ENABLESERVER':
			case 'DISABLESERVER':
				if (isset($_POST['id'])) {
					$id = intval(get_filter_request_var('id'));

					db_execute_prepared('UPDATE plugin_webseer_servers 
						SET enabled = ? WHERE id = ?', 
						array(($action == 'ENABLESERVER' ? 1 : 0), $id));
				}

				break;
			case 'UPDATESERVER':
			case 'ADDSERVER':
				if (isset($_POST['id'])) {
					$save['id']       = get_filter_request_var('id');
					$save['enabled']  = (isset($_POST['enabled']) ? '1' : '0');
					$save['master']   = (isset($_POST['master'])  ? '1' : '0');
					$save['name']     = get_nfilter_request_var('name');
					$save['url']      = get_nfilter_request_var('url');
					$save['ip']       = get_nfilter_request_var('ip');
					$save['location'] = get_nfilter_request_var('location');

					if ($action == 'UPDATESERVER') {
						$id = sql_save($save, 'plugin_webseer_servers', 'id');
					} else {
						db_execute_prepared('REPLACE INTO plugin_webseer_servers 
							(id, enabled, master, name, url, ip, location)
							VALUES (?,?,?,?,?,?,?)', 
							array(
								$save['id'], $save['enabled'], $save['master'], 
								$save['name'], $save['url'], $save['ip'], $save['location'] 
							)
						);				
					}
				}

				break;
			case 'DELETEURL':
				if (isset($_POST['id'])) {
					$id = intval(get_filter_request_var('id'));
					db_execute_prepared('DELETE FROM plugin_webseer_urls WHERE id = ?', array($id));
					db_execute_prepared('DELETE FROM plugin_webseer_urls_log WHERE url_id = ?', array($id));
				}
				break;
			case 'SETMASTER':
				if (isset($_POST['ip'])) {
					$ip = str_replace(array("'", '\\'), '', $_POST['ip']);
					$row = db_fetch_row("SELECT * FROM plugin_webseer_servers WHERE ip = '$ip'");
					if (isset($row['id'])) {
						db_execute('UPDATE plugin_webseer_servers set master = 0');
						db_execute_prepared('UPDATE plugin_webseer_servers set master = 1 WHERE ip = ?', array($ip));
					}
				}
				break;
			case 'GETSERVERS':
				print 'SERVERS=' . base64_encode(serialize($servers));
				break;
			case 'GETURLS':
				$urls = db_fetch_assoc('SELECT * FROM plugin_webseer_urls');
				foreach ($urls as $id => $u) {
					$urls[$id]['debug'] = '';
				}
				print 'URLS=' . base64_encode(serialize($urls));
				break;
		}
	}
}

