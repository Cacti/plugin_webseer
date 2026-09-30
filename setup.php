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

/**
 * Return the CSP nonce attribute for inline <script> tags, safely across
 * Cacti versions. Newer Cacti releases enforce a Content-Security-Policy that
 * requires a per-request nonce on parser-inserted scripts; older releases lack
 * the CactiSecureHeaders class, so this returns an empty string there.
 *
 * @return string The nonce attribute when supported, otherwise empty string.
 */
function plugin_webseer_csp_nonce(): string {
	if (class_exists('CactiSecureHeaders')) {
		return CactiSecureHeaders::getNonceAttribute();
	}

	return '';
}

require_once(__DIR__ . '/includes/constants.php');
require_once(__DIR__ . '/includes/arrays.php');

/**
 * Registers this plugin's Cacti hooks (navigation breadcrumbs, config
 * arrays/menu, poller_bottom, data source replication) and its
 * webseer.php/webseer_servers.php/webseer_proxies.php realm, then
 * creates the plugin's database tables. Invoked by the Cacti plugin
 * framework when the plugin is installed/enabled.
 *
 * @return void
 */
function plugin_webseer_install() {
	global $config;

	api_plugin_register_hook('webseer', 'draw_navigation_text', 'plugin_webseer_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('webseer', 'config_arrays',        'plugin_webseer_config_arrays',        'setup.php');
	api_plugin_register_hook('webseer', 'poller_bottom',        'plugin_webseer_poller_bottom',        'setup.php');
	api_plugin_register_hook('webseer', 'replicate_out',        'webseer_replicate_out',               'setup.php');

	api_plugin_register_realm('webseer', 'webseer.php,webseer_servers.php,webseer_proxies.php', __('Web Service Check Admin', 'webseer'), 1);

	require_once($config['base_path'] . '/plugins/webseer/includes/database.php');

	plugin_webseer_setup_table();
}

/**
 * Drops all of this plugin's database tables. Invoked by the Cacti
 * plugin framework when the plugin is uninstalled.
 *
 * @return void
 */
function plugin_webseer_uninstall() {
	global $config;

	require_once($config['base_path'] . '/plugins/webseer/includes/database.php');

	plugin_webseer_drop_tables();
}

/**
 * Here we will check to ensure everything is configured
 *
 * Runs any pending database schema upgrades for this plugin. Invoked by
 * plugin_webseer_config_arrays() (the 'config_arrays' hook) only when
 * the current page is index.php, plugins.php, or webseer.php - not on
 * every page load.
 *
 * @return bool Always true.
 */
function plugin_webseer_check_config() {
	// Here we will check to ensure everything is configured
	plugin_webseer_upgrade();

	return true;
}

/**
 * Here we will upgrade to the newest version
 *
 * Applies version-gated schema migrations (adding the contacts and
 * proxies tables, renaming the URL log table, adding compression/
 * notify-format/poller-id columns) and updates the recorded plugin
 * version/realm file list, based on comparing the installed version
 * against the current INFO file version. Called from
 * plugin_webseer_check_config(), which is itself only invoked when the
 * current page is index.php, plugins.php, or webseer.php.
 *
 * @return bool Always true.
 *
 * @global array $config Cacti global configuration array (declared but
 *                       not directly used here).
 */
function plugin_webseer_upgrade() {
	// Here we will upgrade to the newest version
	global $config;

	require_once($config['base_path'] . '/plugins/webseer/includes/database.php');

	$info = plugin_webseer_version();

	if (!isset($info['version'], $info['longname'], $info['author'], $info['homepage'], $info['name'])) {
		cacti_log('ERROR: webseer plugin INFO file is missing required fields, skipping upgrade check', false, 'WEBSEER');

		return true;
	}

	$new  = $info['version'];
	$old  = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="webseer"');

	if ($new != $old) {
		// Refresh the schema from the shared definition in includes/database.php:
		// create any missing tables, db_update_table() diff the rest. The historical
		// plugin_webseer_url_log -> plugin_webseer_urls_log rename (and the column
		// additions that were previously hand-written ALTERs) are handled there.
		webseer_upgrade_tables();

		db_execute_prepared('UPDATE plugin_config SET
			version = ?, name = ?, author = ?, webpage = ?
			WHERE directory = ?',
			[
				$info['version'],
				$info['longname'],
				$info['author'],
				$info['homepage'],
				$info['name']
			]
		);

		db_execute_prepared('UPDATE plugin_realms
			SET file = ?
			WHERE file LIKE "%webseer.php%"',
			['webseer.php,webseer_servers.php,webseer_proxies.php']);

		api_plugin_register_hook('webseer', 'replicate_out', 'webseer_replicate_out', 'setup.php', '1');
	}

	return true;
}

/**
 * Reads this plugin's version/author metadata from its INFO file.
 * Invoked by the Cacti plugin framework to display plugin information,
 * and called directly by plugin_webseer_upgrade() and
 * poller_webseer.php's display_version().
 *
 * @return array The plugin's INFO file 'info' section (name, version,
 *               author, etc.).
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the plugin's INFO file.
 */
function plugin_webseer_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/webseer/INFO', true);

	return isset($info['info']) && is_array($info['info']) ? $info['info'] : [];
}

