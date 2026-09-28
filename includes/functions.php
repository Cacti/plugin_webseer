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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include_once(__DIR__ . '/constants.php');
include_once(__DIR__ . '/arrays.php');
include_once(__DIR__ . '/../classes/cURL.php');
include_once(__DIR__ . '/../classes/mxlookup.php');

/**
 * Validate and store the shared list-page request variables (rows, page,
 * refresh, sort and the optional state and regex filters) under $session.
 *
 * The urls, servers and proxies list pages differ only in their session key,
 * default sort column, default refresh interval and whether they expose the
 * state and regex filters, so those are the only parameters. Called from
 * each list page's request-validation function (e.g. list_urls()'s
 * webseer_request_validation(), list_servers()'s
 * webseer_request_validation(), and webseer_proxies.php's
 * request_validation()).
 *
 * @param string $session         The session key to store the validated
 *                                filter values under.
 * @param string $sort_default    The default sort column for this list.
 * @param int    $refresh_default The default page auto-refresh interval
 *                                in seconds.
 * @param bool   $with_state      Whether this list exposes a 'state'
 *                                (enabled/disabled) filter; defaults to
 *                                true.
 * @param bool   $with_rfilter    Whether this list exposes a regex
 *                                'rfilter' filter; defaults to false.
 *
 * @return void
 */
function webseer_validate_list_request($session, $sort_default, $refresh_default, $with_state = true, $with_rfilter = false) {
	$filters = [
		'rows' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			],
		'page' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			],
		'refresh' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => $refresh_default
			],
	];

	if ($with_rfilter) {
		$filters['rfilter'] = [
			'filter'  => FILTER_VALIDATE_IS_REGEX,
			'default' => '',
			'pageset' => true,
			'options' => ['options' => 'sanitize_search_string']
			];
	}

	$filters['sort_column'] = [
		'filter'  => FILTER_CALLBACK,
		'default' => $sort_default,
		'options' => ['options' => 'sanitize_search_string']
		];

	$filters['sort_direction'] = [
		'filter'  => FILTER_CALLBACK,
		'default' => 'ASC',
		'options' => ['options' => 'sanitize_search_string']
		];

	if ($with_state) {
		$filters['state'] = [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			];
	}

	validate_store_request_vars($filters, $session);
}

/**
 * Validate and store the shared history/log request variables under $session.
 *
 * Called from each history/log view's request-validation function (e.g.
 * webseer.php's webseer_log_request_validation() and
 * webseer_servers.php's webseer_log_request_validation()).
 *
 * @param string $session The session key to store the validated filter
 *                        values under.
 *
 * @return void
 */
function webseer_validate_log_request($session) {
	$filters = [
		'id' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '-1'
		],
		'rows' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		],
		'page' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		],
		'filter' => [
			'filter'  => FILTER_CALLBACK,
			'default' => '',
			'options' => ['options' => 'sanitize_search_string']
		],
		'sort_column' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'lastcheck',
			'options' => ['options' => 'sanitize_search_string']
		],
		'sort_direction' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'DESC',
			'options' => ['options' => 'sanitize_search_string']
		],
	];

	validate_store_request_vars($filters, $session);
}

/**
 * Renders this plugin's shared tab bar (Checks/Servers/Proxies), adding a
 * 'Log History' tab when a history view is currently displayed, and
 * highlighting the currently active tab. Called from each of this
 * plugin's list pages before rendering their content.
 *
 * @param string $current_tab The currently active page's filename (e.g.
 *                            'webseer.php'), used to determine which tab
 *                            to highlight.
 *
 * @return void Outputs the tab bar HTML directly.
 *
 * @global array $config Cacti global configuration array; used to build
 *                       the tab link URLs.
 */
