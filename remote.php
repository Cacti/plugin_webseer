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

/* we are not talking to the browser */
$no_http_headers = true;

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br>This script is only meant to run at the command line.');
}

chdir('../../');

require_once("./include/global.php");
include_once($config["base_path"] . '/lib/functions.php');
include_once($config["base_path"] . '/plugins/webseer/functions.php');

$remoteip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']);
//cacti_log("Remote Connection received from : $remoteip");

$servers = db_fetch_assoc("SELECT * FROM plugin_webseer_servers");
$s = array();
foreach ($servers as $server) {
	if ($server['ip'] == $remoteip) {
		$action = (isset($_POST['action']) ? $_POST['action'] : '');
		//cacti_log("Received $action from $remoteip");
		switch ($action) {
			case 'HEARTBEAT':
				db_execute("UPDATE plugin_webseer_servers SET lastcheck = " . time() . " WHERE ip = '$remoteip'");
				break;
			case 'HOSTDOWN':
				if (isset($_POST['url_id'])) {
					db_execute("INSERT INTO plugin_webseer_servers_log
							 (url_id, server, lastcheck, result, http_code, error, total_time, namelookup_time, connect_time, redirect_time, redirect_count, size_download, speed_download) VALUES (" . 
							$_POST['url_id'] . ", " . 
							$_POST['server'] . ", " . 
							$_POST['lastcheck'] . ", " .
							$_POST['result'] . ", " .
							$_POST['http_code'] . ", '" .
							$_POST['error'] . "', '" .
							$_POST['total_time'] . "', '" .
							$_POST['namelookup_time'] . "', '" .
							$_POST['connect_time'] . "', '" .
							$_POST['redirect_time'] . "', '" .
							$_POST['redirect_count'] . "', '" .
							$_POST['size_download'] . "', '" .
							$_POST['speed_download'] . "')");
				}
				break;
			case 'ENABLEURL':
				if (isset($_POST['id'])) {
					$id = intval($_POST['id']);
					db_execute("UPDATE plugin_webseer_urls SET enabled = '" . ($action == 'ENABLEURL' ? 'on' : 'off') . "' WHERE id = $id");
				}
				break;
			case 'UPDATEURL':
			case 'ADDURL':
				if (isset($_POST['id'])) {
					$save['id'] = intval($_POST['id']);
					$save['enabled'] = (isset($_POST['enabled']) ? $_POST['enabled'] : 'off');
					$save['requiresauth'] = (isset($_POST['requiresauth']) ? $_POST['requiresauth'] : 'off');
					$save['checkcert'] = (isset($_POST['checkcert']) ? $_POST['checkcert'] : 'off');
					$save['notify_accounts'] = (isset($_POST['notify_accounts']) ? $_POST['notify_accounts'] : '');
					$save['url'] = $_POST['url'];
					$save['search'] = $_POST['search'];
					$save['search_maint'] = $_POST['search_maint'];
					$save['search_failed'] = $_POST['search_failed'];
					$save['notify_extra'] = $_POST['notify_extra'];
					$save['downtrigger'] = intval($_POST['downtrigger']);
					$save['ip'] = $_POST['ip'];
					$save['display_name'] = $_POST['display_name'];
					if ($action == 'UPDATEURL') {
						$id = sql_save($save, 'plugin_webseer_urls', 'id');
					} else {
						db_execute("REPLACE INTO plugin_webseer_urls (id, enabled, requiresauth, checkcert, ip, display_name, notify_accounts, url, search, search_maint, search_failed, notify_extra, downtrigger)
								VALUES (" . 
								$save['id'] . ", '" . 
								$save['enabled'] . "', '" . 
								$save['requiresauth'] . "', '" . 
								$save['checkcert'] . "', '" . 
								$save['ip'] . "', '" . 
								$save['display_name'] . "', '" . 
								$save['notify_accounts'] . "', '" . 
								$save['url'] . "', '" . 
								$save['search'] . "', '" . 
								$save['search_maint'] . "', '" . 
								$save['search_failed'] . "', '" .
								$save['notify_extra'] . "', " . 
								$save['downtrigger'] . ")");
					}
				}
				break;
			case 'ENABLESERVER':
			case 'DISABLESERVER':
				if (isset($_POST['id'])) {
					$id = intval($_POST['id']);
					db_execute("UPDATE plugin_webseer_servers SET enabled = '" . ($action == 'ENABLESERVER' ? 1 : 0) . "' WHERE id = $id");
				}
				break;
			case 'UPDATESERVER':
			case 'ADDSERVER':
				if (isset($_POST['id'])) {
					$save['id'] = intval($_POST['id']);
					$save['enabled'] = (isset($_POST['enabled']) ? '1' : '0');
					$save['master'] = (isset($_POST['master']) ? '1' : '0');
					$save['name'] = $_POST['name'];
					$save['url'] = $_POST['url'];
					$save['ip'] = $_POST['ip'];
					$save['location'] = $_POST['location'];

					if ($action == 'UPDATESERVER') {
						$id = sql_save($save, 'plugin_webseer_servers', 'id');
					} else {
						db_execute("REPLACE INTO plugin_webseer_servers (id, enabled, master, name, url, ip, location)
								VALUES (" . 
								$save['id'] . ", '" . 
								$save['enabled'] . "', " . 
								$save['master'] . ", '" . 
								$save['name'] . "', '" . 
								$save['url'] . "', '" . 
								$save['ip'] . "', '" . 
								$save['location'] . "')");
					}
				}
				break;
			case 'DELETEURL':
				if (isset($_POST['id'])) {
					$id = intval($_POST['id']);
					db_execute("DELETE FROM plugin_webseer_urls WHERE id = $id");
					db_execute("DELETE FROM plugin_webseer_url_log WHERE url_id = $id");
				}
				break;
			case 'SETMASTER':
				if (isset($_POST['ip'])) {
					$ip = str_replace(array("'", '\\'), '', $_POST['ip']);
					$row = db_fetch_row("SELECT * FROM plugin_webseer_servers WHERE ip = '$ip'");
					if (isset($row['id'])) {
						db_execute("UPDATE plugin_webseer_servers set master = 0");
						db_execute("UPDATE plugin_webseer_servers set master = 1 WHERE ip = '$ip'");
					}
				}
				break;
			case 'GETSERVERS':
				print "SERVERS=" . base64_encode(serialize($servers));
				break;
			case 'GETURLS':
				$urls = db_fetch_assoc("SELECT * FROM plugin_webseer_urls");
				foreach ($urls as $id => $u) {
					$urls[$id]['debug'] = '';
				}
				print "URLS=" . base64_encode(serialize($urls));
				break;
		}
	}
}

