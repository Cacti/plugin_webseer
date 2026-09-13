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
 * state and regex filters, so those are the only parameters.
 * @param mixed $session
 * @param mixed $sort_default
 * @param mixed $refresh_default
 * @param mixed $with_state
 * @param mixed $with_rfilter
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
 * @param mixed $session
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

function plugin_webseer_refresh_servers() {
	$server               = db_fetch_row('SELECT * FROM plugin_webseer_servers WHERE master = 1');
	$server['debug_type'] = 'Server';

	$cc              = new cURL(true, 'cookies.txt', 'gzip', '', $server);
	$data            = [];
	$data['action']  = 'GETSERVERS';
	$results         = $cc->post($server['url'], $data);

	$results         = explode("\n", $results);

	foreach ($results as $r) {
		if (substr($r, 0, 8) == 'SERVERS=') {
			$servers = substr($r, 8);
			$servers = unserialize(base64_decode($servers, true), ['allowed_classes' => false]);

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

function plugin_webseer_refresh_urls() {
	$server = db_fetch_row('SELECT * FROM plugin_webseer_servers WHERE master = 1');

	$server['debug_type'] = 'Server';

	$cc             = new cURL(true, 'cookies.txt', 'gzip', '', $server);
	$data           = [];
	$data['action'] = 'GETURLS';
	$results        = $cc->post($server['url'], $data);
	$results        = explode("\n", $results);

	foreach ($results as $r) {
		if (substr($r, 0, 5) == 'URLS=') {
			$urls = substr($r, 5);
			$urls = unserialize(base64_decode($urls, true), ['allowed_classes' => false]);

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

function plugin_webseer_set_remote_masters($ip) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';
		plugin_webseer_set_remote_master($server['url'], $ip);
	}

	db_execute('UPDATE plugin_webseer_servers set master = 0');
	db_execute_prepared('UPDATE plugin_webseer_servers set master = 1 WHERE ip = ?', [$ip]);
}

function plugin_webseer_set_remote_master($url, $ip) {
	$cc             = new cURL(true, 'cookies.txt', 'gzip', '', $url);
	$data           = [];
	$data['action'] = 'SETMASTER';
	$data['ip']     = $ip;
	$results        = $cc->post($url['url'], $data);
}

function plugin_webseer_enable_remote_hosts($id, $value = true) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$cc             = new cURL(true, 'cookies.txt', 'gzip', '', $server);
		$data           = [];
		$data['action'] = ($value ? 'ENABLEURL' : 'DISABLEURL');
		$data['id']     = $id;
		$results        = $cc->post($server['url'], $data);
	}
}

function plugin_webseer_delete_remote_hosts($id) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$cc             = new cURL(true, 'cookies.txt', 'gzip', '', $server);
		$data           = [];
		$data['action'] = 'DELETEURL';
		$data['id']     = $id;
		$results        = $cc->post($server['url'], $data);
	}
}

function plugin_webseer_add_remote_hosts($id, $save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';

		$cc             = new cURL(true, 'cookies.txt', 'gzip', '', $server);
		$save['action'] = 'ADDURL';
		$save['id']     = $id;
		$results        = $cc->post($server['url'], $save);
	}
}

function plugin_webseer_update_remote_hosts($save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';

		$cc             = new cURL(true, 'cookies.txt', 'gzip', '', $server);
		$save['action'] = 'UPDATEURL';
		$results        = $cc->post($server['url'], $save);
	}
}

function plugin_webseer_add_remote_server($id, $save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';

		$cc             = new cURL(true, 'cookies.txt', 'gzip', '', $server);
		$save['action'] = 'ADDSERVER';
		$save['id']     = $id;
		$results        = $cc->post($server['url'], $save);
	}
}

function plugin_webseer_update_remote_server($save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';

		$cc             = new cURL(true, 'cookies.txt', 'gzip', '', $server);
		$save['action'] = 'UPDATESERVER';
		$results        = $cc->post($server['url'], $save);
	}
}

function plugin_webseer_enable_remote_server($id, $value = true) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';

		$cc             = new cURL(true, 'cookies.txt', 'gzip', '', $server);
		$data           = [];
		$data['action'] = ($value ? 'ENABLESERVER' : 'DISABLESERVER');
		$data['id']     = $id;
		$results        = $cc->post($server['url'], $data);
	}
}

function plugin_webseer_delete_remote_server($id) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$server['debug_type'] = 'Server';

		$cc             = new cURL(true, 'cookies.txt', 'gzip', '', $server);
		$data           = [];
		$data['action'] = 'DELETESERVER';
		$data['id']     = $id;
		$results        = $cc->post($server['url'], $data);
	}
}

function plugin_webseer_down_remote_hosts($save) {
	$servers = db_fetch_assoc('SELECT * FROM plugin_webseer_servers WHERE isme = 0');

	foreach ($servers as $server) {
		$cc             = new cURL(true, 'cookies.txt', 'gzip', '', $server);
		$save['action'] = 'HOSTDOWN';
		$results        = $cc->post($server['url'], $save);
	}
}

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

function plugin_webseer_check_debug() {
	global $debug;

	if (!$debug) {
		$plugin_debug = read_config_option('selective_plugin_debug');

		if (preg_match('/(^|[, ]+)(webseer)($|[, ]+)/', $plugin_debug, $matches)) {
			$debug = (cacti_sizeof($matches) == 4 && $matches[2] == 'webseer');
		}
	}
}

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