function webseer_show_tab($current_tab) {
	global $config;

	$tabs = [
		'webseer.php'         => __('Checks', 'webseer'),
		'webseer_servers.php' => __('Servers', 'webseer'),
		'webseer_proxies.php' => __('Proxies', 'webseer')
	];

	if (get_request_var('action') == 'history') {
		if ($current_tab == 'webseer.php') {
			$current_tab        = 'webseer.php?action=history&id=' . get_filter_request_var('id');
			$tabs[$current_tab] = __('Log History', 'webeer');
		} else {
			$current_tab        = 'webseer_servers.php?action=history&id=' . get_filter_request_var('id');
			$tabs[$current_tab] = __('Log History', 'webeer');
		}
	}

	print "<div class='tabs'><nav><ul>\n";

	if (cacti_sizeof($tabs)) {
		foreach ($tabs as $url => $name) {
			print "<li><a class='" . (($url == $current_tab) ? 'pic selected' : 'pic') . "' href='" . $config['url_path'] .
				"plugins/webseer/$url'>$name</a></li>";
		}
	}

	print '</ul></nav></div>';
}

/**
 * Fetches the current server list from the master webseer server (via a
 * GETSERVERS request) and replaces this poller's local
 * plugin_webseer_servers table with the result. Currently has no call
 * site in this plugin; remote.php's own GETSERVERS case responds
 * directly (serving its own $servers list) rather than invoking this
 * function.
 *
 * @return void
 */
function plugin_webseer_refresh_servers() {
	$server = db_fetch_row('SELECT * FROM plugin_webseer_servers WHERE master = 1');
	$server = is_array($server) ? $server : [];

	if (empty($server['url'])) {
		return;
	}

	$server['debug_type'] = 'Server';

	$cc              = new cURL(true, 'cookies.txt', WEBSEER_COMPRESSION_GZIP, '', $server);
	$data            = [];
	$data['action']  = 'GETSERVERS';
	$results         = $cc->post($server['url'], $data);
	$results         = is_string($results) ? $results : '';

	$results         = explode("\n", $results);

	foreach ($results as $r) {
		if (substr($r, 0, 8) == 'SERVERS=') {
			$servers = substr($r, 8);
			$decoded = base64_decode($servers, true);
			$servers = $decoded === false ? false : unserialize($decoded, ['allowed_classes' => false]);

			if (isset($servers[0]['id'])) {
				db_execute('TRUNCATE TABLE plugin_webseer_servers');

				foreach ($servers as $save) {
					db_execute_prepared('REPLACE INTO plugin_webseer_servers (id, enabled, master, name, url, ip, location)
						VALUES (?,?,?,?,?,?,?)',
						[
							$save['id'], $save['enabled'], $save['master'], $save['name'], $save['url'], $save['ip'], $save['location']
						]
					);
				}
			}

			break;
		}
	}
}

/**
 * Fetches the current service check URL list from the master webseer
 * server (via a GETURLS request) and replaces this poller's local
 * plugin_webseer_urls table with the result. Called from
 * webseer_servers.php's form_save() when the saved server is marked as
 * 'isme' (this poller), to synchronize a remote poller's URL list with
 * the master.
 *
 * @return void
 */
function plugin_webseer_refresh_urls() {
	$server = db_fetch_row('SELECT * FROM plugin_webseer_servers WHERE master = 1');
	$server = is_array($server) ? $server : [];

	if (empty($server['url'])) {
		return;
	}

	$server['debug_type'] = 'Server';

	$cc             = new cURL(true, 'cookies.txt', WEBSEER_COMPRESSION_GZIP, '', $server);
	$data           = [];
	$data['action'] = 'GETURLS';
	$results        = $cc->post($server['url'], $data);
	$results        = is_string($results) ? $results : '';
	$results        = explode("\n", $results);

	foreach ($results as $r) {
		if (substr($r, 0, 5) == 'URLS=') {
			$urls    = substr($r, 5);
			$decoded = base64_decode($urls, true);
			$urls    = $decoded === false ? false : unserialize($decoded, ['allowed_classes' => false]);

			if (isset($urls[0]['id'])) {
				db_execute('TRUNCATE TABLE plugin_webseer_urls');

				foreach ($urls as $save) {
					db_execute_prepared('REPLACE INTO plugin_webseer_urls
						(id, enabled, requiresauth, checkcert, ip, display_name, notify_list, notify_accounts, url, search, search_maint, search_failed, notify_extra, downtrigger)
						VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						[
							$save['id'], $save['enabled'], $save['requiresauth'], $save['checkcert'],
							$save['ip'], $save['display_name'], $save['notify_list'], $save['notify_accounts'],
							$save['url'], $save['search'], $save['search_maint'],
							$save['search_failed'], $save['notify_extra'], $save['downtrigger']
						]
					);
				}
			}

			break;
		}
	}
}

