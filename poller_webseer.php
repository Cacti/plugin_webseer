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

$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}
include('./include/global.php');
include_once($config['base_path'] . '/plugins/webseer/functions.php');
include_once($config['base_path'] . '/lib/poller.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug = false;
$force = false;
$start = microtime(true);

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-f':
			case '--force':
				$force = TRUE;
				break;
			case '-d':
			case '--debug':
				$debug = TRUE;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:
				print "ERROR: Invalid Parameter " . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

echo "Running Service Checks\n";

plugin_webseer_register_server();

// Remove old Logs (ADD A SETTING!!!!!!)
$t = time() - (86400 * 30);

db_execute_prepared('DELETE FROM plugin_webseer_url_log 
	WHERE lastcheck < ?', 
	array($t));

db_execute_prepared('DELETE FROM plugin_webseer_processes 
	WHERE UNIX_TIMESTAMP(time) < ?', 
	array(time() - 15));

$urls = db_fetch_assoc('SELECT * 
	FROM plugin_webseer_urls 
	WHERE enabled = "on"');

$max = 12;

for ($x = 0; $x < count($urls); $x++) {
	$url   = $urls[$x];
	$total = db_fetch_cell('SELECT count(id) 
		FROM plugin_webseer_processes');

	if ($max - $total > 0) {
		debug('Launching Service Check ' . $urls[$x]['url']);
		$command_string = read_config_option('path_php_binary');
		$extra_args     = '-q "' . $config['base_path'] . '/plugins/webseer/webseer_process.php" --id=' . $url['id'] . ($debug ? ' --debug':'');
		exec_background($command_string, $extra_args);
		usleep(10000);
	} else {
		$x--;
		usleep(10000);

		db_execute('DELETE FROM plugin_webseer_processes 
			WHERE UNIX_TIMESTAMP(time) < ' . (time() - 15));
	}
}

while(true) {
	db_execute('DELETE FROM plugin_webseer_processes 
		WHERE UNIX_TIMESTAMP(time) < ' . (time() - 15));

	$running = db_fetch_cell('SELECT COUNT(*) 
		FROM plugin_webseer_processes');

	if ($running == 0) {
		break;
	}else{
		sleep(1);
	}
}

$servers = plugin_webseer_update_servers();

$end   = microtime(true);
$ttime = round($end - $start, 3);

cacti_log("WEBSEER STATS: Total Time:$ttime, Service Checks:" . sizeof($urls) . ", Servers:" . $servers, false, 'SYSTEM');

function plugin_webseer_register_server() {
	global $config;

	$lastcheck = date('Y-m-d H:i:s');

	if (function_exists('gethostname')) {
		$hostname = gethostname();
	}else{
		$hostname = php_uname('n');
	}

	$ipaddress = gethostbyname($hostname);

	$found = db_fetch_cell_prepared('SELECT id 
		FROM plugin_webseer_servers 
		WHERE ip = ?', 
		array($ipaddress));

	if (!$found) {
		debug('Registering Server');

		$save = array();
		$save['enabled']   = 'on';
		$save['isme']      = 1;
		$save['lastcheck'] = $lastcheck;
		$save['ip']        = $ipaddress;

		if (isset($config['poller_id']) && $config['poller_id'] == 1) {
			$save['master'] = 1;
			$save['name']   = __('Cacti Master');
		}else{
			$save['master'] = 0;
			$save['name']   = __('Cacti Remote Server');
		}

		if (substr_count($hostname, '.') > 0) {
			$urlhost = $hostname;
		}else{
			$urlhost = $ipaddress;
		}

		if (isset($config['url_path'])) {
			$save['url'] = (read_config_option('force_https') == 'on' ? 'https://':'http://') . $urlhost . $config['url_path'] . 'index.php';
		}else{
			$save['url'] = 'http://' . $urlhost;
		}

		$id = sql_save($save, 'plugin_webseer_servers');
	}
}

function plugin_webseer_update_servers() {
	$servers = db_fetch_assoc('SELECT * 
		FROM plugin_webseer_servers 
		WHERE isme = 0 
		AND enabled = 1');

	foreach ($servers as $server) {
		$cc = new cURL();
		$cc->host['url'] = $server['url'];
		$data = array();
		$data['action'] = 'HEARTBEAT';
		$results = $cc->post($server['url'], $data);
	}

	return sizeof($servers);
}

function debug($message) {
	global $debug;

	if ($debug) {
		print "DEBUG: " . trim($message) . "\n";
	}
}

/*  display_version - displays version information */
function display_version() {
	global $config;

	if (!function_exists('plugin_webseer_version')) {
		include_once($config['base_path'] . '/plugins/webseer/setup.php');
	}

    $info = plugin_webseer_version();
    echo "Cacti Web Service Check Master Process, Version " . $info['version'] . ", " . COPYRIGHT_YEARS . "\n";
}

/*  display_help - displays the usage of the function */
function display_help () {
    display_version();

    echo "\nusage: poller_webseer.php [--debug] [--force]\n\n";
	echo "This binary will exec all the Web Service check child processes.\n\n";
    echo "--force    - Force all the service checks to run now\n";
    echo "--debug    - Display verbose output during execution\n\n";
}
