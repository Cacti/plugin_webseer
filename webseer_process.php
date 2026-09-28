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

// we are not talking to the browser
$dir = __DIR__;
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

require('./include/cli_check.php');
include_once($config['base_path'] . '/plugins/webseer/includes/functions.php');

ini_set('max_execution_time', '21');

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug;

$debug           = false;
$url_id          = 0;
$poller_interval = read_config_option('poller_interval');

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--id':
				$url_id = (int) $value;

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
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

if (!function_exists('curl_init')) {
	print 'FATAL: You must install php-curl to use this Plugin' . PHP_EOL;
}

if (empty($url_id)) {
	print "ERROR: You must specify a URL id\n";
	exit(1);
}

plugin_webseer_check_debug();

$url = db_fetch_row_prepared('SELECT *
	FROM plugin_webseer_urls
	WHERE enabled = "on"
	AND id = ?',
	[$url_id]);

$url = is_array($url) ? $url : [];

if (!cacti_sizeof($url)) {
	print "ERROR: URL is not Found\n";
	exit(1);
}

if (api_plugin_is_enabled('maint')) {
	include_once($config['base_path'] . '/plugins/maint/functions.php');
}

if (function_exists('plugin_maint_check_webseer_url')) {
	if (plugin_maint_check_webseer_url($url_id)) {
		plugin_webseer_debug('Maintenance schedule active, skipped ' , $url);
		exit(0);
	}
}

$url['debug_type'] = 'Url';
register_startup($url_id, $url['poller_id']);