/**
 * Deletes any webseer notification contacts belonging to Cacti users
 * that no longer exist. Called from webseer.php's form_save() after
 * saving a service check URL's notification settings.
 *
 * @return void
 */
function plugin_webseer_remove_old_users() {
	$users = db_fetch_assoc('SELECT id FROM user_auth');

	$u = [];

	foreach ($users as $user) {
		$u[] = $user['id'];
	}

	$contacts = db_fetch_assoc('SELECT DISTINCT user_id FROM plugin_webseer_contacts');

	foreach ($contacts as $c) {
		if (!in_array($c['user_id'], $u, true)) {
			db_execute_prepared('DELETE FROM plugin_webseer_contacts WHERE user_id = ?', [$c['user_id']]);
		}
	}
}

/**
 * Performs a 'dns' type service check: looks up the configured search
 * term as a DNS A record via mxlookup, and considers the check
 * successful if the resolved IP matches the configured maintenance
 * value. Called from webseer_process.php's main flow for each 'dns'
 * type service check.
 *
 * @param array $host The plugin_webseer_urls row being checked,
 *                    providing the domain ('search') and DNS server
 *                    ('url') to query, and the expected IP
 *                    ('search_maint').
 *
 * @return array|false The check result ('result', 'error', 'time',
 *                     'options' timing fields, and 'data' listing the
 *                     resolved A records), or false if $host is empty.
 */
function plugin_webseer_check_dns($host) {
	$results = false;

	if (cacti_sizeof($host)) {
		$results                               = [];
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

		$s                                  = microtime(true);
		$a                                  = new mxlookup($host['search'], $host['url']);
		$t                                  = microtime(true) - $s;
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

/**
 * Notifies every other registered remote server of a new master server
 * IP (via a SETMASTER request), then updates this poller's local
 * plugin_webseer_servers table to reflect the new master. Currently
 * has no call site in this plugin.
 *
 * @param string $ip The IP address of the newly designated master
 *                   server.
 *
 * @return void
 */
function plugin_webseer_set_remote_masters($ip) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';
		plugin_webseer_set_remote_master($server['url'], $ip);
	}

	db_execute('UPDATE plugin_webseer_servers set master = 0');
	db_execute_prepared('UPDATE plugin_webseer_servers set master = 1 WHERE ip = ?', [$ip]);
}

/**
 * Sends a SETMASTER request to a single remote server, informing it of
 * the current master server's IP. Called from
 * plugin_webseer_set_remote_masters() for each other registered server.
 *
 * @param array  $url The remote server row to notify (its 'url' field is
 *                    the endpoint posted to).
 * @param string $ip  The IP address of the master server to announce.
 *
 * @return void
 */
function plugin_webseer_set_remote_master($url, $ip) {
	$cc             = new cURL(true, 'cookies.txt', WEBSEER_COMPRESSION_GZIP, '', $url);
	$data           = [];
	$data['action'] = 'SETMASTER';
	$data['ip']     = $ip;
	$results        = $cc->post($url['url'], $data);
}

/**
 * Notifies every other registered remote server to enable or disable a
 * service check URL (via an ENABLEURL/DISABLEURL request). Called from
 * webseer.php's 'enable'/'disable' actions to keep remote pollers in
 * sync.
 *
 * @param int  $id    The plugin_webseer_urls.id to enable/disable
 *                    remotely.
 * @param bool $value True to enable, false to disable; defaults to
 *                    true.
 *
 * @return void
 */
function plugin_webseer_enable_remote_hosts($id, $value = true) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$cc             = new cURL(true, 'cookies.txt', WEBSEER_COMPRESSION_GZIP, '', $server);
		$data           = [];
		$data['action'] = ($value ? 'ENABLEURL' : 'DISABLEURL');
		$data['id']     = $id;
		$results        = $cc->post($server['url'], $data);
	}
}