/**
 * Launches a background poller_webseer.php process to run the
 * configured service checks. Invoked by the Cacti plugin framework via
 * the 'poller_bottom' hook at the end of each poller cycle.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       resolve the PHP binary and this plugin's poller
 *                       script path.
 */
function plugin_webseer_poller_bottom() {
	global $config;

	require_once($config['library_path'] . '/database.php');

	$command_string = trim(read_config_option('path_php_binary'));

	// If its not set, just assume its in the path
	if (trim($command_string) == '') {
		$command_string = 'php';
	}
	$extra_args = ' -q ' . $config['base_path'] . '/plugins/webseer/poller_webseer.php';

	exec_background($command_string, $extra_args);
}

/**
 * Adds this plugin's 'Service Checks' entry to the Management menu, and
 * triggers a schema-upgrade check when the currently displayed page is
 * exactly index.php, plugins.php, or webseer.php (not the servers or
 * proxies pages). Invoked by the Cacti plugin framework via the
 * 'config_arrays' hook.
 *
 * @return void
 *
 * @global array $menu                       Cacti's registered admin
 *                                           menu; a 'Service Checks'
 *                                           entry is added under
 *                                           'Management'.
 * @global array $user_auth_realms           Reserved/declared for parity
 *                                           with other hook
 *                                           implementations; not used
 *                                           directly here.
 * @global array $user_auth_realm_filenames  Reserved/declared for parity
 *                                           with other hook
 *                                           implementations; not used
 *                                           directly here.
 */
function plugin_webseer_config_arrays() {
	global $menu, $user_auth_realms, $user_auth_realm_filenames;

	$menu[__('Management')]['plugins/webseer/webseer.php'] = __('Service Checks', 'webseer');

	$files = ['index.php', 'plugins.php', 'webseer.php'];

	if (in_array(get_current_page(), $files, true)) {
		plugin_webseer_check_config();
	}
}

/**
 * Adds this plugin's page breadcrumb/navigation entries (service check
 * list/edit/save, server list/edit/save, proxy list/edit/save). Invoked
 * by the Cacti plugin framework via the 'draw_navigation_text' hook.
 *
 * @param array $nav Cacti's registered navigation text entries.
 *
 * @return array The $nav array with this plugin's entries added.
 */
function plugin_webseer_draw_navigation_text($nav) {
	$nav['webseer.php:'] = [
		'title'   => __('WebSeer Service Checks', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer.php:edit'] = [
		'title'   => __('Service Check Edit', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer.php:save'] = [
		'title'   => __('Service Check Save', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer_servers.php:'] = [
		'title'   => __('WebSeer Servers', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer_servers.php',
		'level'   => '1'
	];

	$nav['webseer_servers.php:edit'] = [
		'title'   => __('Server Edit', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer_servers.php:save'] = [
		'title'   => __('Save Server', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer_proxies.php:'] = [
		'title'   => __('WebSeer Proxies', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer_proxies.php:edit'] = [
		'title'   => __('Proxie Edit', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer_proxies.php:save'] = [
		'title'   => __('Save Proxy', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	return $nav;
}

/**
 * Replicates this plugin's configuration tables (contacts, proxies,
 * servers, service check URLs) out to a remote data collector poller.
 * Invoked by the Cacti plugin framework via the 'replicate_out' hook
 * during data collector replication.
 *
 * @param array $data Replication context, including 'remote_poller_id',
 *                    'rcnn_id' (remote connection id), and 'class'
 *                    (replication scope, e.g. 'all').
 *
 * @return array The $data array, passed through unchanged for hook chaining.
 */
function webseer_replicate_out($data) {
	$remote_poller_id = $data['remote_poller_id'];
	$rcnn_id          = $data['rcnn_id'];
	$class            = $data['class'];

	cacti_log('INFO: Replicating for the WebSeer Plugin', false, 'REPLICATE');

	$tables = [
		'plugin_webseer_contacts',
		'plugin_webseer_proxies',
		'plugin_webseer_servers',
		'plugin_webseer_urls'
	];

	if ($class == 'all') {
		foreach ($tables as $table) {
			$tdata = db_fetch_assoc('SELECT * FROM ' . $table);
			replicate_out_table($rcnn_id, $tdata, $table, $remote_poller_id);
		}
	}

	return $data;
}