if ($url['url'] != '') {
	// attempt to get results 3 times before exiting
	$x       = 0;
	$results = [];

	while ($x < 3) {
		plugin_webseer_debug('Service Check Number ' . $x, $url);

		switch ($url['type']) {
			case 'http':
			case 'https':
				$cc = new cURL(true, 'cookies.txt', $url['compression'], '', $url);

				if ($url['proxy_server'] > 0) {
					$proxy = db_fetch_row_prepared('SELECT *
						FROM plugin_webseer_proxies
						WHERE id = ?',
						[$url['proxy_server']]);

					$proxy = is_array($proxy) ? $proxy : [];

					if (cacti_sizeof($proxy)) {
						$cc->proxy_hostname = $proxy['hostname'];

						if ($url['type'] == 'http') {
							$cc->proxy_http_port  = $proxy['http_port'];
						} else {
							$cc->proxy_https_port = $proxy['https_port'];
						}

						if ($proxy['username'] != '') {
							$cc->proxy_username = $proxy['username'];
						}

						if ($proxy['password'] != '') {
							$cc->proxy_password = $proxy['password'];
						}
					} else {
						cacti_log('ERROR: Unable to obtain Proxy settings');
					}
				}

				$results         = $cc->get();
				$results['data'] = $cc->data;

				break;
			case 'dns':
				$results = plugin_webseer_check_dns($url);

				break;
		}

		$results = is_array($results) ? $results : [];

		if ($results['result']) {
			break;
		}

		$x++;

		usleep(10000);
	}

	// Do calculations for triggering
	$pi = read_config_option('poller_interval');
	$t  = time() - ($url['downtrigger'] * 60);
	$lc = time() - ($pi * 2);

	$ts = db_fetch_cell_prepared('SELECT count(id)
		FROM plugin_webseer_servers
		WHERE isme = 1
		OR (isme = 0 AND UNIX_TIMESTAMP(lastcheck) > ?)',
		[$lc]);

	$tf = ($ts * ($url['downtrigger'] - 1)) + 1;

	$url['failures'] = db_fetch_cell_prepared('SELECT COUNT(url_id)
		FROM plugin_webseer_servers_log
		WHERE UNIX_TIMESTAMP(lastcheck) > ?
		AND url_id = ?',
		[$t, $url['id']]);

	plugin_webseer_debug('pi:' . $pi . ', t:' . $t . ' (' . date('Y-m-d H:i:s', $t) . '), lc:' . $lc . ' (' . date('Y-m-d H:i:s', $lc) . '), ts:' . $ts . ', tf:' . $tf, $url);

	plugin_webseer_debug('failures:' . $url['failures'] . ', triggered:' . $url['triggered'], $url);

	if (strtotime($url['lastcheck']) > 0 && (($url['result'] != '' && $url['result'] != $results['result']) || $url['failures'] > 0 || $url['triggered'] == 1)) {
		plugin_webseer_debug('Checking for trigger', $url);

		$sendemail = false;

		if ($results['result'] == 0) {
			$url['failures']++;

			if ($url['failures'] >= ($url['downtrigger'] * 60) / $poller_interval && $url['triggered'] == 0) {
				$sendemail        = true;
				$url['triggered'] = 1;
			}
		}

		if ($results['result'] == 1) {
			if ($url['failures'] == 0 && $url['triggered'] == 1) {
				$sendemail        = true;
				$url['triggered'] = 0;
			}
		}

		db_execute_prepared('INSERT INTO plugin_webseer_urls_log
			(url_id, lastcheck, compression, result, http_code, error,
			total_time, namelookup_time, connect_time, redirect_time,
			redirect_count, size_download, speed_download)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			[$url['id'], date('Y-m-d H:i:s', $results['time']),
				$results['options']['compression'], $results['result'],
				$results['options']['http_code'], $results['error'],
				$results['options']['total_time'], $results['options']['namelookup_time'],
				$results['options']['connect_time'], $results['options']['redirect_time'],
				$results['options']['redirect_count'], $results['options']['size_download'],
				$results['options']['speed_download']
			]
		);

		if ($sendemail) {
			plugin_webseer_debug('Time to send email to admins', $url);

			if (plugin_webseer_amimaster()) {
				if ($url['notify_format'] == WEBSEER_FORMAT_PLAIN) {
					plugin_webseer_get_users($results, $url, 'text');
				} else {
					plugin_webseer_get_users($results, $url, '');
				}
			}
		}
	} else {
		plugin_webseer_debug('Not checking for trigger', $url);
	}

	plugin_webseer_debug('Updating Statistics', $url);

	db_execute_prepared('UPDATE plugin_webseer_urls
		SET result = ?, triggered = ?, failures = ?,
		lastcheck = ?, error = ?, http_code = ?, total_time = ?, namelookup_time = ?,
		connect_time = ?, redirect_time = ?, redirect_count = ?, speed_download = ?,
		size_download = ?, debug = ?
		WHERE id = ?',
		[$results['result'], $url['triggered'], $url['failures'], date('Y-m-d H:i:s', $results['time']),
			$results['error'], $results['options']['http_code'], $results['options']['total_time'],
			$results['options']['namelookup_time'], $results['options']['connect_time'],
			$results['options']['redirect_time'], $results['options']['redirect_count'],
			$results['options']['speed_download'], $results['options']['size_download'],
			$results['data'], $url['id']
		]
	);

	if ($results['result'] == 0) {
		$save                    = [];
		$save['url_id']          = $url['id'];
		$save['server']          = plugin_webseer_whoami();
		$save['lastcheck']       = date('Y-m-d H:i:s', $results['time']);
		$save['result']          = $results['result'];
		$save['http_code']       = $results['options']['http_code'];
		$save['error']           = $results['error'];
		$save['total_time']      = $results['options']['total_time'];
		$save['namelookup_time'] = $results['options']['namelookup_time'];
		$save['connect_time']    = $results['options']['connect_time'];
		$save['redirect_time']   = $results['options']['redirect_time'];
		$save['redirect_count']  = $results['options']['redirect_count'];
		$save['size_download']   = $results['options']['size_download'];
		$save['speed_download']  = $results['options']['speed_download'];

		plugin_webseer_down_remote_hosts($save);

		db_execute_prepared('INSERT INTO plugin_webseer_servers_log
			(url_id, server, lastcheck, result, http_code, error, total_time,
			namelookup_time, connect_time, redirect_time, redirect_count,
			size_download, speed_download)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			[$url['id'], plugin_webseer_whoami(), date('Y-m-d H:i:s', $results['time']),
				$results['result'], $results['options']['http_code'], $results['error'],
				$results['options']['total_time'], $results['options']['namelookup_time'],
				$results['options']['connect_time'], $results['options']['redirect_time'],
				$results['options']['redirect_count'], $results['options']['size_download'],
				$results['options']['speed_download']]
		);
	}

	db_execute_prepared('UPDATE plugin_webseer_servers
		SET lastcheck=NOW()
		WHERE id = ?',
		[plugin_webseer_whoami()]);
}

// register process end
register_shutdown($url_id, $url['poller_id']);

// purge old entries from the log

db_execute_prepared('DELETE FROM plugin_webseer_servers_log
	WHERE lastcheck < FROM_UNIXTIME(?)',
	[time() - (86400 * 90)]);

// exit

/**
 * Records this worker process's pid in plugin_webseer_processes so it
 * can be tracked/detected as running. Called from this script's main
 * flow before performing a URL's service check.
 *
 * @param int $url_id    The plugin_webseer_urls.id being checked.
 * @param int $poller_id The poller id this check is running on.
 *
 * @return void
 */
function register_startup($url_id, $poller_id) {
	db_execute_prepared('INSERT INTO plugin_webseer_processes
		(url_id, poller_id, pid, time)
		VALUES(?, ?, ?, NOW())',
		[$url_id, $poller_id, getmypid()]);
}

/**
 * Removes this worker process's tracking row from
 * plugin_webseer_processes. Called from this script's main flow after a
 * URL's service check completes.
 *
 * @param int $url_id    The plugin_webseer_urls.id that was checked.
 * @param int $poller_id The poller id this check ran on.
 *
 * @return void
 */