/**
 * Notifies every other registered remote server to delete a service
 * check URL (via a DELETEURL request). Called from webseer.php's
 * form_actions() when a URL is deleted.
 *
 * @param int $id The plugin_webseer_urls.id to delete remotely.
 *
 * @return void
 */
function plugin_webseer_delete_remote_hosts($id) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$cc             = new cURL(true, 'cookies.txt', WEBSEER_COMPRESSION_GZIP, '', $server);
		$data           = [];
		$data['action'] = 'DELETEURL';
		$data['id']     = $id;
		$results        = $cc->post($server['url'], $data);
	}
}

/**
 * Notifies every other registered remote server to add a new service
 * check URL (via an ADDURL request). Called from webseer.php's
 * form_save() when a new service check URL is created.
 *
 * @param int   $id   The newly created plugin_webseer_urls.id.
 * @param array $save The URL's field values to replicate to remote
 *                    servers.
 *
 * @return void
 */
function plugin_webseer_add_remote_hosts($id, $save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';

		$cc             = new cURL(true, 'cookies.txt', WEBSEER_COMPRESSION_GZIP, '', $server);
		$save['action'] = 'ADDURL';
		$save['id']     = $id;
		$results        = $cc->post($server['url'], $save);
	}
}

/**
 * Notifies every other registered remote server to update an existing
 * service check URL (via an UPDATEURL request). Called from
 * webseer.php's form_save() when an existing service check URL is
 * updated.
 *
 * @param array $save The URL's updated field values (including its
 *                    'id') to replicate to remote servers.
 *
 * @return void
 */
function plugin_webseer_update_remote_hosts($save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';

		$cc             = new cURL(true, 'cookies.txt', WEBSEER_COMPRESSION_GZIP, '', $server);
		$save['action'] = 'UPDATEURL';
		$results        = $cc->post($server['url'], $save);
	}
}

/**
 * Notifies every other registered remote server to add a new webseer
 * server registration (via an ADDSERVER request). Called from
 * webseer_servers.php's form_save() when a new server is created.
 *
 * @param int   $id   The newly created plugin_webseer_servers.id.
 * @param array $save The server's field values to replicate to remote
 *                    servers.
 *
 * @return void
 */
function plugin_webseer_add_remote_server($id, $save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';

		$cc             = new cURL(true, 'cookies.txt', WEBSEER_COMPRESSION_GZIP, '', $server);
		$save['action'] = 'ADDSERVER';
		$save['id']     = $id;
		$results        = $cc->post($server['url'], $save);
	}
}

/**
 * Notifies every other registered remote server to update an existing
 * webseer server registration (via an UPDATESERVER request). Called from
 * webseer_servers.php's form_save() when an existing server is updated.
 *
 * @param array $save The server's updated field values (including its
 *                    'id') to replicate to remote servers.
 *
 * @return void
 */
function plugin_webseer_update_remote_server($save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';

		$cc             = new cURL(true, 'cookies.txt', WEBSEER_COMPRESSION_GZIP, '', $server);
		$save['action'] = 'UPDATESERVER';
		$results        = $cc->post($server['url'], $save);
	}
}

/**
 * Notifies every other registered remote server to enable or disable a
 * webseer server registration (via an ENABLESERVER/DISABLESERVER
 * request). Called from webseer_servers.php's 'enable'/'disable' actions
 * to keep remote pollers in sync.
 *
 * @param int  $id    The plugin_webseer_servers.id to enable/disable
 *                    remotely.
 * @param bool $value True to enable, false to disable; defaults to
 *                    true.
 *
 * @return void
 */
function plugin_webseer_enable_remote_server($id, $value = true) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';

		$cc             = new cURL(true, 'cookies.txt', WEBSEER_COMPRESSION_GZIP, '', $server);
		$data           = [];
		$data['action'] = ($value ? 'ENABLESERVER' : 'DISABLESERVER');
		$data['id']     = $id;
		$results        = $cc->post($server['url'], $data);
	}
}

/**
 * Notifies every other registered remote server to delete a webseer
 * server registration (via a DELETESERVER request). Called from
 * webseer_servers.php's form_actions() and do_webseer() when a server is
 * deleted.
 *
 * @param int $id The plugin_webseer_servers.id to delete remotely.
 *
 * @return void
 */
