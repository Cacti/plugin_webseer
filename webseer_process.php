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
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}
include('./include/global.php');
include_once($config['base_path'] . '/plugins/webseer/functions.php');

ini_set('max_execution_time', '21');

if ($_SERVER['argc'] == '2') {
	$h = explode('=', $_SERVER['argv'][1]);
	if ($h[0] == 'id' && intval($h[1]) == $h[1]) {

		$host = db_fetch_row_prepared('SELECT * FROM plugin_webseer_urls WHERE enabled = "on" AND id = ?', array($h[1]), FALSE);

		if (isset($host['url'])) {
			$x = 0;
			while ($x < 2) {
				switch ($host['type']) {
					case 'http':
						$cc = new cURL();
						$cc->host = $host;
						$results = $cc->get($host['url']);
						$results['data'] = $cc->data;
						break;
					case 'dns':
						$results = plugin_webseer_check_dns($host);
						break;
				}
				if ($results['result'] > 0) {
					$x = 3;
				}
				$x++;
			}

			$t  = (intval(time()/60) * 60) - ($host['downtrigger'] * 60);
			$lc = (intval(time()/60) * 60) - 120;
			$ts = db_fetch_cell_prepared('SELECT count(id) FROM plugin_webseer_servers WHERE isme = 1 OR (isme = 0 and lastcheck > ?)', array($lc));
			$tf = ($ts * ($host['downtrigger'] - 1)) + 1;
			
			$host['failures'] = db_fetch_cell_prepared('SELECT count(url_id) FROM plugin_webseer_servers_log WHERE lastcheck > ? AND url_id = ?', array($t, $host['id']));
//			$host['failures'] = $host['failures'] * .66;

			if ($host['lastcheck'] > 0 && (($host['result'] != $results['result']) || $host['failures'] > 0 || $host['triggered'] == 1)) {
				$sendemail = false;

				if ($results['result'] == 0) {
					$host['failures'] = $host['failures'] + 1;
//					//////////if ($host['failures'] > $host['downtrigger'] && $host['triggered'] == 0) {
					if ($host['failures'] >= $tf && $host['triggered'] == 0) {
						$sendemail = true;
						$host['triggered'] = 1;
					}
				}

				if ($results['result'] == 1) {
					if ($host['failures'] == 0 && $host['triggered'] == 1) {
						$sendemail = true;
						$host['triggered'] = 0;
					}
				}

				if ($sendemail) {
					db_execute_prepared("INSERT INTO plugin_webseer_url_log
						(url_id, lastcheck, result, http_code, error, total_time, namelookup_time, connect_time, redirect_time, redirect_count, size_download, speed_download) 
						VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
						array($host['id'], $results['time'], $results['result'], $results['options']['http_code'],
							$results['error'], $results['options']['total_time'], $results['options']['namelookup_time'],
							$results['options']['connect_time'], $results['options']['redirect_time'], $results['options']['redirect_count'],
							$results['options']['size_download'], $results['options']['speed_download']
						)
					);

					if (plugin_webseer_amimaster ()) {
						plugin_webseer_get_users($results, $host, '');
						//plugin_webseer_get_users($results, $host, 'text');
					}
				}
			}

			db_execute_prepared('UPDATE plugin_webseer_urls SET result = ?, triggered = ?, failures = ?, lastcheck = ?, error = ?, http_code = ?,
				total_time = ?, namelookup_time = ?, connect_time = ?, redirect_time = ?, redirect_count = ?, speed_download = ?, size_download = ?, debug = ? 
				WHERE id = ?',
				array( $results['result'], $host['triggered'], $host['failures'], $results['time'], $results['error'], $results['options']['http_code'],
					$results['options']['total_time'], $results['options']['namelookup_time'], $results['options']['connect_time'], $results['options']['redirect_time'], 
					$results['options']['redirect_count'], $results['options']['speed_download'], $results['options']['size_download'], $results['data'], $host['id']
				)
			);

			if ($results['result'] == 0) {
				$save = array();
				$save['url_id'] = $host['id'];
				$save['server'] = plugin_webseer_whoami();
				$save['lastcheck'] = $results['time'];
				$save['result'] = $results['result'];
				$save['http_code'] = $results['options']['http_code'];
				$save['error'] = $results['error'];
				$save['total_time'] = $results['options']['total_time'];
				$save['namelookup_time'] = $results['options']['namelookup_time'];
				$save['connect_time'] = $results['options']['connect_time'];
				$save['redirect_time'] = $results['options']['redirect_time'];
				$save['redirect_count'] = $results['options']['redirect_count'];
				$save['size_download'] = $results['options']['size_download'];
				$save['speed_download'] = $results['options']['speed_download'];
				plugin_webseer_down_remote_hosts ($save);

				db_execute_prepared('INSERT INTO plugin_webseer_servers_log
					(url_id, server, lastcheck, result, http_code, error, total_time, namelookup_time, connect_time, redirect_time, redirect_count, size_download, speed_download) 
					VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', 
					array(
						$host['id'], plugin_webseer_whoami(), $results['time'], $results['result'], $results['options']['http_code'], $results['error'],
						$results['options']['total_time'], $results['options']['namelookup_time'], $results['options']['connect_time'], $results['options']['redirect_time'],
						$results['options']['redirect_count'], $results['options']['size_download'], $results['options']['speed_download']

					)
				);
			}
		}

		db_execute_prepared('DELETE FROM plugin_webseer_processes WHERE url = ?', array($h[1]), FALSE);
	}

	db_execute('DELETE FROM plugin_webseer_servers_log WHERE lastcheck < ' . (time() - (86400 * 90)), FALSE);
}