function register_shutdown($url_id, $poller_id) {
	db_execute_prepared('DELETE FROM plugin_webseer_processes
		WHERE url_id = ?
		AND poller_id = ?
		AND pid = ?',
		[$url_id, $poller_id, getmypid()], false);
}

/**
 * Resolves a service check's configured notification recipients
 * (individual contacts, extra addresses, and/or a notification list),
 * builds a plain-text or HTML down/recovery message, and sends it to
 * each recipient. Called from this script's main flow when a check's
 * result triggers a down/recovery notification.
 *
 * @param array  $results The check result data (result code, error,
 *                        timing/HTTP details) from the service check.
 * @param array  $url     The plugin_webseer_urls row being checked,
 *                        providing its notification configuration.
 * @param string $type    'text' to send a plain-text message, anything
 *                        else to send the richer HTML message.
 *
 * @return void
 *
 * @global array $httperrors Map of HTTP status codes to their
 *                           descriptions, used in the HTML message body.
 */
function plugin_webseer_get_users($results, $url, $type) {
	global $httperrors;

	$users = '';

	if ($url['notify_accounts'] != '') {
		$users = db_fetch_cell('SELECT GROUP_CONCAT(DISTINCT data) AS emails
			FROM plugin_webseer_contacts
			WHERE id IN (' . $url['notify_accounts'] . ')');
	}

	if ($users == '' && isset($url['notify_extra']) && $url['notify_extra'] == '' && $url['notify_list'] <= 0) {
		cacti_log('ERROR: No users to send WEBSEER Notification for ' . $url['display_name'], false, 'WEBSEER');

		return;
	}

	$to = $users;

	if (read_config_option('thold_disable_legacy') == 'on' && ($to != '' || $url['notify_extra'] != '')) {
		cacti_log(sprintf('WARNING: WebSeer Service Check %s has individual Emails specified and Disable Legacy Notification is Enabled.', $url['display_name']), false, 'WEBSEER');
	}

	if ($url['notify_extra'] != '') {
		$to .= ($to != '' ? ', ' : '') . $url['notify_extra'];
	}

	if ($url['notify_list'] > 0) {
		$emails = db_fetch_cell_prepared('SELECT emails
			FROM plugin_notification_lists
			WHERE id = ?',
			[$url['notify_list']]);

		if ($emails != '') {
			$to .= ($to != '' ? ', ' : '') . $emails;
		}
	}

	if ($type == 'text') {
		if ($results['result'] == 0) {
			$subject = 'Site Down: ' . ($url['display_name'] != '' ? $url['display_name'] : $url['url']);
		} else {
			$subject = 'Site Recovered: ' . ($url['display_name'] != '' ? $url['display_name'] : $url['url']);
		}

		$message  = 'Site ' . ($results['result'] == 0 ? 'Down: ' : 'Recovering: ') . ($url['display_name'] != '' ? $url['display_name'] : '') . "\n";
		$message .= 'URL: ' . $url['url'] . "\n";
		$message .= 'Error: ' . $results['error'] . "\n";
		$message .= 'Total Time: ' . $results['options']['total_time'] . "\n";
	} else {
		if ($results['result'] == 0) {
			$subject = 'Site Down: ' . ($url['display_name'] != '' ? $url['display_name'] : $url['url']);
		} else {
			$subject = 'Site Recovered: ' . ($url['display_name'] != '' ? $url['display_name'] : $url['url']);
		}

		$message  = '<h3>' . $subject . "</h3>\n";
		$message .= '<hr>';

		$message .= "<table>\n";
		$message .= '<tr><td>URL:</td><td>' . $url['url'] . "</td></tr>\n";
		$message .= '<tr><td>Status:</td><td>' . ($results['result'] == 0 ? 'Down' : 'Recovering') . "</td></tr>\n";
		$message .= '<tr><td>Date:</td><td>' . date('F j, Y - h:i:s', $results['time']) . "</td></tr>\n";
		$message .= '<tr><td>HTTP Code:</td><td>' . $httperrors[$results['options']['http_code']] . "</td></tr>\n";

		if ($results['error'] != '') {
			$message .= '<tr><td>Error:</td><td>' . $results['error'] . "</td></tr>\n";
		}
		$message .= "</table>\n";

		$message .= '<hr>';

		if ($results['error'] > 0) {
			$message .= "<table>\n";
			$message .= '<tr><td>Total Time:</td><td> ' . round($results['options']['total_time'],4) . "</td></tr>\n";
			$message .= '<tr><td>Connect Time:</td><td> ' . round($results['options']['connect_time'],4) . "</td></tr>\n";
			$message .= '<tr><td>DNS Time:</td><td> ' . round($results['options']['namelookup_time'],4) . "</td></tr>\n";
			$message .= '<tr><td>Redirect Time:</td><td> ' . round($results['options']['redirect_time'],4) . "</td></tr>\n";
			$message .= '<tr><td>Redirect Count:</td><td> ' . round($results['options']['redirect_count'],4) . "</td></tr>\n";
			$message .= '<tr><td>Download Size:</td><td> ' . round($results['options']['size_download'],4) . ' Bytes' . "</td></tr>\n";
			$message .= '<tr><td>Download Speed:</td><td> ' . round($results['options']['speed_download'],4) . ' Bps' . "</td></tr>\n";
			$message .= "</table>\n";

			$message .= '<hr>';
		}
	}

	$users = explode(',', $to);

	foreach ($users as $u) {
		plugin_webseer_send_email($u, $subject, $message);
	}
}

/**
 * Determines whether this poller is registered as the webseer master
 * server (by matching its detected IP against plugin_webseer_servers).
 * Called from this script's main flow to decide whether this process
 * should send down/recovery notification emails.
 *
 * @return bool True if this poller is the registered master server,
 *              false otherwise.
 */
function plugin_webseer_amimaster() {
	if (function_exists('gethostname')) {
		$hostname = gethostname();
	} else {
		$hostname = false;
	}

	if ($hostname === false) {
		$hostname = php_uname('n');
	}

	$ipaddress = gethostbyname($hostname);

	$server    = db_fetch_cell_prepared('SELECT id
		FROM plugin_webseer_servers
		WHERE ip = ?
		AND master = 1',
		[$ipaddress]);

	if ($server) {
		return true;
	}

	return false;
}

/**
 * Looks up this poller's own plugin_webseer_servers.id by matching its
 * detected IP address. Called from this script's main flow to record
 * which server performed a given check in the servers log.
 *
 * @return int The matching plugin_webseer_servers.id, or 0 if this
 *             poller is not registered.
 */
function plugin_webseer_whoami() {
	if (function_exists('gethostname')) {
		$hostname = gethostname();
	} else {
		$hostname = false;
	}

	if ($hostname === false) {
		$hostname = php_uname('n');
	}

	$ipaddress = gethostbyname($hostname);
	$server    = db_fetch_cell_prepared('SELECT id
		FROM plugin_webseer_servers
		WHERE ip = ?',
		[$ipaddress]);

	if ($server) {
		return $server;
	}

	return 0;
}

/**
 * Sends a down/recovery notification email to a single recipient via
 * Cacti's mailer(), tagging the User-Agent with the plugin/Cacti
 * version. Called from plugin_webseer_get_users() for each resolved
 * recipient address.
 *
 * @param string $to      The recipient email address.
 * @param string $subject The email subject line.
 * @param string $message The HTML (or plain-text) email body; a
 *                        stripped-tags plain-text alternative is
 *                        derived from it automatically.
 *
 * @return void
 */
function plugin_webseer_send_email($to, $subject, $message) {
	$from_name  = read_config_option('settings_from_name');
	$from_email = read_config_option('settings_from_email');

	if ($from_name != '') {
		$from[0] = $from_email;
		$from[1] = $from_name;
	} else {
		$from    = $from_email;
	}

	if (defined('CACTI_VERSION')) {
		$v = CACTI_VERSION;
	} else {
		$v = get_cacti_version();
	}

	$headers['User-Agent'] = 'Cacti-WebSeer-v' . $v;

	$message_text = strip_tags($message);

	mailer($from, $to, '', '', '', $subject, $message, $message_text, '', $headers);
}

/**
 * display_version - displays version information
 *
 * Prints this script's name/plugin version/copyright. Called from the
 * CLI argument parser for the '--version' flag, and from display_help()
 * to prefix the usage text.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate and load setup.php for the version
 *                       lookup.
 */
function display_version() {
	global $config;

	if (!function_exists('plugin_webseer_version')) {
		include_once($config['base_path'] . '/plugins/webseer/setup.php');
	}

	$info = plugin_webseer_version();

	print 'Cacti Web Service Check Processor, Version ' . (isset($info['version']) ? $info['version'] : 'unknown') . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays the usage of the function
 *
 * Prints this script's version banner followed by its command-line
 * usage/argument summary. Called from the CLI argument parser for the
 * '--help' flag, and whenever an invalid argument is supplied.
 *
 * @return void
 */
function display_help() {
	display_version();

	print PHP_EOL;
	print 'usage: webseer_process.php --id=N [--debug]' . PHP_EOL . PHP_EOL;
	print 'This binary will run the Web Service check for the WebSeer plugin.' . PHP_EOL . PHP_EOL;
	print '--id=N     - The url ID from the WebSeer database.' . PHP_EOL;
	print '--debug    - Display verbose output during execution' . PHP_EOL . PHP_EOL;
}
