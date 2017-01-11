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

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '55');

error_reporting(E_ALL ^ E_DEPRECATED);
$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}
include('./include/global.php');
include_once($config['base_path'] . '/plugins/webseer/functions.php');
include_once($config['base_path'] . '/lib/poller.php');

// Remove old Logs (ADD A SETTING!!!!!!)
$t = time() - (86400 * 30);
db_execute("DELETE FROM plugin_webseer_url_log WHERE lastcheck < $t", FALSE);
db_execute('DELETE FROM plugin_webseer_processes WHERE time < ' . (time() - 15));

$start = microtime(true);
$hosts = db_fetch_assoc('SELECT * FROM plugin_webseer_urls WHERE enabled = "on"', FALSE);

$max = 12;

for ($x = 0; $x < count($hosts); $x++) {
	$host = $hosts[$x];
	$total = db_fetch_cell('SELECT count(id) FROM plugin_webseer_processes');
	if ($max - $total > 0) {
		db_execute('INSERT INTO plugin_webseer_processes (url, time) VALUES(' . $host['id'] . ', ' . time() . ')');
		$command_string = read_config_option("path_php_binary");
		$extra_args     = '-q "' . $config["base_path"] . '/plugins/webseer/webseer_process.php" id=' . $host['id'];
		exec_background($command_string, $extra_args);
		usleep(10000);
	} else {
		$x--;
		usleep(10000);
		db_execute('DELETE FROM plugin_webseer_processes WHERE time < ' . (time() - 15));
	}
}

while(true) {
	db_execute('DELETE FROM plugin_webseer_processes WHERE time < ' . (time() - 15));
	$running = db_fetch_cell('SELECT COUNT(*) FROM plugin_webseer_processes');

	if ($running == 0) {
		break;
	}else{
		sleep(1);
	}
}

$servers = plugin_webseer_update_servers();

$end   = microtime(true);
$ttime = round($end - $start, 3);

cacti_log("STATS WEBSEER: Total Time:$ttime, Service Checks:" . sizeof($hosts) . ", Servers:" . $servers, false, 'SYSTEM');

function plugin_webseer_update_servers() {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0 AND enabled = 1');
	foreach ($servers as $server) {
		$cc = new cURL();
		$cc->host['url'] = $server['url'];
		$data = array();
		$data['action'] = 'HEARTBEAT';
		$results = $cc->post($server['url'], $data);
	}

	return sizeof($servers);
}