function plugin_webseer_delete_remote_server($id) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';

		$cc             = new cURL(true, 'cookies.txt', WEBSEER_COMPRESSION_GZIP, '', $server);
		$data           = [];
		$data['action'] = 'DELETESERVER';
		$data['id']     = $id;
		$results        = $cc->post($server['url'], $data);
	}
}

/**
 * Notifies every other registered remote server that a service check URL
 * is currently down (via a HOSTDOWN request), so they can record the
 * outage in their own servers log. Called from webseer_process.php's
 * main flow when a service check fails.
 *
 * @param array $save The check result/URL identification data to report
 *                    to remote servers.
 *
 * @return void
 */
function plugin_webseer_down_remote_hosts($save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$cc             = new cURL(true, 'cookies.txt', WEBSEER_COMPRESSION_GZIP, '', $server);
		$save['action'] = 'HOSTDOWN';
		$results        = $cc->post($server['url'], $save);
	}
}

/**
 * Synchronizes plugin_webseer_contacts with every Cacti user's email
 * address, adding/updating an 'email' type contact entry for each user
 * that has one configured. Currently unused/dead code: not called from
 * anywhere else in this file.
 *
 * @return void
 */
function plugin_webseer_update_contacts() {
	$users = db_fetch_assoc("SELECT id, 'email' AS type, email_address FROM user_auth WHERE email_address!=''");

	if (cacti_sizeof($users)) {
		foreach ($users as $u) {
			$cid = db_fetch_cell_prepared('SELECT id FROM plugin_webseer_contacts WHERE type="email" AND user_id=?', [$u['id']]);

			if ($cid) {
				db_execute_prepared(
					'REPLACE INTO plugin_webseer_contacts (id, user_id, type, data) VALUES (?, ?, \'email\', ?)',
					[$cid, $u['id'], $u['email_address']]
				);
			} else {
				db_execute_prepared(
					'REPLACE INTO plugin_webseer_contacts (user_id, type, data) VALUES (?, \'email\', ?)',
					[$u['id'], $u['email_address']]
				);
			}
		}
	}
}

/**
 * Enables debug output for this run when webseer is selectively enabled
 * via Cacti's 'selective_plugin_debug' setting, without overriding an
 * already-enabled global debug flag. Called from poller_webseer.php's
 * and webseer_process.php's main flow at the start of each run.
 *
 * @return void
 *
 * @global bool $debug Set to true when webseer-specific debug output is
 *                     enabled.
 */
function plugin_webseer_check_debug() {
	global $debug;

	if (!$debug) {
		$plugin_debug = read_config_option('selective_plugin_debug');

		if (preg_match('/(^|[, ]+)(webseer)($|[, ]+)/', $plugin_debug, $matches)) {
			$debug = (cacti_sizeof($matches) == 4 && $matches[2] == 'webseer');
		}
	}
}

/**
 * Logs a debug message to the Cacti log (and stdout) when debug output
 * is enabled, prefixed with the target host/server's debug type and id
 * for context. Called throughout this plugin's poller/processor scripts
 * and the cURL class to report progress during checks.
 *
 * @param string $message The debug message to log.
 * @param array  $host    The related host/server/URL row, used to
 *                        prefix the message with its 'debug_type' and
 *                        'id' when present; defaults to an empty array.
 *
 * @return void
 *
 * @global bool $debug Whether debug output is enabled; when false, this
 *                     function is a no-op.
 */
function plugin_webseer_debug($message = '', $host = []) {
	global $debug;

	if ($debug) {
		$prefix  = (empty($host['id']) && empty($host['debug_type'])) ? '' : '[';
		$suffix  = (empty($host['id']) && empty($host['debug_type'])) ? '' : '] ';
		$spacer  = (empty($host['id']) || empty($host['debug_type'])) ? '' : ' ';
		$host_id = (empty($host['id'])) ? '' : $host['id'];
		$host_dt = (empty($host['debug_type'])) ? '' : $host['debug_type'];
		cacti_log('DEBUG: ' . $prefix . $host_dt . $spacer . $host_id . $suffix . trim($message), true, 'WEBSEER');
	}
}
