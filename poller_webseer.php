<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '55');

$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

include('./include/cli_check.php');
include_once($config['base_path'] . '/plugins/webseer/includes/functions.php');
include_once($config['base_path'] . '/lib/poller.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug = false;
$force = false;
$start = microtime(true);

$poller_id = $config['poller_id'];

if (cacti_sizeof($parms)) {
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
				$force = true;
				break;
			case '-d':
			case '--debug':
				$debug = true;
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

if (!function_exists('curl_init')) {
	print "FATAL: You must install php-curl to use this Plugin" . PHP_EOL;
}

plugin_webseer_check_debug();

print "Running Service Checks\n";

plugin_webseer_register_server();

// Remove old Logs (ADD A SETTING!!!!!!)
$t = time() - (86400 * 30);

if ($poller_id == 1) {
	db_execute_prepared('DELETE FROM plugin_webseer_urls_log
		WHERE lastcheck < FROM_UNIXTIME(?)',
		array($t));

	db_execute_prepared('DELETE FROM plugin_webseer_processes
		WHERE time < FROM_UNIXTIME(?)',
		array(time() - 15));
}

$urls = db_fetch_assoc_prepared('SELECT *
	FROM plugin_webseer_urls
	WHERE enabled = "on"
	AND poller_id = ?',
	array($poller_id));

$max = 12;

if (cacti_sizeof($urls)) {
	foreach($urls as $url) {
		$total = db_fetch_cell_prepared('SELECT COUNT(id)
			FROM plugin_webseer_processes
			WHERE poller_id = ?',
			array($poller_id));

		if ($max - $total > 0) {
			$url['debug_type'] = 'Url';

			plugin_webseer_debug('Launching Service Check ' . $url['url'], $url);

			$command_string = read_config_option('path_php_binary');
			$extra_args     = '-q "' . $config['base_path'] . '/plugins/webseer/webseer_process.php" --id=' . $url['id'] . ($debug ? ' --debug':'');
			exec_background($command_string, $extra_args);

			usleep(10000);
		} else {
			usleep(10000);

			db_execute_prepared('DELETE FROM plugin_webseer_processes
				WHERE time < FROM_UNIXTIME(?)
				AND poller_id = ?',
				array(time() - 15, $poller_id));
		}
	}
}

while(true) {
	db_execute_prepared('DELETE FROM plugin_webseer_processes
		WHERE time < FROM_UNIXTIME(?)
		AND poller_id = ?',
		array(time() - 15, $poller_id));

	$running = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_webseer_processes
		WHERE poller_id = ?',
		array($poller_id));

	if ($running == 0) {
		break;
	} else {
		sleep(1);
	}
}

$servers = plugin_webseer_update_servers();

$end   = microtime(true);
$ttime = round($end - $start, 2);

$stats = 'Time:' . $ttime . ' Checks:' . sizeof($urls) . ' Servers:' . $servers;

cacti_log("WEBSEER STATS: $stats", false, 'SYSTEM');

if ($poller_id == 1) {
	set_config_option('stats_webseer', $stats);
}

set_config_option('stats_webseer_' . $poller_id, $stats);

function plugin_webseer_register_server() {
	global $config;

	$lastcheck = date('Y-m-d H:i:s');

	if (function_exists('gethostname')) {
		$hostname = gethostname();
	} else {
		$hostname = php_uname('n');
	}

	$ipaddress = gethostbyname($hostname);

	$found = db_fetch_cell_prepared('SELECT id
		FROM plugin_webseer_servers
		WHERE ip = ?',
		array($ipaddress));

	if (!$found) {
		$found['debug_type'] = 'Server';

		plugin_webseer_debug('Registering Server ' . $ipaddress, $found);

		$save = array();
		$save['enabled']   = 'on';
		$save['isme']      = 1;
		$save['lastcheck'] = $lastcheck;
		$save['ip']        = $ipaddress;

		if (isset($config['poller_id']) && $config['poller_id'] == 1) {
			$save['master'] = 1;
			$save['name']   = __('Cacti Master');
		} else {
			$save['master'] = 0;
			$save['name']   = __('Cacti Remote Server');
		}

		if (substr_count($hostname, '.') > 0) {
			$urlhost = $hostname;
		} else {
			$urlhost = $ipaddress;
		}

		if (isset($config['url_path'])) {
			$save['url'] = (read_config_option('force_https') == 'on' ? 'https://':'http://') . $urlhost . $config['url_path'] . 'index.php';
		} else {
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

	if ($servers !== false && cacti_sizeof($servers)) {
		foreach ($servers as $server) {
			$server['debug_type'] = 'Server';

			$cc = new cURL(true, 'cookies.txt', $server['compression'], '', $server);;

			$data = array();
			$data['action'] = 'HEARTBEAT';
			$results = $cc->post($server['url'], $data);
		}
	}

	return cacti_sizeof($servers);
}

/**
 * display_version - displays version information
 */
function display_version() {
	global $config;

	if (!function_exists('plugin_webseer_version')) {
		include_once($config['base_path'] . '/plugins/webseer/setup.php');
	}

    $info = plugin_webseer_version();

    print "Cacti Service Check Master Process, Version " . $info['version'] . ", " . COPYRIGHT_YEARS . "\n";
}

/**
 * display_help - displays the usage of the function
 */
function display_help () {
    display_version();

    print "\nusage: poller_webseer.php [--debug] [--force]\n\n";
	print "This binary will exec all the Web Service check child processes.\n\n";
    print "--force    - Force all the service checks to run now\n";
    print "--debug    - Display verbose output during execution\n\n";
}