function plugin_webseer_get_users($results, $host, $type) {
	if ($type == 'text') {
		//$sql = "SELECT data FROM plugin_notification_lists WHERE `type` = 'text' AND  (id = " . ($host['notify_accounts'] != '' ? implode(' OR id = ', explode(',', $host['notify_accounts'])) . ')' : '0)');
		//$users = db_fetch_assoc($sql);
		$users = array();

	} else {
		if ($host['notify_accounts'] != '') {
			$users = db_fetch_assoc_prepared('SELECT emails FROM plugin_notification_lists WHERE id = ?', array($host['notify_accounts']));
		} else {
			$users = array();
		}
	}

	$to = '';
	$u = array();
	if (!empty($users)) {
		foreach ($users as $user) {
			$u[] = $user['emails'];
		}
	}

	$to = implode(',', $u);			
	if ($host['notify_extra'] != '') {
		if ($to != '') {
			$to .= ',';
		}
		$to .= $host['notify_extra'];
	}

	if ($type == 'text') {
		$subject = '';
		$message = "Site " . ($results['result'] == 0 ? 'Down' : 'Recovering') . "\n";
		$message .= "URL: " . $host['url'] . "\n";
		$message .= "Error: " . $results['error'] . "\n";
		$message .= "Total Time: " . $results['options']['total_time'] . "\n";
	} else {
		if ($results['result'] == 0) {
			$subject = "Site Down: " . ($host['display_name'] != '' ? $host['display_name'] : $host['url']);
		} else {
			$subject = "Site Recovered: " . ($host['display_name'] != '' ? $host['display_name'] : $host['url']);
		}

		$message = "-------------------------------------------------------\n";
		$message .= "URL: " . $host['url'] . "\n";
		$message .= "Status: " . ($results['result'] == 0 ? 'Down' : 'Recovering') . "\n";
		$message .= "Date: " . date('F j, Y - h:i:s', $results['time']) . "\n";
		$message .= "HTTP Code: " . $results['options']['http_code'] . "\n";
		$message .= "Error: " . $results['error'] . "\n";
		$message .= "-------------------------------------------------------\n";
		$message .= "Total Time: " . $results['options']['total_time'] . "\n";
		$message .= "Connect Time: " . $results['options']['connect_time'] . "\n";
		$message .= "DNS Time: " . $results['options']['namelookup_time'] . "\n";
		$message .= "Redirect Time: " . $results['options']['redirect_time'] . "\n";
		$message .= "Redirect Count: " . $results['options']['redirect_count'] . "\n";
		$message .= "Download Size: " . $results['options']['size_download'] . " Bytes\n";
		$message .= "Download Speed: " . $results['options']['speed_download'] . " Bps\n";
		$message .= "-------------------------------------------------------\n";
	}

	$users = explode(',', $to);
	foreach ($users as $u) {
		plugin_webseer_send_email($u, $subject, $message);
	}
}

function plugin_webseer_amimaster () {
	$server = db_fetch_row('SELECT * FROM plugin_webseer_servers WHERE isme = 1 AND master = 1', FALSE);
	if (isset($server['ip'])) {
		return 1;
	}
	return 0;
}

function plugin_webseer_whoami () {
	$server = db_fetch_row('SELECT * FROM plugin_webseer_servers WHERE isme = 1', FALSE);
	if (isset($server['id'])) {
		return $server['id'];
	}
	return 0;
}

function plugin_webseer_send_email($to, $subject, $message, $type = '') {
	webseer_send_mail($to, $subject, $message, $type);
}

